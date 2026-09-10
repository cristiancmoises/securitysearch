#!/usr/bin/env python3
"""Install the optional daily public Tranco metadata refresh on the IONOS host.

No search, visitor or picture data is sent. Existing different units are refused.
The application does not depend on this timer; refresh failures retain the cache.
"""
from pathlib import Path
import argparse, os, shutil, subprocess, sys

UNIT = 'securitysearch-tranco'
MARKER = '# Managed by SecuritySearch rank-refresh v1\n'

def unit_files(docker):
    if not docker.startswith('/') or any(c.isspace() for c in docker):
        raise RuntimeError('Unsupported Docker executable path.')
    return {
        UNIT+'.service': MARKER + '''[Unit]
Description=Refresh public SecuritySearch Tranco metadata (no visitor data)
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
ExecStart='''+docker+''' exec --user apache --workdir /var/www/html/4get security-search php lib/tranco.php --refresh
TimeoutStartSec=30
UMask=0022
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true

''',
        UNIT+'.timer': MARKER + '''[Unit]
Description=Daily public Tranco metadata refresh for SecuritySearch

[Timer]
OnCalendar=daily
RandomizedDelaySec=45m
Persistent=true
Unit=securitysearch-tranco.service

[Install]
WantedBy=timers.target
'''}

def install(directory, docker, run=subprocess.run):
    files=unit_files(docker)
    # Check every destination before the first write.
    for name,text in files.items():
        target=directory/name
        if target.is_symlink() or (target.exists() and target.read_text()!=text):
            raise RuntimeError('Existing different unit preserved: '+str(target))
    for name,text in files.items():
        target=directory/name
        if not target.exists():
            with target.open('x') as stream: stream.write(text)
            target.chmod(0o644)
    run(['systemctl','daemon-reload'],check=True)
    run(['systemctl','enable','--now',UNIT+'.timer'],check=True)
    result=run(['systemctl','start',UNIT+'.service'],check=False)
    if result.returncode:
        print('Timer installed; immediate refresh failed. Cached/unavailable state is retained. Inspect journalctl -u '+UNIT+'.service.',file=sys.stderr)
    else:
        print('Daily Tranco refresh enabled and initial refresh completed.')
    return result.returncode

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--install',action='store_true',required=True)
    parser.parse_args()
    if os.geteuid()!=0 or not Path('/run/systemd/system').is_dir():
        raise RuntimeError('Run on the systemd VPS as root, outside the application container.')
    docker=shutil.which('docker')
    if not docker: raise RuntimeError('Docker executable not found.')
    # Validate the container identity/workdir and metadata code before installing units.
    subprocess.run([docker,'exec','--user','apache','--workdir','/var/www/html/4get','security-search','php','-r',
                    'require "data/config.php"; if(config::VERSION<25 || !is_file("lib/tranco.php")) exit(2);'],check=True)
    return install(Path('/etc/systemd/system'),docker)

if __name__=='__main__':
    try:sys.exit(main())
    except (RuntimeError,OSError,subprocess.SubprocessError) as e:
        print('Rank timer not fully configured: '+str(e),file=sys.stderr);sys.exit(1)
