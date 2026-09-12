#!/usr/bin/env python3
"""Native PHP card renderer over localhost; synthetic results, no provider requests."""
import json, os, shutil, socket, subprocess, tempfile, time, unittest
import urllib.error, urllib.parse, urllib.request
from html.parser import HTMLParser
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class Cards(HTMLParser):
    def __init__(self,html):
        super().__init__();self.images=[];self.links=[];self.tags=[];self.feed(html)
    def handle_starttag(self,tag,attrs):
        self.tags.append(tag)
        if tag=='img':self.images.append(dict(attrs))
        if tag=='a':self.links.append(dict(attrs))
def target(url):return urllib.parse.parse_qs(urllib.parse.urlsplit(url).query)['i'][0]
def source(name,w=None,h=None):return dict(url=f'https://example.invalid/{name}.png',width=w,height=h)
class NativeCards(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        if not shutil.which('php'):raise RuntimeError('Native PHP required; absence is not a pass')
        cls.tmp=tempfile.TemporaryDirectory(prefix='securitysearch-cards-');cls.directory=Path(cls.tmp.name)
        root=json.dumps(str(ROOT))
        (cls.directory/'router.php').write_text('''<?php
$root=ROOT_PATH;
require $root.'/data/config.php';require $root.'/lib/frontend.php';require $root.'/lib/image_results.php';
header('Cache-Control: private, no-store');
if ($_SERVER['REQUEST_METHOD']==='GET') { echo 'fixture-ready';exit; }
$body=file_get_contents('php://input',false,null,0,65537);
if(strlen($body)>65536){http_response_code(413);exit;}
$request=json_decode($body,true,64,JSON_THROW_ON_ERROR);
$results=image_results::bounded($request['results']);$f=new frontend();$get=$request['get']??[];
if(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/items'){
 header('Content-Type: application/json');
 echo json_encode(['items'=>image_results::items($f,$get,$results),'npt'=>$results['npt'],'omitted'=>$results['omitted']],JSON_THROW_ON_ERROR);
}else{
 header('Content-Type: text/html; charset=UTF-8');
 [$html,$count]=image_results::render($f,$get,$results);echo '<!doctype html><meta charset="UTF-8">'.$html;
}
'''.replace('ROOT_PATH',root))
        with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        cls.base=f'http://127.0.0.1:{port}'
        cls.log=open(cls.directory/'php.log','w+b')
        cls.proc=subprocess.Popen(['php','-d','display_errors=1','-d','disable_functions=curl_init,curl_exec,curl_multi_init,fsockopen,pfsockopen,stream_socket_client,socket_create','-S',f'127.0.0.1:{port}','router.php'],cwd=cls.directory,stdout=cls.log,stderr=cls.log)
        cls.client=urllib.request.build_opener(urllib.request.ProxyHandler({}))
        try:
            for _ in range(100):
                if cls.proc.poll() is not None:raise RuntimeError('Native PHP fixture stopped')
                try:
                    with cls.client.open(cls.base,timeout=.5) as r:
                        if r.read()==b'fixture-ready':break
                except OSError:time.sleep(.03)
            else:raise RuntimeError('Native PHP fixture did not become ready')
        except BaseException:cls.tearDownClass();raise
    @classmethod
    def tearDownClass(cls):
        cls.proc.terminate()
        try:cls.proc.wait(timeout=5)
        except subprocess.TimeoutExpired:cls.proc.kill();cls.proc.wait()
        cls.log.close();cls.tmp.cleanup()
    def request(self,path,rows,get=None):
        body=json.dumps({'results':{'image':rows,'npt':'opaque-fixture-cursor'},'get':get or {}}).encode()
        with self.client.open(urllib.request.Request(self.base+path,data=body,headers={'Content-Type':'application/json'}),timeout=5) as response:
            self.assertEqual(response.status,200)
            self.assertEqual(response.headers['Cache-Control'],'private, no-store')
            return response.read().decode()
    def test_html_and_json_agree_for_all_quality_modes(self):
        rows=[dict(source=[source('original',1600,1200),source('preview',320,240),source('pixel',1,1)])]
        for quality in ['preview','high','original']:
            with self.subTest(quality=quality):
                item=json.loads(self.request('/items',rows,dict(quality=quality)))['items'][0]
                html=Cards(self.request('/cards',rows,dict(quality=quality)))
                self.assertEqual(len(html.images),1);img=html.images[0]
                self.assertEqual(img['src'],item['preview']);self.assertEqual([int(img['width']),int(img['height'])],[item['width'],item['height']])
                self.assertEqual(target(img['src']),source('preview' if quality=='preview' else 'original')['url'])
    def test_animation_keeps_poster_and_separate_motion(self):
        rows=[dict(source=[source('original',1600,1200),source('preview',320,240),source('pixel',1,1)],motion_url='https://example.invalid/full.gif')]
        page=Cards(self.request('/cards',rows));img=page.images[0]
        self.assertEqual(target(img['src']),source('preview')['url']);self.assertIn('s=poster',img['src'])
        self.assertEqual(target(img['data-motion']),'https://example.invalid/full.gif');self.assertIn('preview=1',img['data-motion'])
        self.assertNotIn('script',page.tags)
    def test_untrusted_titles_and_urls_are_not_executable(self):
        rows=[dict(title='<script>alert(1)</script>" onerror="boom',url='javascript:boom',source=[source('original',1000,800),source('preview',320,240),dict(url='javascript:boom')])]
        page=Cards(self.request('/cards',rows));self.assertNotIn('script',page.tags)
        self.assertEqual(len(page.images),1);self.assertNotIn('onerror',page.images[0])
        for link in page.links:self.assertFalse(link.get('href','').startswith('javascript:'))
    def test_page_limit_cursor_and_priority(self):
        rows=[dict(source=[source('original',1600,1200),source('preview',320,240)])]*40
        data=json.loads(self.request('/items',rows));page=Cards(self.request('/cards',rows))
        self.assertEqual((len(data['items']),len(page.images),data['omitted'],data['npt']),(24,24,16,'opaque-fixture-cursor'))
        self.assertEqual(sum(i['loading']=='eager' for i in page.images),4)
        self.assertEqual(sum(i['fetchpriority']=='high' for i in page.images),1)
    def test_only_tiny_alternative_falls_back(self):
        rows=[dict(source=[source('original',1600,1200),source('pixel',1,1)])]
        page=Cards(self.request('/cards',rows));self.assertEqual(target(page.images[0]['src']),source('original')['url'])
    def test_bounded_source_scan(self):
        rows=[dict(source=[source('original',1600,1200)]+[source('pixel',1,1)]*31+[source('preview',320,240)])]
        page=Cards(self.request('/cards',rows));self.assertEqual(target(page.images[0]['src']),source('original')['url'])
    def test_no_provider_requests_needed(self):
        # URLs under .invalid are never fetched: pure metadata in both outputs.
        rows=[dict(source=[source('original',1600,1200),source('preview',320,240)])]
        self.assertEqual(len(json.loads(self.request('/items',rows))['items']),1)
        self.assertEqual(len(Cards(self.request('/cards',rows)).images),1)
if __name__=='__main__':unittest.main(verbosity=2)
