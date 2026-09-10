#!/usr/bin/env python3
"""Read-only check of the notice actually served inside the deployed container."""
import argparse
import re
import subprocess
import sys

MARKER = 'data-attribution-revision="redlib-attribution-1"'
SHORT = 'External Redlib instances are operated by independent third parties, not Security Ops.'
PATHS = ('/', '/about', '/settings')

def verify(container='security-search'):
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]{0,127}', container):
        raise RuntimeError('Invalid container identifier.')
    for path in PATHS:
        response=subprocess.run(['docker','exec',container,'curl','--disable','--fail','--silent',
            '--show-error','--connect-timeout','3','--max-time','8','--noproxy','*',
            '--proto','=http','http://127.0.0.1'+path],capture_output=True,timeout=12)
        if response.returncode:
            raise RuntimeError('Cannot verify the deployed attribution on '+path+'. Deployment may already be complete; inspect its printed rollback information.')
        if len(response.stdout)>512*1024:
            raise RuntimeError('Unexpectedly large local page while verifying '+path+'.')
        body=response.stdout.decode('utf-8',errors='replace')
        if MARKER not in body or SHORT not in body or '{%redlib_' in body:
            raise RuntimeError('The expected Redlib ownership notice is not served on '+path+'. Keep the backup and inspect the running source; do not force-push or delete it.')
        if path=='/about' and 'id="external-redlib"' not in body:
            raise RuntimeError('The deployed external-provider explanation is missing.')
    print('Verified deployed Redlib ownership notice on /, /about and /settings. No provider search was sent.')

if __name__=='__main__':
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('--container',default='security-search');a=p.parse_args()
    try:verify(a.container)
    except (RuntimeError,subprocess.TimeoutExpired,FileNotFoundError) as e:
        print('Attribution verification stopped: '+(str(e) if isinstance(e,RuntimeError) else type(e).__name__),file=sys.stderr);sys.exit(1)
