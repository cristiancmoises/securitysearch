#!/usr/bin/env python3
"""Actual local PHP/HTTP + immutable CSS/asset contracts; no provider requests."""
from pathlib import Path
from html.parser import HTMLParser
import hashlib,json,re,socket,subprocess,tempfile,time,unittest,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[1]
class Page(HTMLParser):
 def __init__(self,text):
  super().__init__();self.links=[];self.images=[];self.scripts=[];self.styles=[];self.current=None;self.feed(text)
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='link':self.links.append(a)
  if tag=='img':self.images.append(a)
  if tag=='script':self.scripts.append(a)
  if tag=='style':self.current=[a,''];self.styles.append(self.current)
 def handle_endtag(self,tag):
  if tag=='style':self.current=None
 def handle_data(self,data):
  if self.current is not None:self.current[1]+=data
class Home(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
  cls.base=f'http://127.0.0.1:{port}';cls.proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','tests/experience-router.php'],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
  for _ in range(80):
   try:urllib.request.urlopen(cls.base,timeout=1).close();return
   except (OSError,TimeoutError):time.sleep(.05)
  cls.proc.terminate();cls.proc.wait(timeout=5);raise RuntimeError('Local PHP server did not start')
 @classmethod
 def tearDownClass(cls):cls.proc.terminate();cls.proc.wait(timeout=5)
 def page(self,theme='Black'):
  req=urllib.request.Request(self.base,headers={'Cookie':'theme='+theme,'User-Agent':'Mozilla/5.0 offline audit'})
  with urllib.request.urlopen(req,timeout=5) as r:return dict(r.headers),r.read().decode()
 def test_default_has_no_blocking_css_requests(self):
  h,text=self.page();p=Page(text)
  self.assertFalse([l for l in p.links if l.get('rel')=='stylesheet'])
  styles={a.get('data-home-style'):css for a,css in p.styles if 'data-home-style'in a}
  self.assertEqual(set(styles),{'base','controls','black'})
  for name,css in styles.items():self.assertGreater(len(css),200);self.assertNotIn('@import',css);self.assertNotIn('</style',css)
  self.assertLess(sum(len(x) for x in styles.values()),10000)
  self.assertIn("script-src 'none'",h['Content-Security-Policy']);self.assertIn("connect-src 'none'",h['Content-Security-Policy'])
  self.assertEqual(p.scripts,[]);self.assertIn('private, no-store',h['Cache-Control'])
 def test_lcp_image_is_early_eager_and_sized(self):
  _,text=self.page();p=Page(text);images=[a for a in p.images if a.get('alt')=='Security Search'];self.assertEqual(len(images),1);img=images[0]
  self.assertEqual((img['width'],img['height'],img['loading'],img['fetchpriority']),('400','86','eager','high'))
  pre=[l for l in p.links if l.get('rel')=='preload'and l.get('as')=='image'];self.assertEqual(len(pre),1);self.assertEqual(pre[0]['href'],img['src'])
  self.assertLess(text.index('rel="preload"'),text.index('<style'));self.assertIn('?v29',img['src'])
  data=(ROOT/'banner/securitysearch.webp').read_bytes();self.assertLessEqual(len(data),6000);self.assertEqual(data[:4],b'RIFF');self.assertEqual(data[8:12],b'WEBP')
 def test_custom_and_image_themes_still_supported(self):
  for theme in ['Custom','Tron','Lain','SecOps','Art','Gentoo']:
   with self.subTest(theme=theme):
    h,text=self.page(theme);p=Page(text)
    self.assertTrue(any('/themes/'+theme+'.css?v29' in l.get('href','')for l in p.links))
    self.assertFalse(any('/static/style.css' in l.get('href','') or '/static/experience.css'in l.get('href','')for l in p.links))
    self.assertEqual(len(p.scripts),1 if theme=='Custom' else 0)
    if theme=='Custom':self.assertIn('local-background.js',p.scripts[0]['src']);self.assertIn('id="background-file"',text)
    self.assertIn('independent third parties, not Security Ops',text)
 def test_generated_css_hashes_and_bounds(self):
  m=json.loads((ROOT/'data/home-css-manifest.json').read_text());self.assertEqual(m['schema'],1)
  for group in ['inputs','outputs']:
   for name,digest in m[group].items():self.assertEqual(hashlib.sha256((ROOT/name).read_bytes()).hexdigest(),digest,name)
  for name in m['outputs']:
   css=(ROOT/name).read_text();self.assertNotIn('@import',css);self.assertNotIn('https://',css);self.assertNotIn('http://',css)
 def test_missing_empty_unsafe_or_linked_css_falls_back(self):
  with tempfile.TemporaryDirectory() as tmp:
   root=Path(tmp);(root/'lib').mkdir();(root/'static').mkdir();(root/'lib/home_styles.php').write_bytes((ROOT/'lib/home_styles.php').read_bytes())
   code='class config{const VERSION=29;}require "lib/home_styles.php";echo home_styles::inline("base");'
   def call():
    p=subprocess.run(['php','-r',code],cwd=root,capture_output=True,text=True,timeout=5);self.assertEqual(p.returncode,0,p.stderr);return p.stdout
   self.assertEqual(call(),'')
   file=root/'static/home-base.css'
   for bad in ['', '</style><script>bad</script>', '@import "https://example.invalid/x.css";', 'x'*24577]:
    file.write_text(bad);self.assertEqual(call(),'')
   file.write_text('body{color:black}');self.assertIn('body{color:black}',call())
   file.unlink();file.symlink_to(ROOT/'static/home-base.css');self.assertEqual(call(),'')
 def test_unknown_css_name_is_not_a_file_path(self):
  code='require "data/config.php";require "lib/home_styles.php";try{home_styles::inline("../../data/config.php");exit(8);}catch(InvalidArgumentException $e){echo "safe";}'
  p=subprocess.run(['php','-r',code],cwd=ROOT,capture_output=True,text=True,timeout=5);self.assertEqual(p.returncode,0,p.stderr);self.assertEqual(p.stdout,'safe')
 def test_no_cross_origin_preconnect_or_defer_hacks(self):
  _,text=self.page();p=Page(text)
  self.assertFalse(any(l.get('rel')in ('preconnect','dns-prefetch')for l in p.links))
  self.assertNotIn('onload=',text);self.assertNotIn('media="print"',text)
 def test_probe_input_is_fixed_and_results_are_not_output(self):
  src=(ROOT/'lib/search_probe.php').read_text();self.assertIn("'s'=>'GNU Guix'",src);self.assertIn("PHP_SAPI!=='cli'",src)
  p=subprocess.run(['php','lib/search_probe.php','https://evil.test','web'],cwd=ROOT,capture_output=True,text=True,timeout=5)
  self.assertEqual(p.returncode,2);self.assertEqual(p.stdout,'')
if __name__=='__main__':unittest.main(verbosity=2)
