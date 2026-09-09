#!/usr/bin/env python3
"""Explicit, low-rate live matrix; does not call another provider on failure.
Run on the Docker host: python3 scripts/provider-audit.py --all
Neutral search 'teste' is sent once for each configured provider/page pair.
Reports counts, status and elapsed time; never result URLs, tokens or keys.
"""
import argparse
import datetime
import json
from pathlib import Path
import re
import subprocess
import sys
import time
APP='/var/www/html/4get'
def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--all',action='store_true',required=True)
    p.add_argument('--container',default='security-search')
    p.add_argument('--output',type=Path,default=Path('provider-audit.json'))
    a=p.parse_args()
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*',a.container): p.error('Invalid container name')
    dest='/tmp/securitysearch-provider-probe.php'
    subprocess.run(['docker','cp',str(Path(__file__).with_name('provider-probe.php')),a.container+':'+dest],check=True)
    base=['docker','exec','--workdir',APP,a.container,'timeout','35','php','-d','apc.enable_cli=1',dest]
    inv=subprocess.run(base+['--inventory'],capture_output=True,text=True,timeout=40,check=True)
    pairs=json.loads(inv.stdout);rows=[]
    report={'date_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'source':'live VPS direct adapters; no automatic fallback','query':'teste','results':rows}
    for pair in pairs:
        before=time.monotonic()
        try:
            result=subprocess.run(base+[pair['provider'],pair['page'],'teste','1'],capture_output=True,text=True,timeout=40)
            row=json.loads(result.stdout) if result.returncode==0 else dict(pair,status='unavailable',reason='process_failure')
        except (subprocess.TimeoutExpired,ValueError): row=dict(pair,status='unavailable',reason='timeout_or_invalid_output')
        row['observed_ms']=round((time.monotonic()-before)*1000,1);rows.append(row)
        a.output.write_text(json.dumps(report,indent=2)+'\n')
        print(pair['page']+'/'+pair['provider']+': '+row['status']+' ('+str(row['observed_ms'])+' ms)',flush=True)
        time.sleep(1)
    print('Report: '+str(a.output.resolve()))
    return 0 if all(row['status']=='ok' for row in rows) else 2
if __name__=='__main__':
    try: sys.exit(main())
    except Exception as e: print('Audit stopped: '+type(e).__name__,file=sys.stderr);sys.exit(1)
