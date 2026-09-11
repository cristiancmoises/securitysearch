#!/usr/bin/env python3
"""Actual local homepage HTTP/cookie tests; no external network or PHP extensions."""
import http.cookiejar
from pathlib import Path
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
ROOT=Path(__file__).resolve().parents[1]
def main():
 s=socket.socket();s.bind(('127.0.0.1',0));port=s.getsockname()[1];s.close();base='http://127.0.0.1:'+str(port)
 proc=subprocess.Popen(['php','-S','127.0.0.1:'+str(port),'tests/ui-router.php'],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 try:
  for _ in range(40):
   try:urllib.request.urlopen(base,timeout=1);break
   except (urllib.error.URLError,TimeoutError):time.sleep(.1)
  jar=http.cookiejar.CookieJar();client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
  response=client.open(base);html=response.read().decode()
  assert response.status==200 and  'data-home-style="black"' in html and '/static/themes/Black.css' not in html
  assert "script-src 'none'" in response.headers['Content-Security-Policy']
  assert response.headers['Cache-Control']=='private, no-store'
  assert '<script' not in html and '{%theme_picker%}' not in html
  response=client.open(urllib.request.Request(base+'/',data=urllib.parse.urlencode({'appearance':'1','theme':'Art'}).encode()))
  assert '/static/themes/Art.css?v30' in response.read().decode()
  assert any(c.name=='theme' and c.value=='Art' and c.has_nonstandard_attr('HttpOnly') and c.get_nonstandard_attr('SameSite')=='Lax' for c in jar)
  for form,headers,expected in [({'appearance':'1','theme':'../../etc/passwd'},{},400),({'appearance':'1','theme':'Black'},{'Sec-Fetch-Site':'cross-site'},403),({'appearance':'1','theme[]':'Art'},{},400)]:
   try:client.open(urllib.request.Request(base+'/',data=urllib.parse.urlencode(form).encode(),headers=headers));raise AssertionError('Invalid appearance accepted')
   except urllib.error.HTTPError as e:assert e.code==expected
  print('PASS: 9 actual HTTP theme, cookie, CSP, cache and hostile-input checks.')
 finally:proc.terminate();proc.wait(timeout=5)
if __name__=='__main__':main()
