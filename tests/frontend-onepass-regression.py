#!/usr/bin/env python3
"""Template replacement and actual PHP response contracts; no public-network benchmark."""
import re,subprocess,unittest,json,hashlib
from pathlib import Path
from importlib.util import spec_from_file_location,module_from_spec
ROOT=Path(__file__).resolve().parents[1]
spec=spec_from_file_location('home_contract',ROOT/'tests/home-performance-regression.py');home=module_from_spec(spec);spec.loader.exec_module(home)
class Rendering(unittest.TestCase):
 def php(self,code):
  p=subprocess.run(['php','-r',code],cwd=ROOT,capture_output=True,text=True,timeout=8)
  self.assertEqual(p.returncode,0,p.stderr);return p.stdout
 def test_placeholder_text_is_not_reinterpreted(self):
  text=self.php('require "data/config.php";require "lib/frontend.php";$f=new frontend();echo $f->load("header.html",["title"=>"Literal {%server_name%}","search"=>"Private A"]);')
  self.assertIn('<title>Literal {%server_name%}</title>',text)
  self.assertIn('Private A',text)
 def test_two_renders_have_no_user_value_leak(self):
  text=self.php('require "data/config.php";require "lib/frontend.php";$f=new frontend();$f->load("header.html",["title"=>"Private first"]);echo $f->load("header.html",["title"=>"Independent second"]);')
  self.assertIn('Independent second',text);self.assertNotIn('Private first',text)
 def test_array_cookie_is_ignored_not_a_type_error(self):
  text=self.php('$_COOKIE["scraper_ac"]=["x"];require "data/config.php";require "lib/frontend.php";echo (new frontend())->load("header.html");')
  self.assertIn('href="/opensearch"',text)
 def test_invalid_replacement_is_rejected(self):
  text=self.php('require "data/config.php";require "lib/frontend.php";try{(new frontend())->load("header.html",["title"=>["x"]]);exit(9);}catch(InvalidArgumentException $e){echo "rejected";}')
  self.assertEqual(text,'rejected')
 def test_compiled_skin_provenance(self):
  manifest=json.loads((ROOT/'data/home-skin-manifest.json').read_text())
  self.assertEqual(hashlib.sha256((ROOT/'static/home-skin.source.css').read_bytes()).hexdigest(),manifest['source_sha256'])
  css=re.search(r'<style data-home-skin>(.*?)</style>',(ROOT/'template/home.html').read_text(),re.S)[1]
  self.assertEqual(hashlib.sha256(css.encode()).hexdigest(),manifest['css_sha256'])
  self.assertLess(len(css.encode()),manifest['raw_bytes']);self.assertNotIn('@import',css)
class Timing(home.Home):
 # Reuse actual server fixture, but run only this additional method here.
 def test_origin_only_timing_is_query_free_and_private(self):
  h,text=self.page();self.assertRegex(h.get('Server-Timing',''),r'^app;dur=[0-9]+\.[0-9]{2}$')
  self.assertIn('private, no-store',h['Cache-Control']);self.assertIn('In Code We Trust',text)
# Do not rerun inherited suites in this additional command.
if __name__=='__main__':
 suite=unittest.TestSuite([unittest.defaultTestLoader.loadTestsFromTestCase(Rendering),Timing('test_origin_only_timing_is_query_free_and_private')])
 result=unittest.TextTestRunner(verbosity=2).run(suite);raise SystemExit(0 if result.wasSuccessful() else 1)
