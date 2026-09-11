#!/usr/bin/env python3
"""Real localhost PHP headers/forms with fixed renderer fixture, no external API."""
from pathlib import Path
import json,socket,subprocess,tempfile,time,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[1]
def main():
 with socket.socket()as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 base=f'http://127.0.0.1:{port}';checks=[]
 with tempfile.TemporaryFile()as log:
  proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','tests/experience-router.php'],cwd=ROOT,stdout=log,stderr=log)
  def req(path='/',cookie=None,data=None,headers=None):
   h={'User-Agent':'Mozilla/5.0 Local fixture',**(headers or {})};
   if cookie:h['Cookie']=cookie
   request=urllib.request.Request(base+path,headers=h,data=data)
   try:r=urllib.request.urlopen(request,timeout=5)
   except urllib.error.HTTPError as e:r=e
   return r.status,dict(r.headers),r.read().decode('utf-8',errors='replace')
  def ok(v,label):assert v,label;checks.append(label)
  try:
   for _ in range(80):
    try:req();break
    except OSError:time.sleep(.05)
   code,h,b=req();ok(code==200 and "script-src 'none'"in h['Content-Security-Policy']and '<script'not in b,'Black emits no scripts')
   ok('Works without JavaScript'in b and 'No JavaScript'not in b,'Truthful statement')
   ok('/sitemap"'in b and 'social-card.png'in b,'Sitemap and social metadata')
   for theme in ['Tron','SecOps','Gentoo','Art','Lain','Stop','Dark','Wine','The Birthday Massacre']:
    c,h,b=req(cookie='theme='+theme);expected='Black'if theme in ['Dark','Wine','The Birthday Massacre']else theme
    ok(c==200 and ('data-home-style="black"'in b if expected=='Black' else '/themes/'+expected+'.css?v34'in b) and '<script'not in b,'Native theme '+theme)
   c,h,b=req(cookie='theme=Custom');ok(c==200 and "script-src 'self'"in h['Content-Security-Policy']and "connect-src 'none'"in h['Content-Security-Policy'],'Custom CSP')
   ok(b.count('local-background.js')==1 and 'id="background-file"'in b and 'name="background-file"'not in b,'One local script and unnamed input')
   ok(b.index('id="background-file"')<b.index('class="appearance-form"'),'File picker outside form')
   c,h,b=req('/settings',cookie='theme=Custom');ok(c==200 and h['X-Robots-Tag']=='noindex, nofollow'and 'private, no-store'in h['Cache-Control'],'Settings private/noindex')
   ok('value="Tron"'in b and 'value="Custom"'in b and 'value="Dark"'not in b,'Settings whitelist')
   c,h,b=req(data=b'appearance=1&theme=Tron',headers={'Content-Type':'application/x-www-form-urlencoded','Sec-Fetch-Site':'cross-site'});ok(c==403,'Cross-site theme change blocked')
   c,h,b=req('/fixture-images',cookie='theme=Tron');ok(c==200 and 'data-motion='in b and 'images-motion.js?v34'in b,'Actual image renderer / fixture transport')
   ok('experience.css?v34'in b and "connect-src 'self'"in h['Content-Security-Policy'],'Image CSS and scoped policy')
   print('PASS:',len(checks),'actual localhost PHP checks: '+', '.join(checks))
  finally:proc.terminate();proc.wait(timeout=5)
if __name__=='__main__':main()
