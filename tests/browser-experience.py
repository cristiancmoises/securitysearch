#!/usr/bin/env python3
"""Optional real-Chromium tests of actual local PHP/CSS/JS, never upstream services.
Requires Python Playwright and Chromium. Not silently skipped by the VPS PHP gate.
"""
import argparse, json, os, socket, subprocess, tempfile, time, urllib.request
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--output',type=Path,required=True);a=p.parse_args();out=a.output.resolve();out.mkdir(parents=True,exist_ok=True);results=[]
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 base=f'http://127.0.0.1:{port}'
 log=(out/'php-browser.log').open('w')
 server=subprocess.Popen(['php','-d','display_errors=0','-S',f'127.0.0.1:{port}','tests/experience-router.php'],cwd=ROOT,stdout=log,stderr=log)
 def ok(value,label):
  assert value,label
  results.append(label);print('PASS:',label,flush=True)
 try:
  for _ in range(80):
   try:urllib.request.urlopen(base,timeout=.5);break
   except OSError:time.sleep(.05)
  with sync_playwright() as pw:
   browser=pw.chromium.launch(executable_path='/usr/bin/chromium',headless=True,args=['--no-sandbox'])
   ctx=browser.new_context(viewport={'width':1365,'height':1000},user_agent='Mozilla/5.0 SecuritySearch local browser validation')
   external=[];errors=[];requests=[];responses=[]
   def route(r):
    if r.request.url.startswith(base+'/') or r.request.url.startswith(('data:','blob:')):r.continue_()
    else:external.append(r.request.url);r.abort()
   ctx.route('**/*',route);page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
   page.on('request',lambda req:requests.append((req.method,req.url,req.post_data)))
   page.on('response',lambda resp:responses.append((resp.status,resp.url)))
   resp=page.goto(base,wait_until='networkidle')
   ok(resp.status==200 and "script-src 'none'" in resp.headers['content-security-policy'],'Default homepage CSP disables scripts')
   ok(page.locator('script').count()==0,'Black homepage emits no script')
   ok(page.get_by_text('Works without JavaScript',exact=False).count()>0,'Truthful JavaScript statement')
   ok(page.locator('.footer-onion').get_attribute('href').endswith('.onion/'),'Onion link from existing source')
   ok('unavailable' in page.locator('.footer-rank').inner_text().lower(),'No invented Tranco rank')
   page.screenshot(path=str(out/'home-black.png'),full_page=True)
   page.locator('#appearance summary').click();page.locator('#appearance').scroll_into_view_if_needed();page.wait_for_timeout(250)
   previews=page.locator('.appearance-choice img')
   # Lazy images load when each item enters the viewport.
   for i in range(previews.count()):previews.nth(i).scroll_into_view_if_needed()
   page.wait_for_timeout(300)
   ok(previews.count()==18 and previews.evaluate_all('(imgs)=>imgs.every(i=>i.complete&&i.naturalWidth>0)'),'All eighteen preview thumbnails render')
   names=page.locator('.appearance-choice input').evaluate_all('(xs)=>xs.map(x=>x.value)')
   ok(not set(['Dark','Wine','The Birthday Massacre','gentoo'])&set(names),'Removed and duplicate themes absent')
   page.locator('#appearance').screenshot(path=str(out/'theme-picker.png'))
   page.locator('input[name=theme][value=Custom]').check();page.get_by_role('button',name='Save appearance',exact=True).click();page.wait_for_load_state('networkidle')
   ok(page.locator('script[src*="local-background"]').count()==1,'Custom explicitly loads one local-picture script')
   custom_headers=page.request.get(base).headers
   ok("script-src 'self'" in custom_headers['content-security-policy'] and "connect-src 'none'" in custom_headers['content-security-policy'],'Custom permits script but denies network connections')
   page.locator('#appearance summary').click();fileinput=page.locator('#background-file')
   ok(fileinput.evaluate('(e)=>e.form===null&&!e.hasAttribute("name")'),'Picture input is outside forms and unnamed')
   before=len(requests);fileinput.set_input_files(ROOT/'static/theme-previews/Art.webp');page.wait_for_function('document.body.classList.contains("local-picture")');page.wait_for_timeout(250)
   ok(not any(m!='GET' for m,u,b in requests[before:]),'Picture selection sends no upload request')
   ok(page.evaluate('sessionStorage.getItem("securitysearch.background.v1")!==null && localStorage.getItem("securitysearch.background.v1")===null'),'Picture defaults to tab session only')
   page.locator('#background-remember').check()
   ok(page.evaluate('localStorage.getItem("securitysearch.background.v1")!==null && sessionStorage.getItem("securitysearch.background.v1")===null'),'Remember checkbox explicitly moves data to local storage')
   page.screenshot(path=str(out/'custom-picture.png'),full_page=True)
   page.reload(wait_until='networkidle');ok(page.locator('body').evaluate('(e)=>e.classList.contains("local-picture")'),'Local picture restored without upload')
   page.locator('#appearance summary').click();page.locator('#background-remove').click()
   ok(page.evaluate('sessionStorage.getItem("securitysearch.background.v1")===null && localStorage.getItem("securitysearch.background.v1")===null'),'Remove clears both stores')
   for theme in ['Tron','SecOps']:
    page.locator(f'input[name=theme][value={theme}]').check();page.get_by_role('button',name='Save appearance',exact=True).click();page.wait_for_load_state('networkidle')
    wallpaper=page.locator('.ambient-wallpaper').evaluate('(e)=>getComputedStyle(e).backgroundImage')
    ok(theme.lower() in wallpaper and '.webp' in wallpaper,theme+' animation selected without JavaScript')
    page.screenshot(path=str(out/theme.lower()+'.png'),full_page=True)
    page.locator('#appearance summary').click()
   page.goto(base+'/settings',wait_until='networkidle')
   settings=page.request.get(base+'/settings');ok(settings.headers.get('x-robots-tag')=='noindex, nofollow','Settings noindex/nofollow')
   s=page.locator('select').first;ok(s.evaluate('(e)=>getComputedStyle(e).backgroundColor')=='rgb(0, 0, 0)','Native settings selects black')
   page.goto(base+'/fixture-images',wait_until='networkidle');page.wait_for_timeout(250)
   ok(page.locator('#images img[data-motion]').count()==8,'Actual PHP image renderer provides motion candidates')
   ok(page.locator('#images img[data-motion]').evaluate_all('(xs)=>xs.every(x=>x.src.includes("s=poster"))'),'Motion keeps every poster URL unchanged')
   playing=page.locator('.motion-layer:not([hidden])');ok(0<playing.count()<=4,'Browser animates at most four visible overlays')
   b=page.locator('.motion-toggle[aria-pressed=true]').first;b.click();ok(b.get_attribute('aria-pressed')=='false','Accessible pause works')
   page.emulate_media(reduced_motion='reduce');page.wait_for_timeout(100);ok(page.locator('.motion-layer').count()==0,'Reduced motion removes animation layers')
   ok(page.locator('select').first.evaluate('(e)=>getComputedStyle(e).backgroundColor')=='rgb(0, 0, 0)','Image filter background is black')
   colors=page.locator('select').first.evaluate('(e)=>[getComputedStyle(e).color,getComputedStyle(e).getPropertyValue("--8ec07c")]')
   ok(colors[0]=='rgb(51, 242, 255)','Image filter text inherits cyan theme accent')
   page.emulate_media(reduced_motion='no-preference');page.screenshot(path=str(out/'image-previews.png'),full_page=True)
   ctx.clear_cookies();page.set_viewport_size({'width':390,'height':844});page.goto(base,wait_until='networkidle');page.locator('#appearance summary').click()
   ok(page.evaluate('document.documentElement.scrollWidth<=innerWidth'),'Mobile layout has no horizontal overflow')
   page.screenshot(path=str(out/'mobile-picker.png'),full_page=True)
   ok(not external,'No third-party browser requests during local appearance/image tests')
   ok(not errors,'No uncaught browser JavaScript errors')
   ok(not [(c,u)for c,u in responses if c>=400],'No missing local assets or HTTP errors')
   browser.close()
 finally:
  server.terminate();server.wait(timeout=5);log.close();(out/'browser-results.json').write_text(json.dumps({'passed':len(results),'checks':results},indent=2)+'\n')
if __name__=='__main__':main()
