"""Optional actual browser fixture; requires Playwright, Pillow and BeautifulSoup.
Embedded resources deliberately avoid any network navigation and are not an E2E deployment claim.
"""
from pathlib import Path
import argparse, os, tempfile
import base64,hashlib,http.cookiejar,json,mimetypes,re,socket,subprocess,time,urllib.request,urllib.parse,io
from bs4 import BeautifulSoup
from PIL import Image,ImageDraw
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output',type=Path,required=True);args=parser.parse_args();OUT=args.output.expanduser().resolve()
if OUT==ROOT or ROOT in OUT.parents:raise RuntimeError('Keep generated browser evidence outside the source checkout.')
(OUT/'preview').mkdir(parents=True,exist_ok=True);(OUT/'audit').mkdir(exist_ok=True)
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','tests/experience-router.php'],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
def asset(path,base=ROOT):
    parsed=urllib.parse.urlsplit(path)
    if parsed.scheme in ('data','blob'):return path
    if parsed.scheme or parsed.netloc:return 'data:,'
    p=(ROOT/parsed.path.lstrip('/')) if parsed.path.startswith('/') else base/parsed.path
    p=p.resolve()
    if not p.is_relative_to(ROOT) or not p.is_file():return 'data:,'
    mime=mimetypes.guess_type(p.name)[0]or'application/octet-stream'
    return 'data:'+mime+';base64,'+base64.b64encode(p.read_bytes()).decode()
def css_text(p):
    raw=p.read_text()
    return re.sub(r'url\([\s\'"]*([^\)\'\"]+)[\s\'"]*\)',lambda m:'url("'+asset(m[1],p.parent)+'")',raw)
try:
    client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    for i in range(80):
        try:client.open(f'http://127.0.0.1:{port}/',timeout=1).read();break
        except OSError:time.sleep(.05)
    res=client.open(urllib.request.Request(f'http://127.0.0.1:{port}/',data=b'appearance=1&theme=Custom'))
    headers=dict(res.headers);raw=res.read().decode();soup=BeautifulSoup(raw,'html.parser')
    script_bytes=(ROOT/'static/local-background.js').read_text()
    script_hash=base64.b64encode(hashlib.sha256(script_bytes.encode()).digest()).decode()
    for link in soup.find_all('link'):
        if 'stylesheet'in link.get('rel',[]):
            p=ROOT/urllib.parse.urlsplit(link['href']).path.lstrip('/')
            style=soup.new_tag('style');style.string=css_text(p);link.replace_with(style)
        else:link.decompose()
    for img in soup.find_all('img'):
        if img.get('src'):img['src']=asset(img['src'])
    for script in soup.find_all('script'):
        assert script['src'].startswith('/static/local-background.js')
        script.decompose()
    script=soup.new_tag('script');script.string=script_bytes;soup.body.append(script)
    csp=soup.new_tag('meta');csp['http-equiv']='Content-Security-Policy';csp['content']="default-src 'none'; script-src 'sha256-"+script_hash+"'; script-src-attr 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; object-src 'none'; form-action 'none'"
    soup.head.insert(0,csp)
    # Fixture image only; no user's photo is read or embedded.
    im=Image.new('RGB',(640,360),(18,88,128));d=ImageDraw.Draw(im);d.rectangle((320,0,640,360),fill=(145,45,91));d.ellipse((110,70,300,260),fill=(244,197,91))
    samples={}
    for fmt in ('PNG','JPEG','WEBP','GIF'):
        buf=io.BytesIO();im.save(buf,fmt);samples[fmt]=buf.getvalue()
    report={'method':'actual PHP-rendered HTML, bundled resources embedded, inline script allowed by hash in fixture CSP; navigation blocked by managed browser policy; no production navigation claim','checks':[]}
    def check(v,msg):
        assert v,msg;report['checks'].append(msg)
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path=os.environ.get('SECURITYSEARCH_CHROMIUM','/usr/bin/chromium'),headless=True,args=['--no-sandbox'])
        page=browser.new_page(viewport={'width':1280,'height':960});requests=[];errors=[]
        page.on('request',lambda r:requests.append(r.url));page.on('pageerror',lambda e:errors.append(str(e)))
        page.set_content(str(soup),wait_until='load')
        page.wait_for_function('() => !document.getElementById("background-file").disabled')
        check(page.locator('#appearance').get_attribute('open') is not None,'Custom chooser opens automatically')
        check(page.locator('#background-file').is_visible(),'Native file input is visible')
        check(page.eval_on_selector('#background-file','e=>e.form===null && !e.name'),'File outside forms and unnamed')
        check(page.eval_on_selector('#background-file','e=>e.getBoundingClientRect().width>200'),'File input has usable width')
        for fmt in samples:
            # FileChooser event from genuine browser click, then real FileReader/canvas decode.
            with page.expect_file_chooser() as fc:page.locator('#background-file').click()
            fc.value.set_files({'name':'private-fixture.'+fmt.lower(),'mimeType':''if fmt=='PNG'else'image/'+fmt.lower(),'buffer':samples[fmt]})
            page.wait_for_function('() => document.getElementById("background-status").textContent.startsWith("Applied")')
            check(page.eval_on_selector('body','e=>e.classList.contains("local-picture")'),fmt+' byte signature and real native decode')
            check(page.locator('#background-preview').is_visible(),fmt+' local thumbnail displayed')
            check(page.eval_on_selector('#background-preview','e=>e.complete && e.naturalWidth===640'),fmt+' normalized dimensions preserved')
            check(page.eval_on_selector('body','e=>getComputedStyle(e,"::before").backgroundImage.includes("data:image/")'),fmt+' CSS background applied')
            check(page.input_value('#background-file')=='',fmt+' filename field cleared')
        check('page only'in page.inner_text('#background-status'),'Unavailable opaque-origin storage reports page-only instead of losing picture')
        previous=page.eval_on_selector('body','e=>e.style.getPropertyValue("--local-picture")')
        page.locator('#background-file').set_input_files({'name':'fake.png','mimeType':'image/png','buffer':b'not an image'})
        page.wait_for_function('() => document.getElementById("background-status").textContent.startsWith("Choose a real")')
        check(page.eval_on_selector('body','e=>e.style.getPropertyValue("--local-picture")')==previous,'Malformed file preserves previous background')
        page.locator('#background-file').set_input_files({'name':'fixture.png','mimeType':'image/png','buffer':samples['PNG']})
        page.wait_for_function('() => document.getElementById("background-status").textContent.startsWith("Applied")')
        page.locator('#local-background-editor').scroll_into_view_if_needed()
        page.screenshot(path=str(OUT/'preview/local-picture-desktop.png'),full_page=False)
        page.set_viewport_size({'width':390,'height':844});page.locator('#local-background-editor').scroll_into_view_if_needed()
        page.screenshot(path=str(OUT/'preview/local-picture-mobile.png'),full_page=False)
        check(page.eval_on_selector('html','e=>e.scrollWidth')<=390,'Mobile page fits viewport')
        page.locator('#background-remove').click()
        check(not page.eval_on_selector('body','e=>e.classList.contains("local-picture")'),'Remove clears displayed picture')
        check(page.locator('#background-preview').is_hidden(),'Remove clears thumbnail')
        check(not errors,'No unhandled JavaScript errors')
        check(not requests,'No network requests during file selection/decoding/display/removal')
        # No-script rendering really leaves the control disabled with a visible explanation.
        quiet=browser.new_context(java_script_enabled=False,viewport={'width':1024,'height':768});q=quiet.new_page();q.set_content(str(soup))
        check(q.locator('#background-file').is_disabled(),'Without JavaScript, chooser disabled with visible explanation')
        check(q.locator('noscript').inner_text().startswith('JavaScript is disabled'),'No-script instructions visible')
        browser.close()
    report['count']=len(report['checks']);report['outbound_requests']=requests;report['script_errors']=errors
    (OUT/'audit/picture-browser.json').write_text(json.dumps(report,indent=2)+'\n')
    print('PASS:',len(report['checks']),'real browser fixture checks')
finally:proc.terminate();proc.wait(timeout=5)
