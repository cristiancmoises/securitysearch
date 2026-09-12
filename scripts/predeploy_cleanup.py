#!/usr/bin/env python3
"""Plan/remove only old SecuritySearch Docker objects; never prune shared data.

A normal deploy invokes run_cleanup with its exclusive deployment lock held.
The independent CLI is a read-only plan unless --execute is explicitly supplied.
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
REF = re.compile(r'^(?:docker\.io/library/)?security-?search:v([0-9]+\.[0-9]+\.[0-9]+)-[0-9]{8}t[0-9]{6}z(?:-audit)?$')
NAME = re.compile(r'^/?security-search-(?:candidate|rollback|failed|audit)-[0-9]{8}t[0-9]{6}z$')
ID = re.compile(r'^(?:sha256:)?[0-9a-f]{64}$')
STATES = ('exited', 'created', 'dead')
MIN_AGE = 3600
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
        p = subprocess.run(DOCKER + list(args), stdout=subprocess.PIPE,
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

def rollback_time(obj):
    """A rollback's name records retirement time; Created records original launch."""
    name = obj.get('Name', '')
    if not NAME.fullmatch(name) or '-rollback-' not in name:
        return None
    value = name.rsplit('-rollback-', 1)[1]
    try:
        return dt.datetime.strptime(value, '%Y%m%dt%H%M%Sz').replace(tzinfo=dt.timezone.utc).timestamp()
    except ValueError:
        return None

def identity(obj):
    s = obj.get('State') or {}
    return (obj.get('Id'), obj.get('Image'), s.get('StartedAt'), obj.get('RestartCount'), obj.get('Name'))

def healthy(obj):
    s = obj.get('State') or {}
    return (s.get('Status') == 'running' and s.get('Running') is True
            and (s.get('Health') or {}).get('Status') == 'healthy')

def container_version(obj):
    c = obj.get('Config') or {}
    ref = REF.fullmatch(c.get('Image', ''))
    if not ref or not NAME.fullmatch(obj.get('Name', '')):
        return None
    label = (c.get('Labels') or {}).get('co.securityops.release')
    if label is not None and label != ref[1]:
        return None
    return version(ref[1])

def image_versions(obj):
    tags = obj.get('RepoTags') or []
    if not isinstance(tags, list) or len(tags) != 1:
        return None  # Unidentified or multiply tagged images are deliberately preserved.
    values = []
    for tag in tags:
        m = REF.fullmatch(tag)
        if not m:
            return None  # A shared/non-project tag protects the entire image.
        values.append(version(m[1]))
    return values

def make_plan(containers, images, active, target, now):
    if not healthy(active):
        raise CleanupError('Production must be running/healthy before cleanup.')
    target_v = version(target)
    current_id = active['Id']
    protected = {current_id}
    rollback = [c for c in containers if container_version(c) is not None
                and '-rollback-' in c.get('Name', '')]
    dated = [(c, rollback_time(c)) for c in rollback]
    known = [value for _, value in dated if value is not None and value <= now]
    newest = max(known) if known else None
    for container, retired in dated:
        # Unknown/future retirement dates are conservative preservation, not deletion.
        # Preserve ties so inventory order cannot choose which rollback survives.
        if retired is None or retired == newest or retired > now:
            protected.add(container['Id'])
    removable = []
    for c in containers:
        cv = container_version(c)
        state = c.get('State') or {}
        if (cv is not None and cv < target_v and c['Id'] not in protected
                and state.get('Status') in STATES and not state.get('Running')
                and not state.get('Paused') and not state.get('Restarting')
                and now - stamp(c['Created']) >= MIN_AGE):
            removable.append(c)
    removed_ids = {c['Id'] for c in removable}
    used = {c['Image'] for c in containers if c['Id'] not in removed_ids}
    used.add(active['Image'])
    unused = []
    for image in images:
        versions = image_versions(image)
        if (versions is not None and all(v < target_v for v in versions)
                and image['Id'] not in used and now - stamp(image['Created']) >= MIN_AGE):
            unused.append(image)
    return {
        'schema': 1, 'mode': 'plan', 'target': target,
        'minimum_age_seconds': MIN_AGE,
        'production_id': current_id, 'production_image': active['Image'],
        'protected_containers': sorted(protected),
        'containers': [{'id': c['Id'], 'name': c['Name'], 'image': c['Image'],
                        'created': c['Created'], 'status': c['State']['Status']} for c in removable],
        'images': [{'id': i['Id'], 'tags': sorted(i.get('RepoTags') or []),
                    'created': i['Created'], 'size': i.get('Size', 0)}
                   for i in sorted(unused, key=lambda x: stamp(x['Created']), reverse=True)],
        'preserved': 'Running/paused/restarting containers, current image, newest rollback, '
                     'same/newer releases, recent objects, unknown/shared/multi-tag images; '
                     'volumes/networks/build cache/backups/NPM are untouched.',
    }

def check_lock(fd):
    try:
        info = os.fstat(fd)
        if not stat.S_ISREG(info.st_mode) or info.st_uid != os.geteuid() or info.st_mode & 0o022:
            raise CleanupError('Unsafe cleanup lock descriptor.')
        # flock on the already-held open description does not release the deploy lock.
        fcntl.flock(fd, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except (OSError, TypeError):
        raise CleanupError('Exclusive deployment lock unavailable; cleanup refused.') from None

def run_cleanup(target, *, execute=False, lock_fd=None, run=docker, now=None, record=None):
    if execute:
        check_lock(lock_fd)
    now = time.time() if now is None else now
    active = inspect(run, 'container', 'security-search')
    before = identity(active)
    inventory = objects(run, 'container')
    images = objects(run, 'image')
    plan = make_plan(inventory, images, active, target, now)
    plan['removed_containers'] = []
    plan['removed_images'] = []
    if not execute:
        return plan
    if record is not None:
        record(plan)
    def guard():
        state = inspect(run, 'container', 'security-search')
        if identity(state) != before or not healthy(state):
            raise CleanupError('Production changed; no further cleanup is allowed.')
    for item in plan['containers']:
        guard()
        fresh = inspect(run, 'container', item['id'])
        # Recompute against the current inventory so a new rollback or active state
        # never becomes removable because of a stale initial plan.
        current = objects(run, 'container')
        allowed = make_plan(current, [], active, target, now)['containers']
        if not any(c == item for c in allowed):
            raise CleanupError('Container identity/state changed; cleanup stopped.')
        if fresh['Image'] != item['image'] or fresh['Name'] != item['name']:
            raise CleanupError('Container identity changed; cleanup stopped.')
        run('container', 'rm', item['id'])  # Never --force or --volumes.
        plan['removed_containers'].append(item['id'])
        if record is not None:
            record(plan)
    for item in plan['images']:
        guard()
        current = objects(run, 'container')
        if item['id'] in {c['Image'] for c in current}:
            continue
        fresh = inspect(run, 'image', item['id'])
        if sorted(fresh.get('RepoTags') or []) != item['tags'] or fresh['Created'] != item['created']:
            raise CleanupError('Image tags/identity changed; cleanup stopped.')
        # Validate the tag still points to this precise image immediately before rm.
        for tag in item['tags']:
            if inspect(run, 'image', tag)['Id'] != item['id']:
                raise CleanupError('Image tag moved; cleanup stopped.')
        run('image', 'rm', '--no-prune', item['id'])
        plan['removed_images'].append(item['id'])
        if record is not None:
            record(plan)
    guard()
    plan['mode'] = 'executed'
    if record is not None:
        record(plan)
    return plan

def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--target', required=True)
    p.add_argument('--execute', action='store_true')
    args = p.parse_args()
    fd = None
    try:
        if os.geteuid() != 0:
            raise CleanupError('Run on the IONOS host as root.')
        if args.execute:
            fd = os.open(LOCK, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
        elif LOCK.exists():
            fd = os.open(LOCK, os.O_RDONLY | os.O_NOFOLLOW)
        if fd is not None:
            check_lock(fd)
        print(json.dumps(run_cleanup(args.target, execute=args.execute, lock_fd=fd), indent=2))
    finally:
        if fd is not None:
            os.close(fd)

if __name__ == '__main__':
    try:
        main()
    except (CleanupError, OSError, ValueError, KeyError) as error:
        print('CLEANUP STOP: '+(str(error) if isinstance(error, CleanupError) else type(error).__name__), file=sys.stderr)
        sys.exit(1)
