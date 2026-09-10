#!/usr/bin/env python3
"""Actual PHP responses/cookies and parsed form ownership; no upstream calls."""
import http.cookiejar,socket,subprocess,time,urllib.request,urllib.parse,urllib.error
from html.parser import HTMLParser
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class Page(HTMLParser):
 def __init__(self,html):
  super().__init__();self.forms=[];self.files=[];self.details=[];self.scripts=[];self.images=[];self.feed(html)
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='form':self.forms.append(a)
  if tag=='input' and a.get('type')=='file':self.files.append((a,list(self.forms)))
  if tag=='details':self.details.append(a)
  if tag=='script':self.scripts.append(a)
  if tag=='img':self.images.append(a)
 def handle_endtag(self,tag):
  if tag=='form' and self.forms:self.forms.pop()
def main():
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 base=f'http://127.0.0.1:{port}';count=0
 def check(v,msg):
  nonlocal count
  assert v,msg;count+=1
 def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def request(c,path='/',data=None):
  req=urllib.request.Request(base+path,headers={'User-Agent':'Mozilla/5.0 offline-check'},data=urllib.parse.urlencode(data).encode()if data is not None else None)
  res=c.open(req,timeout=5);return dict(res.headers),res.read().decode()
 with tempfile_log() as log:
  proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','tests/experience-router.php'],cwd=ROOT,stdout=log,stderr=log)
  try:
   c=client()
   for _ in range(60):
    try:request(c);break
    except OSError:time.sleep(.05)
   h,b=request(c);check('Use a picture from this device'in b,'Visible native entry point')
   check('libre.securityops.co'not in b and 'href="https://redlib.privacyredirect.com' in b,'Active homepage Reddit link and label reflect new primary')
   check("script-src 'none'" in h['Content-Security-Policy'] and not Page(b).scripts,'Default does not execute script')
   h,b=request(c,data={'appearance':'1','theme':'Custom'});doc=Page(b)
   check(any(a.get('id')=='appearance' and 'open'in a for a in doc.details),'Chooser remains open after selection')
   check(len(doc.files)==1 and not doc.files[0][1]and 'name'not in doc.files[0][0],'Local file cannot be serialized into forms')
   check(b.index('id="background-file"')<b.index('class="appearance-form"'),'Chooser before long catalog')
   check('disabled'in doc.files[0][0]and 'optional JavaScript'in b,'Blocked scripts cannot leave silent dead chooser')
   check("script-src 'self'"in h['Content-Security-Policy']and "connect-src 'none'"in h['Content-Security-Policy'],'Scoped Custom CSP')
   check(len(doc.scripts)==1 and doc.scripts[0].get('src','').startswith('/static/local-background.js?v'),'One local script')
   check(any(a.get('id')=='background-preview' for a in doc.images),'Visible local preview target')
   # Prior bug: header was calculated from old cookies before a settings POST.
   fresh=client();h,b=request(fresh,'/settings',{'theme':'Custom'});doc=Page(b)
   check("script-src 'self'"in h['Content-Security-Policy'],'POST immediately permits selected script')
   check(len(doc.files)==1 and not doc.files[0][1],'Settings page supplies editor outside its form')
   check('private, no-store'in h['Cache-Control'],'Picture preference response not publicly cached')
   h,b=request(fresh,'/settings');check(len(Page(b).files)==1,'Settings reload keeps editor')
   h,b=request(c,data={'appearance':'1','theme':'Black'});check(not Page(b).files and not Page(b).scripts,'Switch to native Black removes optional editor/script')
   check("script-src 'none'"in h['Content-Security-Policy'],'Black policy restored')
   js=(ROOT/'static/local-background.js').read_text();check('file.name'not in js and 'document.cookie'not in js,'Controller never transmits filename/cookie data')
   print(f'PASS: {count} actual PHP HTTP/form/CSP checks for browser-local pictures.')
  finally:proc.terminate();proc.wait(timeout=5)
def tempfile_log():
 import tempfile
 return tempfile.TemporaryFile()
if __name__=='__main__':main()
