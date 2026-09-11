#!/usr/bin/env python3
"""Read-only origin delivery diagnostics for the v0.9.27 deployment, not a benchmark."""
import argparse,datetime,json,os,re,subprocess,sys
from pathlib import Path
HOST='root@securityops.co'
SSH=['ssh','-p','5119','-o','ConnectTimeout=15',HOST]
COMMAND='docker exec -i --user apache --workdir /var/www/html/4get security-search php'
HEADER_KEYS={'content-type','content-encoding','cache-control','vary','server-timing','x-securitysearch-render'}
def clean_report(raw):
 if len(raw)>32768:raise RuntimeError('Report too large.')
 report=json.loads(raw)
 if not isinstance(report,dict) or type(report.get('schema'))is not int or report['schema']!=1:raise RuntimeError('Invalid report schema.')
 rows=report.get('samples')
 if not isinstance(rows,list)or len(rows)!=3:raise RuntimeError('Expected three fixed homepage observations.')
 out=[]
 for i,row in enumerate(rows,1):
  if not isinstance(row,dict)or row.get('sample')!=i or row.get('status')not in ('ok','unavailable'):raise RuntimeError('Invalid sample identity.')
  item={'sample':i,'status':row['status']}
  for key in ['http_status','curl_errno','wire_body_bytes','decoded_body_bytes']:
   n=row.get(key)
   if type(n)is not int or not 0<=n<=131072:raise RuntimeError('Invalid numeric sample.')
   item[key]=n
  for key in ['ttfb_ms','total_ms']:
   n=row.get(key)
   if type(n)not in (float,int)or not 0<=n<=10000:raise RuntimeError('Invalid timing.')
   item[key]=n
  asset=row.get('asset_version')
  if asset is not None and (type(asset)is not int or not 0<=asset<=9999):raise RuntimeError('Invalid asset version.')
  item['asset_version']=asset
  if item['status']=='ok' and (asset!=31 or item['http_status']!=200 or item['curl_errno']!=0):raise RuntimeError('Invalid success claim.')
  headers=row.get('headers')
  if not isinstance(headers,dict):raise RuntimeError('Invalid headers.')
  item['headers']={}
  for key in HEADER_KEYS:
   if key in headers:
    val=headers[key]
    if not isinstance(val,str)or len(val)>300 or re.search(r'[\x00-\x1f\x7f]',val):raise RuntimeError('Unsafe header field.')
    item['headers'][key]=val
  out.append(item)
 return {'schema':1,'scope':'Read-only localhost homepage HTTP inside the application container. Not public network/TLS/NPM or search-result performance.','samples':out}
def main():
 p=argparse.ArgumentParser(description=__doc__);p.parse_args()
 code=Path(__file__).with_suffix('.php')
 if not code.is_file()or code.is_symlink():raise RuntimeError('Keep the paired PHP probe with this script.')
 result=subprocess.run(SSH+[COMMAND],input=code.read_text(),text=True,capture_output=True,timeout=30)
 if result.returncode not in (0,2):raise RuntimeError('SSH/probe failed. No service changes were requested; inspect SSH access and the running container.')
 report=clean_report(result.stdout);report['observed_at_utc']=datetime.datetime.now(datetime.timezone.utc).isoformat();report['remote_probe_exit']=result.returncode
 folder=Path.home()/'Downloads';folder.mkdir(parents=True,exist_ok=True)
 stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S.%fZ')
 target=folder/('securitysearch-delivery-profile-'+stamp+'.json')
 fd=os.open(target,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
 with os.fdopen(fd,'w')as f:json.dump(report,f,indent=2);f.write('\n')
 print(json.dumps(report,indent=2));print('Saved privately to '+str(target))
 return 0 if result.returncode==0 and all(row['status']=='ok'for row in report['samples']) else 2
if __name__=='__main__':
 try:sys.exit(main())
 except (Exception,KeyboardInterrupt)as error:
  print('Stopped: '+(str(error)if isinstance(error,RuntimeError)else type(error).__name__)+'. No container restart or configuration write was requested.',file=sys.stderr);sys.exit(1)
