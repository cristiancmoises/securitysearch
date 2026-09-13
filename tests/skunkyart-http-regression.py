#!/usr/bin/env python3
"""Actual localhost PHP HTTP/card rendering, with synthetic SkunkyArt JSON, not live provider acceptance."""
import json,socket,subprocess,tempfile,time,unittest,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class HTTP(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.tmp=tempfile.TemporaryDirectory();cls.dir=Path(cls.tmp.name)
  s=socket.socket();s.bind(('127.0.0.1',0));cls.port=s.getsockname()[1];s.close()
  php='''<?php
chdir(ROOT_PATH);require 'data/config.php';require 'lib/frontend.php';require 'lib/image_results.php';require 'scraper/skunkyart.php';
$p=(new ReflectionClass('skunkyart'))->newInstanceWithoutConstructor();
$row=['title'=>($_GET['large']??'')==='1'?str_repeat('&',100000):'<img src=x onerror=alert(1)>','original_url'=>'https://www.deviantart.com/artist/art/Work-1','full'=>'/media?t=ZnVsbA.c2ln','preview'=>'/media?t=cHJldmlldw.c2ln','width'=>1600,'height'=>1200,'mature'=>($_GET['mature']??'')==='1'];
if(($_GET['invalid']??'')==='1')$row['full']=$row['preview']='https://evil.invalid/a';
try{$r=$p->decode(json_encode(['items'=>[$row],'has_more'=>false]),false);[$html,$count]=image_results::render(new frontend(),[], $r);header('Content-Type: text/html; charset=utf-8');header('X-Fixture-Count: '.$count);echo $html;}catch(Throwable $e){http_response_code(503);header('Cache-Control: no-store');echo 'Fixture unavailable';}
'''.replace('ROOT_PATH',json.dumps(str(ROOT)))
  (cls.dir/'index.php').write_text(php)
  cls.proc=subprocess.Popen(['php','-S',f'127.0.0.1:{cls.port}','-t',str(cls.dir)],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
  for _ in range(100):
   try:urllib.request.urlopen(f'http://127.0.0.1:{cls.port}/',timeout=.2).close();break
   except OSError:time.sleep(.03)
  else:cls.proc.terminate();cls.proc.wait();cls.tmp.cleanup();raise RuntimeError('Local PHP server unavailable')
 @classmethod
 def tearDownClass(cls):cls.proc.terminate();cls.proc.wait(timeout=5);cls.tmp.cleanup()
 def get(self,q=''):
  try:r=urllib.request.urlopen(f'http://127.0.0.1:{self.port}/?'+q,timeout=3)
  except urllib.error.HTTPError as e:r=e
  with r:return r.status,r.headers,r.read().decode()
 def test_nonempty_card(self):s,h,b=self.get();self.assertEqual(s,200);self.assertEqual(h['X-Fixture-Count'],'1');self.assertIn('image-wrapper',b)
 def test_metadata_cannot_inject(self):_,_,b=self.get();self.assertNotIn('<img src=x',b);self.assertIn('&lt;img',b)
 def test_images_use_existing_proxy(self):_,_,b=self.get();self.assertIn('proxy?',b);self.assertNotIn('src="https://skunkyart',b)
 def test_original_artwork_link(self):_,_,b=self.get();self.assertIn('https://www.deviantart.com/artist/art/Work-1',b)
 def test_large_title_bounded(self):_,_,b=self.get('large=1');self.assertLess(len(b.encode()),20000)
 def test_safe_search_omits_mature(self):s,h,b=self.get('mature=1');self.assertEqual(s,200);self.assertEqual(h['X-Fixture-Count'],'0');self.assertNotIn('image-wrapper',b)
 def test_malformed_media_is_failure(self):s,h,b=self.get('invalid=1');self.assertEqual(s,503);self.assertEqual(h['Cache-Control'],'no-store');self.assertNotIn('evil.invalid',b)
 def test_native_shortcut_has_no_script_dependency(self):
  t=(ROOT/'template/search-actions.html').read_text();self.assertIn('value="skunkyart"',t);self.assertIn('aria-label="Search DeviantArt"',t);self.assertNotIn('<script',t)
if __name__=='__main__':unittest.main(verbosity=2)
