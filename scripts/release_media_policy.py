#!/usr/bin/env python3
"""Reject operator-only originals/derivatives in new tracked trees or outgoing history.
This is a known-asset policy, not an AI-image detector or a history-rewriting tool.
"""
import argparse, re, subprocess
from pathlib import Path
FORBIDDEN_BLOBS={'fcd2163ef4f77991b0f66af12099bd13e7322b3c','b5e32642d24b7e31bdba2a4c06aa7aad1d41cb9f'}
# Allowed older public palette/Matrix files, NOT derivatives of the excluded originals.
SAFE_PUBLIC = {'static/wallpapers/secops.webp': '1a205eb7d4f76b1827a9ad16fb1c3e8ab00a4c37', 'static/wallpapers/secops-still.webp': 'ad34f8387d90d34425293a056d075977dd9caf4c', 'static/theme-previews/SecOps.webp': '0a8d0ed7695f01d3a16365f38600ebc1f490b52f', 'static/theme-previews/Lain.webp': '9bbcf47a4b4700d3378f866b3cb64491b366869b', 'docs/screenshots/securitysearch-0.9.21-secops.png': '07e6467813393f49caae2bde6422b1b799d75c9c'}

def git(repo,*args):
    p=subprocess.run(['git','-C',str(repo),*args],capture_output=True,timeout=120)
    if p.returncode: raise RuntimeError('Media policy Git inspection failed; no publication authorized.')
    return p.stdout

def forbidden(path,oid):
    if oid in FORBIDDEN_BLOBS: return True
    normalized=path.lower()
    if normalized.startswith('static/operator-themes/'): return True
    if re.search(r'(^|/)(?:[^/]*)(?:lain|secops)[^/]*\.(?:gifv?|webp|png|jpe?g|avif|mp4|webm)$',normalized):
        return SAFE_PUBLIC.get(path)!=oid
    return False

def check_tree(repo,commit='HEAD'):
    for entry in git(repo,'ls-tree','-rz','--full-tree',commit).split(b'\0'):
        if not entry: continue
        meta,name=entry.split(b'\t',1);mode,kind,oid=meta.decode().split();name=name.decode('utf-8')
        if kind=='blob' and forbidden(name,oid):
            raise RuntimeError('Operator-only artwork is tracked: '+name+'. Move it out of the source history before publishing; no force rewrite was attempted.')

def check_history(repo,commit,base):
    commits=git(repo,'rev-list',commit,'^'+base).decode().splitlines()
    if len(commits)>300: raise RuntimeError('Outgoing history exceeds the bounded media review limit (300 commits). Review it explicitly.')
    for revision in commits: check_tree(repo,revision)
    # Detect known originals even if named innocuously in intermediate trees.
    objects=git(repo,'rev-list','--objects','--no-object-names',commit,'^'+base).decode().splitlines()
    if FORBIDDEN_BLOBS.intersection(objects): raise RuntimeError('Outgoing history contains a prohibited original image blob. Publication refused.')

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--repo',type=Path,default=Path('.'));p.add_argument('--base');a=p.parse_args()
    check_tree(a.repo)
    if a.base:check_history(a.repo,'HEAD',a.base)
    print('Known restricted artwork absent from inspected trees; no historical objects were rewritten.')
if __name__=='__main__':main()
