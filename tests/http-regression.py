#!/usr/bin/env python3
"""Real localhost HTTP controller tests with an isolated, offline provider fixture.
Copies production PHP/controllers into a temporary tree; never modifies a live scraper.
Requires PHP curl/DOM/mbstring/APCu/sodium. No browser or external network requests.
"""
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import shutil
import signal
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT=Path(__file__).resolve().parents[1]
class Links(HTMLParser):
    def __init__(self,html):
        super().__init__();self.next=None;self.feed(html)
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='a' and attrs.get('class') in ['nextpage img','nextpage']:self.next=attrs['href']


def main():
    with tempfile.TemporaryDirectory(prefix='securitysearch-http-') as temp:
        root=Path(temp)
        for folder in ['lib','template','oracles']:
            shutil.copytree(ROOT/folder,root/folder)
        for folder in ['static','banner']:
            (root/folder).symlink_to(ROOT/folder,target_is_directory=True)
        (root/'data').mkdir();shutil.copy2(ROOT/'data/config.php',root/'data/config.php')
        for file in ['images.php','index.php','settings.php','news.php','web.php']:
            shutil.copy2(ROOT/file,root/file)
        (root/'scraper').mkdir()
        (root/'scraper/google.php').write_text('''<?php
require_once 'lib/backend.php';
class google {
 public function getfilters($page) { return ['nsfw'=>['display'=>'Safe search','option'=>['yes'=>'Yes','no'=>'No']],'newer'=>['display'=>'After','option'=>'_DATE'],'format'=>['display'=>'Format','option'=>['any'=>'Any','gif'=>'GIF']]]; }
 public function web($get) { throw new RuntimeException('Google unavailable'); }
 public function image($get) {
  if (str_starts_with($get['s'],'fallback')) throw new RuntimeException('Google unavailable');
  apcu_inc('fixture-calls');$b=new backend('google');$page=1;
  if (!empty($get['npt'])) { $state=json_decode($b->get($get['npt'],'images')[0],true);$page=$state['page'];$get=$state['get']; }
  if ($get['s']==='fixture failure' && $page>=3) { throw new RuntimeException('Google temporarily rate-limited this instance.'); }
  $images=[['title'=>'Fixture page '.$page,'url'=>'https://example.org/source','source'=>[['url'=>'https://example.org/a.gif','width'=>400,'height'=>300]]]];
  if ($get['s']==='fixture empty' && $page===2) {$images=[];}
  $next=$get['s']==='fixture final' && $page===2 ? null : $b->store(json_encode(['page'=>$page+1,'get'=>$get]),'images','raw_ip::::');
  return ['image'=>$images,'npt'=>$next];
 }
}
''')
        (root/'scraper/brave.php').write_text('''<?php
class brave {
 public function getfilters($page){return ['nsfw'=>['display'=>'Safe search','option'=>['yes'=>'Yes','no'=>'No']],'format'=>['display'=>'Format','option'=>['any'=>'Any','gif'=>'GIF']]];}
 public function image($get){if($get['s']==='fallback failure')throw new RuntimeException('Brave unavailable');return ['npt'=>null,'image'=>[['title'=>'Brave fallback result','url'=>'https://example.org/source','source'=>[['url'=>'https://example.org/a.gif','width'=>400,'height'=>300]]]]];}
 public function web($get){return ['status'=>'ok','npt'=>null,'web'=>[['title'=>'Brave web fallback','url'=>'https://example.org/source','description'=>'A neutral result','date'=>null,'thumb'=>['url'=>null,'ratio'=>null],'sublink'=>[],'table'=>[]]],'spelling'=>['type'=>'no_correction'],'answer'=>[],'image'=>[],'video'=>[],'news'=>[],'related'=>[]];}
}
''')
        source=(ROOT/'scraper/reddit.php').read_text().replace('class reddit extends','class reddit_adapter extends')
        (root/'scraper/reddit.php').write_text(source+'''
class reddit extends reddit_adapter {
 protected function fetch_path(string $path,array $params): string {
  if (($params['q'] ?? '')==='failure') return '<div id="error">Blocked</div>';
  return file_get_contents('redlib-fixture.html');
 }
}
''')
        shutil.copy2(ROOT/'tests/fixtures/redlib-news.html',root/'redlib-fixture.html')
        # Isolated media controller with fixed local bytes; no resolver/transport bypass in production.
        media=root/'media';(media/'lib').mkdir(parents=True);(media/'data').mkdir()
        shutil.copy2(ROOT/'proxy.php',media/'proxy.php');shutil.copy2(ROOT/'data/config.php',media/'data/config.php')
        for file in ['animated_preview.php','image_poster.php','security_headers_minimal.php']:
            shutil.copy2(ROOT/'lib'/file,media/'lib'/file)
        shutil.copytree(ROOT/'tests/fixtures/motion',media/'fixtures')
        (media/'lib/curlproxy.php').write_text('''<?php
class proxy {
 const req_image=1;
 public function __construct($resize=true){}
 public function get($url,$type,$headers=true,$referer=null,$redirect=0,$max=0,$budget=null,$accept=null){
  $file=basename(parse_url($url,PHP_URL_PATH));
  if (!in_array($file,['two.gif','two.webp','two.png','static.webp'],true)) throw new RuntimeException('Unknown fixture');
  return ['body'=>file_get_contents('fixtures/'.$file),'headers'=>[]];
 }
 public function getfilenameheader($headers,$url,$type=null){}
 public function do404(){http_response_code(404);exit;}
}
''')
        (root/'router.php').write_text('''<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/fixture-reset') {apcu_clear_cache();apcu_add('fixture-calls',0);echo 'reset';return true;}
if ($path==='/fixture-stats') {header('Content-Type: application/json');echo json_encode(['calls'=>apcu_fetch('fixture-calls')]);return true;}
if ($path==='/') {require 'index.php';return true;}
if ($path==='/images') {require 'images.php';return true;}
if ($path==='/news') {require 'news.php';return true;}
if ($path==='/web') {require 'web.php';return true;}
if ($path==='/proxy-fixture') {chdir('media');require 'proxy.php';return true;}
if ($path==='/settings') {require 'settings.php';return true;}
http_response_code(404);return true;
''')
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        env=dict(os.environ,PHP_CLI_SERVER_WORKERS='4')
        with (root/'server.log').open('w+') as log:
            server=subprocess.Popen(['php','-d','apc.enable_cli=1','-d','display_errors=0','-d','log_errors=1','-d','error_log=/dev/stderr','-d','error_reporting=-1','-S',f'127.0.0.1:{port}','router.php'],cwd=root,env=env,stdout=log,stderr=log,start_new_session=True)
            base=f'http://127.0.0.1:{port}'
            def request(path, cookie=None, raw=False):
                req=urllib.request.Request(base+path,headers={'User-Agent':'Mozilla/5.0 SecuritySearch-local-tests', **({'Cookie':cookie} if cookie else {})})
                try: response=urllib.request.urlopen(req,timeout=8)
                except urllib.error.HTTPError as error:response=error
                with response:return response.status,response.headers,response.read() if raw else response.read().decode()
            def reset():request('/fixture-reset')
            def calls():return json.loads(request('/fixture-stats')[2])['calls']
            def search(query='GNU Guix', view='filmstrip', cookie=None):
                code,headers,html=request('/images?s='+urllib.parse.quote(query)+'&view='+view+'&quality=high&format=gif&newer=2025-01-01',cookie)
                assert code==200 and 'Refresh' not in headers and 'Automatic pages' not in html
                return headers,html,Links(html).next
            def append(url):
                code,headers,body=request(url+'&append=1')
                assert code==200 and headers['Content-Type'].startswith('application/json') and 'Refresh' not in headers
                assert headers['Cache-Control']=='private, no-store'
                return json.loads(body)
            try:
                for _ in range(100):
                    try:
                        if request('/')[0]==200:break
                    except OSError:time.sleep(.05)
                else:raise RuntimeError('Local PHP server did not start')
                reset();code,headers,html=request('/')
                assert "script-src 'none'" in headers['Content-Security-Policy'] and '<script' not in html.lower()
                assert all(label in html for label in ['Search Image','Search Pinterest','Search YouTube','In Code We Trust.'])
                assert '/static/themes/Tron.css?v23' in html and html.count('class="search-action-icon"')==4
                code,headers,html=request('/settings')
                assert code==200 and 'Load more images while scrolling' in html and 'name="image_infinite"' in html
                assert "script-src 'none'" in headers['Content-Security-Policy']
                for view in ['grid','compact','gallery','feed','list','filmstrip']:
                    headers,html,url=search(view=view)
                    assert 'images-view-'+view in html and '/static/images-infinite.js?v23' in html
                    assert "script-src 'self'" in headers['Content-Security-Policy'] and "connect-src 'self'" in headers['Content-Security-Policy']
                    params=urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)
                    assert params['view']==[view] and params['quality']==['high'] and params['format']==['gif'] and params['newer']==['2025-01-01']
                headers,html,url=search(cookie='image_infinite=no; image_motion=no; theme=Lain')
                assert '<script' not in html and url and '/static/themes/Lain.css?v23' in html
                reset();headers,html,url=search()
                for number in range(2,16):
                    data=append(url)
                    assert data['version']==1 and data['items'][0]['title']=='Fixture page '+str(number)
                    assert data['items'][0]['preview'].startswith('/proxy?')
                    url=data['next']
                assert calls()==15
                # Native pagination remains usable from the latest continuation.
                code,headers,html=request(url)
                assert code==200 and 'Fixture page 16' in html and 'Refresh' not in headers
                print('PASS: HTTP six views, compact actions, Tron/saved theme, preference fallback, JSON appends past ten pages and retained query/format/date/quality.')
                for query in ['fixture empty','fixture final']:
                    reset();_,_,url=search(query);data=append(url)
                    assert (data['next'] is not None)==(query=='fixture empty')
                    if query=='fixture empty':assert data['items']==[]
                reset();_,_,url=search('fixture failure');data=append(url)
                code,headers,html=request(data['next']+'&append=1')
                assert code==503 and headers['Retry-After']=='30' and 'Refresh' not in headers
                assert 'Google is temporarily unavailable' in html and 'Try Brave' in html and 'Took ' not in html and '<script' not in html
                before=calls();code,headers,html=request('/images?frame=invalid&flow_action=play')
                assert code==410 and 'Refresh' not in headers and calls()==before
                reset();code,headers,html=request('/images?append=1')
                assert code==200 and json.loads(html)['items']==[] and calls()==0
                code,headers,html=request('/images?s=GNU+Guix&flow_start=1&seconds=10')
                assert code==200 and 'Refresh' not in headers and 'Automatic pages' not in html
                print('PASS: HTTP empty/final JSON, provider 503 recovery, stale-frame 410 without provider request, blank search and obsolete timer parameters.')
                headers,html,url=search('GNU Guix',cookie='image_infinite=no')
                assert '/static/images-motion.js?' in html and '/static/images-infinite.js?' not in html and 'data-motion=' in html
                headers,html,url=search('GNU Guix',cookie='image_motion=no')
                assert '/static/images-motion.js?' not in html and '/static/images-infinite.js?' in html
                headers,html,url=search('fallback success')
                assert 'Results from Brave' in html and 'data-provider="brave"' in html and 'Brave fallback result' in html and 'Search paused' not in html
                assert html.count('<!DOCTYPE html>')==1 and 's=poster' in html and 'data-motion=' in html
                code,headers,html=request('/web?s=GNU+Guix')
                assert code==200 and 'Results from Brave' in html and 'Brave web fallback' in html and 'Search paused' not in html, (code,html[-2000:])
                code,headers,html=request('/images?s=fallback+failure')
                assert code==503 and 'Google and its Brave fallback' in html
                code,headers,html=request('/news')
                assert code==200 and 'Latest posts' in html and 'Reddit via Redlib' in html and 'GNU Guix' in html and '<b></b>' not in html and 'A&amp;amp;B' not in html
                assert '<script' not in html and "script-src 'none'" in headers['Content-Security-Policy']
                more=Links(html).next;assert more and 'scraper=reddit' in more
                code,headers,html=request('/'+more.lstrip('/'))
                assert code==200 and 'Reddit via Redlib' in html
                code,headers,html=request('/news?s=GNU+Guix')
                assert code==200 and 'Related posts' in html and 'libre.securityops.co/r/news/comments' in html
                code,headers,html=request('/news?s=failure')
                assert code==503 and 'Took ' not in html
                for file,mime in [('two.gif','image/gif'),('two.webp','image/webp'),('two.png','image/png')]:
                    path='/proxy-fixture?i=https%3A%2F%2Fexample.org%2F'+file
                    code,headers,body=request(path+'&s=animated&preview=1',raw=True)
                    assert code==200 and headers['Content-Type']==mime and body==(ROOT/'tests/fixtures/motion'/file).read_bytes(), (file,code,str(headers),body[:50])
                    code,headers,body=request(path+'&s=poster',raw=True)
                    assert code==200 and headers['Content-Type']=='image/jpeg' and body.startswith(b'\xff\xd8'),file
                code,headers,body=request('/proxy-fixture?i=https%3A%2F%2Fexample.org%2Fstatic.webp&s=animated&preview=1',raw=True)
                assert code==422 and body==b''
                print('PASS: HTTP independent motion/infinite preferences, Google-to-Brave web/image header recovery, Reddit feed/search/pagination/errors and byte-preserved animations/static posters.')
                reset()
                subprocess.run(['python3',str(ROOT/'scripts/benchmark-http.py'),'--url',base+'/','--requests','50','--concurrency','4'],check=True)
                log.flush();log.seek(0);errors=log.read()
                assert not any(marker in errors for marker in ['PHP Warning','PHP Fatal','PHP Deprecated','PHP Parse error']), 'PHP runtime diagnostic in controller tests'
            finally:
                log.flush();log.seek(0)
                diagnostics=[line for line in log.read().splitlines() if any(marker in line for marker in ['PHP Warning','PHP Fatal','PHP Deprecated','PHP Parse error'])]
                if diagnostics:print('\n'.join(diagnostics))
                os.killpg(server.pid,signal.SIGTERM)
                server.wait(timeout=10)
if __name__=='__main__':main()
