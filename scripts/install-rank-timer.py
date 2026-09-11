#!/usr/bin/env python3
"""Install the optional daily public Tranco metadata refresh on the IONOS host.

No search, visitor or picture data is sent.  A recognized v1 unit is upgraded
atomically so the anonymous static homepage is rebuilt after a successful public
rank refresh.  Unrecognized/operator-managed units are preserved and refused.
"""
from pathlib import Path
import argparse, os, shutil, subprocess, sys, tempfile

UNIT = 'securitysearch-tranco'
MARKER = '# Managed by SecuritySearch rank-refresh v2\n'
LEGACY_MARKER = '# Managed by SecuritySearch rank-refresh v1\n'

def _v1_unit_files(docker):
    return {
        UNIT+'.service': LEGACY_MARKER + '''[Unit]\nDescription=Refresh public SecuritySearch Tranco metadata (no visitor data)\nAfter=docker.service\nRequires=docker.service\n\n[Service]\nType=oneshot\nExecStart='''+docker+''' exec --user apache --workdir /var/www/html/4get security-search php lib/tranco.php --refresh\nTimeoutStartSec=30\nUMask=0022\nNoNewPrivileges=true\nPrivateTmp=true\nProtectSystem=strict\nProtectHome=true\n\n''',
        UNIT+'.timer': LEGACY_MARKER + '''[Unit]\nDescription=Daily public Tranco metadata refresh for SecuritySearch\n\n[Timer]\nOnCalendar=daily\nRandomizedDelaySec=45m\nPersistent=true\nUnit=securitysearch-tranco.service\n\n[Install]\nWantedBy=timers.target\n'''}

def unit_files(docker):
    if not docker.startswith('/') or any(c.isspace() for c in docker):
        raise RuntimeError('Unsupported Docker executable path.')
    return {
        UNIT+'.service': MARKER + '''[Unit]\nDescription=Refresh public SecuritySearch Tranco metadata and anonymous homepage\nAfter=docker.service\nRequires=docker.service\n\n[Service]\nType=oneshot\nExecStart='''+docker+''' exec --user apache --workdir /var/www/html/4get security-search php lib/tranco.php --refresh\nExecStartPost='''+docker+''' exec --user root --workdir /var/www/html/4get security-search php lib/build_home_snapshot.php --build\nTimeoutStartSec=35\nUMask=0022\nNoNewPrivileges=true\nPrivateTmp=true\nProtectSystem=strict\nProtectHome=true\n\n''',
        UNIT+'.timer': MARKER + '''[Unit]\nDescription=Daily public Tranco metadata refresh for SecuritySearch\n\n[Timer]\nOnCalendar=daily\nRandomizedDelaySec=45m\nPersistent=true\nUnit=securitysearch-tranco.service\n\n[Install]\nWantedBy=timers.target\n'''}

def _atomic_write(path: Path, text: str):
    fd,tmp=tempfile.mkstemp(prefix='.'+path.name+'.',dir=path.parent,text=True)
    try:
        with os.fdopen(fd,'w') as stream: stream.write(text)
        os.chmod(tmp,0o644)
        os.replace(tmp,path)
    finally:
        try: os.unlink(tmp)
        except FileNotFoundError: pass

def install(directory, docker, run=subprocess.run):
    files=unit_files(docker); legacy=_v1_unit_files(docker); actions={}
    # Validate every destination before the first write.
    for name,text in files.items():
        target=directory/name
        if target.is_symlink(): raise RuntimeError('Existing linked unit preserved: '+str(target))
        if not target.exists(): actions[name]='create'; continue
        current=target.read_text()
        if current==text: actions[name]='keep'
        elif current==legacy[name]: actions[name]='upgrade'
        else: raise RuntimeError('Existing different unit preserved: '+str(target))
    for name,text in files.items():
        if actions[name] in ('create','upgrade'): _atomic_write(directory/name,text)
    run(['systemctl','daemon-reload'],check=True)
    run(['systemctl','enable','--now',UNIT+'.timer'],check=True)
    result=run(['systemctl','start',UNIT+'.service'],check=False)
    if result.returncode:
        print('Timer installed; immediate refresh failed. Cached/unavailable state is retained. Inspect journalctl -u '+UNIT+'.service.',file=sys.stderr)
    else:
        print('Daily Tranco refresh enabled; public metadata and anonymous homepage were refreshed.')
    return result.returncode

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--install',action='store_true',required=True)
    parser.parse_args()
    if os.geteuid()!=0 or not Path('/run/systemd/system').is_dir():
        raise RuntimeError('Run on the systemd VPS as root, outside the application container.')
    docker=shutil.which('docker')
    if not docker: raise RuntimeError('Docker executable not found.')
    # Validate the container identity/workdir and both metadata builders before installing units.
    subprocess.run([docker,'exec','--user','apache','--workdir','/var/www/html/4get','security-search','php','-r',
                    'require "data/config.php"; if(config::VERSION<32 || !is_file("lib/tranco.php") || !is_file("lib/build_home_snapshot.php")) exit(2);'],check=True)
    return install(Path('/etc/systemd/system'),docker)

if __name__=='__main__':
    try:sys.exit(main())
    except (RuntimeError,OSError,subprocess.SubprocessError) as e:
        print('Rank timer not fully configured: '+str(e),file=sys.stderr);sys.exit(1)
