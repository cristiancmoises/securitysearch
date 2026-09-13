#!/usr/bin/env python3
"""Explicit/after-success retention. Never stops a running container or prunes Docker.

Retires known older builds, including the stopped rollback after successful promotion.
Gzip scope is only recognized upload archives under securitysearch-incoming.
Private backups, extracted source, volumes, networks and shared images are preserved.
"""
import argparse
import datetime as dt
import fcntl
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import time

LOCK = Path('/run/lock/securitysearch-update.lock')
DOCKER = ['docker', '--host', 'unix:///var/run/docker.sock']
REF = re.compile(r'^(?:docker\.io/library/)?security-?search(?:-audit-only)?:v([0-9]{1,5}\.[0-9]{1,5}\.[0-9]{1,5})-([0-9]{8}t[0-9]{6}z)(?:-[0-9a-f]{12})?(?:-audit)?$')
NAME = re.compile(r'^/?security-search-(?:candidate|rollback|failed|audit)-[0-9]{8}t[0-9]{6}z(?:-[0-9a-f]{12})?$')
ID = re.compile(r'^(?:sha256:)?[0-9a-f]{64}$')
STATES = ('exited', 'created', 'dead')
MAX_OBJECTS = 4000
INSPECT_BATCH = 16

class CleanupError(RuntimeError):
    pass

def version(value):
    if not isinstance(value, str) or not re.fullmatch(r'[0-9]{1,5}\.[0-9]{1,5}\.[0-9]{1,5}', value):
        raise CleanupError('Invalid release version; cleanup refused.')
    return tuple(int(x) for x in value.split('.'))

def stamp(value):
    if not isinstance(value, str) or len(value) > 40:
        raise CleanupError('Invalid Docker creation timestamp.')
    try:
        parsed = dt.datetime.fromisoformat(value.replace('Z', '+00:00'))
        if parsed.tzinfo is None:
            raise ValueError()
        return parsed.timestamp()
    except ValueError:
        raise CleanupError('Unrecognized Docker creation timestamp.') from None

def docker(*args):
    try:
        env = {k:v for k,v in os.environ.items() if not k.startswith('DOCKER_')}
        p = subprocess.run(DOCKER + list(args), env=env, stdout=subprocess.PIPE,
                           stderr=subprocess.PIPE, timeout=90)
    except (OSError, subprocess.TimeoutExpired):
        raise CleanupError('Docker query/removal unavailable; no force operation attempted.') from None
    if p.returncode:
        raise CleanupError('Docker '+args[0]+' failed (exit='+str(p.returncode)+'); cleanup stopped.')
    if len(p.stdout) > 32*1024*1024:
        raise CleanupError('Docker response exceeded cleanup limit.')
    return p.stdout.decode('utf-8')

def inspect(run, kind, obj):
    try:
        value = json.loads(run(kind, 'inspect', obj))
        if not isinstance(value, list) or len(value) != 1 or not isinstance(value[0], dict):
            raise ValueError()
        return value[0]
    except (ValueError, TypeError, IndexError):
        raise CleanupError('Invalid Docker inspect response.') from None

def objects(run, kind):
    # Exact immutable IDs; bounded batches reduce CLI startup/socket overhead.
    ids = list(dict.fromkeys(run(kind, 'ls', '-aq', '--no-trunc').split()))
    if len(ids) > MAX_OBJECTS or any(not ID.fullmatch(x) for x in ids):
        raise CleanupError('Docker object inventory invalid or over limit.')
    result = []
    for offset in range(0, len(ids), INSPECT_BATCH):
        batch = ids[offset:offset+INSPECT_BATCH]
        try:
            rows = json.loads(run(kind, 'inspect', *batch))
            if not isinstance(rows, list) or len(rows) != len(batch):
                raise ValueError()
            by_id = {}
            for row in rows:
                if not isinstance(row, dict) or row.get('Id') not in batch or row['Id'] in by_id:
                    raise ValueError()
                by_id[row['Id']] = row
            if set(by_id) != set(batch):
                raise ValueError()
            result.extend(by_id[key] for key in batch)
        except (ValueError, TypeError, KeyError):
            raise CleanupError('Docker batch inventory mismatch; cleanup stopped.') from None
    return result

INCOMING = Path('/root/securitysearch-incoming')
UPLOAD = re.compile(r'[0-9]{8}T[0-9]{6}Z-[0-9a-f]{12}')
ARCHIVE = re.compile(r'securitysearch-v([0-9]{1,5}\.[0-9]{1,5}\.[0-9]{1,5})-deploy\.tar\.gz')

def build_key(ref):
    m = REF.fullmatch(ref) if isinstance(ref, str) else None
    if not m:
        return None
    try:
        when = dt.datetime.strptime(m[2], '%Y%m%dt%H%M%Sz').replace(tzinfo=dt.timezone.utc)
        return version(m[1]), int(when.timestamp())
    except (ValueError, CleanupError):
        return None

def healthy(obj):
    s = obj.get('State') or {}
    return (s.get('Status') == 'running' and s.get('Running') is True
            and (s.get('Health') or {}).get('Status') == 'healthy')

def stable(obj):
    s = obj.get('State') or {}
    return (obj.get('Id'), obj.get('Image'), obj.get('Name'), s.get('StartedAt'), obj.get('RestartCount'))

def eligible_container(obj, active, cutoff):
    state = obj.get('State') or {}; config = obj.get('Config') or {}
    key = build_key(config.get('Image')); name = obj.get('Name', '')
    label = (config.get('Labels') or {}).get('co.securityops.release')
    return (obj.get('Id') != active.get('Id') and NAME.fullmatch(name) is not None
            and key is not None and key < cutoff and key[1] <= cutoff[1]
            and (label is None or version(label) == key[0])
            and state.get('Status') in STATES
            and not any(state.get(k) for k in ('Running', 'Paused', 'Restarting')))

def eligible_image(obj, used, cutoff):
    tags = obj.get('RepoTags')
    if (obj.get('Id') in used or not isinstance(tags, list) or not tags
            or obj.get('RepoDigests')):
        return False
    keys = [build_key(t) for t in tags]
    return all(key is not None and key < cutoff and key[1] <= cutoff[1] for key in keys)

def plan(containers, images, active):
    if not healthy(active):
        raise CleanupError('Production must be running and healthy.')
    cutoff = build_key((active.get('Config') or {}).get('Image'))
    if cutoff is None or not ID.fullmatch(active.get('Id','')) or not ID.fullmatch(active.get('Image','')):
        raise CleanupError('Current production build identity is not recognized.')
    doomed = [c for c in containers if eligible_container(c, active, cutoff)]
    gone = {c['Id'] for c in doomed}
    used = {c['Image'] for c in containers if c['Id'] not in gone} | {active['Image']}
    return {'containers':[c['Id'] for c in doomed],
            'images':[i['Id'] for i in images if eligible_image(i, used, cutoff)]}, cutoff

def open_dir(path):
    """Anchor each component; never traverse a symbolic link."""
    path = Path(path)
    if not path.is_absolute() or '..' in path.parts:
        raise CleanupError('Unrecognized archive directory.')
    fd = os.open('/', os.O_RDONLY | os.O_DIRECTORY)
    try:
        for name in path.parts[1:]:
            nxt = os.open(name, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW, dir_fd=fd)
            os.close(fd); fd=nxt
            st=os.fstat(fd)
            if st.st_uid != os.geteuid() or st.st_mode & 0o022:
                raise CleanupError('Untrusted archive directory; no archive cleanup.')
        return fd
    except BaseException:
        os.close(fd); raise

def gzip_uploads(cutoff, containers, *, execute=False, root=INCOMING, guard=lambda:None, keep_upload=None):
    """Do not delete extracted source, mounted inputs, backups, or arbitrary .gz files."""
    result={'eligible':[], 'removed':[], 'skipped':0}
    try: fd=open_dir(root)
    except FileNotFoundError: return result
    try:
        names=os.listdir(fd)
        if len(names)>4096: raise CleanupError('Archive inventory exceeds limit.')
        mounts=[Path(m['Source']) for c in containers for m in c.get('Mounts',[]) if m.get('Type')=='bind' and isinstance(m.get('Source'),str)]
        for name in sorted(names):
            if not UPLOAD.fullmatch(name) or name==keep_upload: continue
            timestamp=int(dt.datetime.strptime(name[:16],'%Y%m%dT%H%M%SZ').replace(tzinfo=dt.timezone.utc).timestamp())
            child=os.stat(name,dir_fd=fd,follow_symlinks=False)
            if not stat.S_ISDIR(child.st_mode) or child.st_uid!=os.geteuid() or child.st_mode & 0o022:
                result['skipped']+=1;continue
            d=os.open(name,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW,dir_fd=fd)
            try:
                entries=os.listdir(d)
                if len(entries)>256: raise CleanupError('Upload directory exceeds limit.')
                for archive in sorted(entries):
                    m=ARCHIVE.fullmatch(archive)
                    if not m or timestamp>cutoff[1] or (version(m[1]),timestamp)>=cutoff:continue
                    path=root/name/archive
                    if any(path==p or p in path.parents or path in p.parents for p in mounts):
                        result['skipped']+=1;continue
                    pair=[archive]
                    if archive+'.sha256' in entries:pair.append(archive+'.sha256')
                    stats=[]
                    for member in pair:
                        s=os.stat(member,dir_fd=d,follow_symlinks=False)
                        if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or s.st_uid!=os.geteuid() or s.st_mode&0o022:
                            stats=[];break
                        stats.append(s)
                    if not stats:result['skipped']+=1;continue
                    for member,s in zip(pair,stats):
                        result['eligible'].append(name+'/'+member)
                        if execute:
                            guard()
                            now=os.stat(member,dir_fd=d,follow_symlinks=False)
                            stamp=lambda x:(x.st_dev,x.st_ino,x.st_mode,x.st_size,x.st_mtime_ns,x.st_ctime_ns,x.st_nlink)
                            if stamp(now)!=stamp(s):raise CleanupError('Upload file changed; deletion stopped.')
                            os.unlink(member,dir_fd=d)
                            result['removed'].append(name+'/'+member)
            finally:os.close(d)
    finally:os.close(fd)
    return result

def current_upload(active, incoming=INCOMING):
    key=build_key((active.get('Config') or {}).get('Image'))
    if not key:return None
    stamp_text=dt.datetime.fromtimestamp(key[1],dt.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    path=Path('/root/securitysearch-backups')/stamp_text/'source-directory.txt'
    try:
        directory=open_dir(path.parent)
        try:
            f=os.open(path.name,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK,dir_fd=directory)
            try:
                info=os.fstat(f)
                if not stat.S_ISREG(info.st_mode) or info.st_uid!=os.geteuid() or info.st_nlink!=1 or info.st_mode&0o022 or info.st_size>4096:return None
                source=Path(os.read(f,4097).decode().strip())
            finally:os.close(f)
        finally:os.close(directory)
        if source.parent.parent==incoming and UPLOAD.fullmatch(source.parent.name):return source.parent.name
    except (OSError,ValueError,UnicodeError,CleanupError):pass
    return None

def cleanup(*, execute=False, run=docker, expected=None, incoming=INCOMING):
    active=inspect(run,'container','security-search')
    if expected and (active.get('Id'),active.get('Image'))!=tuple(expected):
        raise CleanupError('Accepted production identity changed; no cleanup.')
    pinned=stable(active); containers=objects(run,'container'); images=objects(run,'image')
    candidates,cutoff=plan(containers,images,active)
    def guard():
        live=inspect(run,'container','security-search')
        if not healthy(live) or stable(live)!=pinned:raise CleanupError('Production changed; cleanup stopped.')
    result={'schema':1,'mode':'execute' if execute else 'plan','production_container':active['Id'],
            'production_image':active['Image'],'candidates':candidates,
            'removed_containers':[],'removed_images':[], 'removed_image_tags':[], 'rollback_policy':'older_stopped_rollback_is_retired',
            'scope':'owned_older_builds_and_incoming_upload_gzip_only', 'complete':False}
    try:
        if execute:
            for cid in candidates['containers']:
                guard(); current=inspect(run,'container',cid)
                if not eligible_container(current,active,cutoff):continue
                # No force or volumes flag: a concurrently started container is protected by Docker.
                run('container','rm',cid);result['removed_containers'].append(cid)
            for iid in candidates['images']:
                guard(); current=inspect(run,'image',iid)
                used={c['Image'] for c in objects(run,'container')}|{active['Image']}
                if not eligible_image(current,used,cutoff):continue
                tags = sorted(current['RepoTags'])
                if len(tags) == 1:
                    run('image','rm','--no-prune',iid)
                else:
                    # Docker refuses ID removal of multi-tagged images without force.
                    # Untag only this previously validated all-project set, checking
                    # identity and container references again before each operation.
                    pending = set(tags)
                    for tag in tags:
                        guard(); current = inspect(run,'image',iid)
                        used = {c['Image'] for c in objects(run,'container')} | {active['Image']}
                        if set(current.get('RepoTags') or []) != pending or not eligible_image(current,used,cutoff):
                            raise CleanupError('Image tags or references changed; untagging stopped.')
                        if inspect(run,'image',tag).get('Id') != iid:
                            raise CleanupError('Image tag identity changed; untagging stopped.')
                        run('image','rm','--no-prune',tag)
                        pending.remove(tag); result['removed_image_tags'].append(tag)
                result['removed_images'].append(iid)
        remaining=objects(run,'container') if execute else containers
        keep=current_upload(active,incoming)
        result['archives']=gzip_uploads(cutoff,remaining,execute=execute,root=incoming,guard=guard,keep_upload=keep) if keep else {'status':'skipped_current_upload_pointer_unavailable','removed':[]}
        guard();result['complete']=True
        return result
    except (Exception,KeyboardInterrupt) as e:
        result['error']='cleanup_incomplete_no_force_no_rollback_of_completed_removals'
        print(json.dumps(result),flush=True)
        raise CleanupError('Cleanup incomplete; production was not intentionally stopped. Completed removals remain.') from e

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--execute',action='store_true')
    p.add_argument('--expected-container');p.add_argument('--expected-image')
    args=p.parse_args()
    if bool(args.expected_container)!=bool(args.expected_image):raise CleanupError('Both expected IDs are required.')
    if os.geteuid()!=0:raise CleanupError('Run on the Docker host as root.')
    fd=os.open(LOCK,os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600)
    try:
        s=os.fstat(fd)
        if not stat.S_ISREG(s.st_mode) or s.st_uid!=0 or s.st_nlink!=1 or s.st_mode&0o022:
            raise CleanupError('Untrusted deployment lock.')
        fcntl.flock(fd,fcntl.LOCK_EX|fcntl.LOCK_NB)
        expected=(args.expected_container,args.expected_image) if args.expected_container else None
        result=cleanup(execute=args.execute,expected=expected)
        print(json.dumps(result,indent=2))
    finally:os.close(fd)

if __name__=='__main__':
    try:main()
    except (Exception,KeyboardInterrupt):
        print('Cleanup stopped; no force removal, global prune, or production stop requested.',file=sys.stderr)
        sys.exit(2)
