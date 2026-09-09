#!/usr/bin/env python3
"""Small reproducible HTTP load check; does not print queries or response bodies."""
import argparse,concurrent.futures,json,statistics,time,urllib.request,urllib.parse
p=argparse.ArgumentParser();p.add_argument('--url',default='http://172.17.0.1:5140/');p.add_argument('--requests',type=int,default=50);p.add_argument('--concurrency',type=int,default=4);a=p.parse_args()
if not 1<=a.requests<=500 or not 1<=a.concurrency<=16:p.error('Use 1–500 requests and 1–16 workers')
if urllib.parse.urlsplit(a.url).scheme not in ('http','https'):p.error('Expected an HTTP(S) URL')
def measure(_):
 t=time.perf_counter()
 try:
  req=urllib.request.Request(a.url,headers={'User-Agent':'Mozilla/5.0 SecuritySearch-performance-check'})
  with urllib.request.urlopen(req,timeout=30) as r:
   data=r.read(4194305)
   ok=r.status==200 and len(data)<=4194304 and b'Search provider unavailable' not in data
   return (time.perf_counter()-t)*1000,ok,len(data)
 except Exception:return (time.perf_counter()-t)*1000,False,0
start=time.perf_counter()
with concurrent.futures.ThreadPoolExecutor(max_workers=a.concurrency) as pool:results=list(pool.map(measure,range(a.requests)))
values=sorted(r[0] for r in results);elapsed=time.perf_counter()-start
print(json.dumps({'requests':a.requests,'concurrency':a.concurrency,'successes':sum(r[1] for r in results),'median_ms':round(statistics.median(values),2),'p95_ms':round(values[max(0,int(len(values)*.95)-1)],2),'requests_per_second':round(a.requests/elapsed,2),'downloaded_bytes':sum(r[2] for r in results),'note':'Includes endpoint/network latency; use the homepage for load tests, never hammer search providers.'},indent=2))
raise SystemExit(0 if all(r[1] for r in results) else 1)
