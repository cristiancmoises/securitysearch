#!/usr/bin/env python3
"""Analyze existing SecOps benchmark results.csv; no network requests or reranking.
Usage: python3 tools/analyze-delivery.py ~/Downloads/securityops-benchmarks/RUN/results.csv
Only allowlisted numeric timing/status columns are reported; URLs/IPs/errors omitted.
"""
import argparse, collections, csv, json, math, pathlib, statistics, sys
SITES=('securityops.co','4get.ca','google.com','yandex.com','bing.com','search.brave.com','duckduckgo.com')
NUMERIC=('dns_ms','tcp_ms','tls_ms','ttfb_ms','total_ms','body_phase_ms')
def number(value):
 try:
  n=float(value)
  return n if math.isfinite(n) and n>=0 else None
 except (TypeError,ValueError):return None
def analyze(path):
 path=pathlib.Path(path)
 if path.is_symlink() or not path.is_file() or path.stat().st_size>16*1024*1024:raise ValueError('Use a regular results.csv of at most 16 MiB.')
 rows=[];seen=set()
 with path.open(newline='',encoding='utf-8-sig') as file:
  reader=csv.DictReader(file)
  if not {'phase','round','site','status','total_ms','ttfb_ms'}<=set(reader.fieldnames or []):raise ValueError('Missing original benchmark columns.')
  for row in reader:
   if len(rows)>=10000:raise ValueError('Too many measurements.')
   if row['phase']!='measured' or row['site'] not in SITES:continue
   try:round_id=int(row['round'])
   except (ValueError,TypeError):raise ValueError('Invalid round.') from None
   if not 1<=round_id<=100000:raise ValueError('Round out of bounds.')
   key=(row['site'],round_id)
   if key in seen:raise ValueError('Duplicate site/round; cannot pair safely.')
   seen.add(key)
   status=row['status'] if row['status'] in ('eligible','skipped') else 'ineligible'
   clean={'site':row['site'],'round':round_id,'status':status}
   for field in (*NUMERIC,'curl_exit','http_code'):clean[field]=number(row.get(field))
   if status=='eligible' and any(clean[k] is None for k in ('ttfb_ms','total_ms')):raise ValueError('Eligible timing is missing.')
   rows.append(clean)
 report={'schema':1,'scope':'Existing HTTP-homepage observations only; no new benchmark, no winner and no search/browser timing.', 'sites':{}, 'paired_securityops_vs_4get':None}
 for site in SITES:
  sr=[r for r in rows if r['site']==site]
  if not sr:continue
  ok=[r for r in sr if r['status']=='eligible'];bad=[r for r in sr if r['status']=='ineligible']
  med={k:statistics.median(v) if (v:=[r[k] for r in ok if r[k] is not None]) else None for k in NUMERIC}
  report['sites'][site]={'eligible':len(ok),'ineligible':len(bad),'skipped':sum(r['status']=='skipped' for r in sr),'median_ms':med,'failed_rounds':[r['round'] for r in bad], 'failure_codes':dict(collections.Counter(str(int(r['curl_exit']))+' / HTTP '+str(int(r['http_code'])) for r in bad if r['curl_exit'] is not None and r['http_code'] is not None))}
 a={r['round']:r for r in rows if r['site']=='securityops.co' and r['status']=='eligible'}
 b={r['round']:r for r in rows if r['site']=='4get.ca' and r['status']=='eligible'}
 common=sorted(a.keys()&b.keys())
 if common:
  report['paired_securityops_vs_4get']={'n':len(common),'difference_of_paired_medians_ms':{k:statistics.median(a[i][k] for i in common)-statistics.median(b[i][k] for i in common) for k in ('ttfb_ms','total_ms')},'descriptive_only':True}
 report['shared_failure_rounds']={str(i):[r['site'] for r in rows if r['round']==i and r['status']=='ineligible'] for i in sorted({r['round'] for r in rows if r['status']=='ineligible'})}
 report['caution']='Coincident failures suggest checking the client/network/run; they do not prove throttling or any particular cause. Missing DNS/TCP/TLS columns remain unknown. Do not compare own-sample medians as paired measurements.'
 return report
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('results_csv');a=p.parse_args()
 try:print(json.dumps(analyze(a.results_csv),indent=2,ensure_ascii=True))
 except (ValueError,OSError,csv.Error) as e:print('STOP: '+str(e),file=sys.stderr);return 1
 return 0
if __name__=='__main__':sys.exit(main())
