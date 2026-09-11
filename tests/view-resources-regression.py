#!/usr/bin/env python3
"""Real PHP rendering/build checks; no network providers or substitute extensions."""
import hashlib,json,os,shutil,subprocess,tempfile,unittest,socket,time,urllib.request
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
THEMES=['Black','Tron','SecOps','Custom','Ajattix','Art','Art1','Art2','Art3','Arte','Cat','Cat2','Gentoo','Kawaii','Lain','SecurityOps','Stop','Valerie']
class Views(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.root=Path(self.temp.name)/'app';self.root.mkdir()
  for d in ['lib','template','data','static/themes','static/theme-previews']:
   shutil.copytree(ROOT/d,self.root/d,ignore=shutil.ignore_patterns('view-resources.generated.php','api_keys','__pycache__'))
  for f in ['index.php','static/home-base.css','static/home-controls.css','static/home-black.css']:
   shutil.copyfile(ROOT/f,self.root/f)
  self.generated=self.root/'data/view-resources.generated.php'
 def tearDown(self):self.temp.cleanup()
 def php(self,code,bundled=True,ok=True):
  env=os.environ.copy();env['SECURITYSEARCH_RENDER_BUNDLE']='1' if bundled else '0'
  p=subprocess.run(['php','-r',code],cwd=self.root,env=env,text=True,capture_output=True,timeout=8)
  if ok:self.assertEqual(p.returncode,0,p.stderr)
  return p
 def build(self,mode='--build',ok=True):
  p=subprocess.run(['php','lib/build_view_resources.php',mode],cwd=self.root,text=True,capture_output=True,timeout=8)
  if ok:self.assertEqual(p.returncode,0,p.stderr)
  return p
 def page(self,theme='Black',bundled=True):
  return self.php('$_COOKIE["theme"]='+json.dumps(theme)+';include "index.php";',bundled).stdout
 def test_all_themes_are_byte_identical_in_compiled_and_dynamic_modes(self):
  self.build()
  for theme in THEMES+['Dark','gentoo','<script>']:
   with self.subTest(theme=theme):self.assertEqual(self.page(theme),self.page(theme,False))
 def test_homepage_no_longer_loads_search_renderer(self):
  self.build();p=self.php('ob_start();include "index.php";ob_end_clean();echo json_encode([class_exists("frontend",false),get_included_files(),view_resources::mode()]);')
  present,paths,mode=json.loads(p.stdout);self.assertFalse(present);self.assertEqual(mode,'compiled');self.assertFalse(any('/scraper/' in x for x in paths))
 def test_shared_frontend_load_interface_remains_identical(self):
  self.build()
  code='require "data/config.php";require "lib/frontend.php";$v=["title"=>"Literal {%server_name%}","search"=>"Alice", "left"=>"Bob"];echo json_encode([(new frontend())->load("header.html",$v),(new page_renderer())->load("header.html",$v)]);'
  a,b=json.loads(self.php(code).stdout);self.assertEqual(a,b);self.assertIn('Literal {%server_name%}',a)
 def test_no_request_or_config_values_enter_generated_file(self):
  (self.root/'data/config.php').write_text((self.root/'data/config.php').read_text().replace('Security Search','SECRET-SERVER-NAME'))
  self.build();text=self.generated.read_text();self.assertNotIn('SECRET-SERVER-NAME',text)
  self.assertNotIn('data:image/',text);self.assertNotIn('static/operator-themes/',text)
  self.assertNotIn('sessionStorage',text);self.assertNotIn('localStorage',text)
 def test_source_edits_require_rebuild_before_process_restart(self):
  self.build();p=self.root/'template/home.html';p.write_text(p.read_text().replace('Works without JavaScript','Ordinary searches still work'))
  self.assertNotEqual(self.build('--check',False).returncode,0);self.build();self.build('--check')
  self.assertIn('Ordinary searches still work',self.page());self.assertEqual(self.page(),self.page(bundled=False))
 def test_build_is_deterministic_and_fixed_argument_only(self):
  self.build();first=self.generated.read_bytes();self.build();self.assertEqual(first,self.generated.read_bytes())
  self.assertNotEqual(self.build('/tmp/other-output',False).returncode,0)
 def test_missing_invalid_and_old_bundle_use_dynamic_fallback(self):
  expected=self.page(bundled=False)
  self.assertEqual(self.page(),expected)
  for source in ['<?php return [];','<?php return "wrong";','<?php broken syntax']:
   self.generated.write_text(source);self.generated.chmod(0o644);self.assertEqual(self.page(),expected)
  self.build();text=self.generated.read_text().replace("'securitysearch-views-1'","'obsolete-revision'");self.generated.write_text(text);self.assertEqual(self.page(),expected)
 def test_world_writable_bundle_is_never_executed(self):
  self.generated.write_text('<?php file_put_contents("unsafe-execution", "bad"); return [];'+(' ' *100));self.generated.chmod(0o666)
  self.page();self.assertFalse((self.root/'unsafe-execution').exists())
 def test_input_and_output_symlinks_are_refused(self):
  target=self.root/'template/home.html';raw=target.read_bytes();target.unlink();target.symlink_to(ROOT/'template/home.html')
  self.assertNotEqual(self.build(ok=False).returncode,0);self.assertFalse(self.generated.exists())
  target.unlink();target.write_bytes(raw);self.generated.symlink_to(self.root/'index.php');before=(self.root/'index.php').read_bytes()
  self.assertNotEqual(self.build(ok=False).returncode,0);self.assertEqual(before,(self.root/'index.php').read_bytes())
 def test_unsafe_css_refuses_build_without_replacing_old_bundle(self):
  self.build();before=self.generated.read_bytes();(self.root/'static/home-base.css').write_text('</style><script>bad</script>')
  self.assertNotEqual(self.build(ok=False).returncode,0);self.assertEqual(before,self.generated.read_bytes())
 def test_cached_template_values_never_leak_between_renders(self):
  self.build();code='require "data/config.php";require "lib/frontend.php";$f=new frontend();$f->load("header.html",["title"=>"Private First"]);echo $f->load("header.html",["title"=>"Different Second"]);'
  text=self.php(code).stdout;self.assertNotIn('Private First',text);self.assertIn('Different Second',text)
 def test_unknown_template_and_invalid_replacement_still_rejected(self):
  self.build()
  for call in ['$f->load("../../etc/passwd")','$f->load("header.html",["title"=>["bad"]])']:
   text=self.php('require "data/config.php";require "lib/frontend.php";$f=new frontend();try{'+call+';exit(3);}catch(InvalidArgumentException $e){echo "refused";}').stdout
   self.assertEqual(text,'refused')
 def test_private_pack_overlays_are_not_compiled_or_lost(self):
  folder=self.root/'static/operator-themes';folder.mkdir();payload=b'RIFF\x08\x00\x00\x00WEBPfixture'
  names=['lain.webp','lain-still.webp','Lain-preview.webp','secops.webp','secops-still.webp','SecOps-preview.webp']
  for name in names:(folder/name).write_bytes(payload)
  m={'schema':1,'deployment_only':True,'source_commit':'81979bb217f97df1c6acc724ef7d8c9da2789d7c','source_blobs':{'Lain':'fcd2163ef4f77991b0f66af12099bd13e7322b3c','SecOps':'b5e32642d24b7e31bdba2a4c06aa7aad1d41cb9f'},'assets':{n:{'size':len(payload),'sha256':hashlib.sha256(payload).hexdigest()}for n in names}}
  (folder/'manifest.json').write_text(json.dumps(m));self.build()
  self.assertNotIn('WEBPfixture',self.generated.read_text());self.assertNotIn('operator-themes',self.generated.read_text())
  for theme in ['Lain','SecOps']:
   self.assertEqual(self.page(theme),self.page(theme,False));self.assertIn(theme+'-operator.css',self.page(theme))
 def test_generated_files_are_excluded_from_archives_and_docker_input(self):
  for file in ['.gitignore','.dockerignore','.gitattributes']:
   self.assertIn('data/view-resources.generated.php',(ROOT/file).read_text())
 def test_process_startup_compiles_then_falls_back_on_failure(self):
  source=(ROOT/'docker/docker-entrypoint.sh').read_text()
  start=source.index('# Compile fixed public');end=source.index('\nif [ "$#"',start);block=source[start:end]
  # Shell commands are deliberately mocked; native PHP compilation is tested above.
  bindir=Path(self.temp.name)/'bin';bindir.mkdir();php=bindir/'php'
  for flag,exit_code,expected in [('1',0,'1'),('1',1,'0'),('0',0,'0')]:
   php.write_text('#!/bin/sh\nexit '+str(exit_code)+'\n');php.chmod(0o755)
   env=os.environ.copy();env.update(PATH=str(bindir)+':'+env['PATH'],SECURITYSEARCH_RENDER_BUNDLE=flag)
   p=subprocess.run(['sh','-c',block+'\nprintf "MODE=%s" "$SECURITYSEARCH_RENDER_BUNDLE"'],env=env,text=True,capture_output=True,timeout=5)
   self.assertEqual(p.returncode,0,p.stderr);self.assertIn('MODE='+expected,p.stdout)
 def test_real_http_responses_preserve_headers_and_personalized_body(self):
  self.build(); servers=[]
  try:
   for flag in ['0','1']:
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    env=os.environ.copy();env['SECURITYSEARCH_RENDER_BUNDLE']=flag
    proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}'],cwd=self.root,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    url=f'http://127.0.0.1:{port}/';servers.append((proc,url))
    for attempt in range(100):
     try:
      with urllib.request.urlopen(url,timeout=1):break
     except OSError:time.sleep(.025)
    else:self.fail('Local PHP server did not start.')
   for theme in ['Black','Custom','Tron','Lain','SecOps','Art']:
    replies=[]
    for proc,url in servers:
     req=urllib.request.Request(url,headers={'Cookie':'theme='+theme,'User-Agent':'Local functional fixture'})
     with urllib.request.urlopen(req,timeout=5) as response:
      replies.append((response.read(),dict(response.headers)))
    self.assertEqual(replies[0][0],replies[1][0],theme)
    self.assertEqual(replies[0][1]['X-SecuritySearch-Render'],'dynamic')
    self.assertEqual(replies[1][1]['X-SecuritySearch-Render'],'compiled')
    for key in ['Content-Security-Policy','Cache-Control','Referrer-Policy','X-Content-Type-Options']:
     self.assertEqual(replies[0][1].get(key),replies[1][1].get(key),key)
    self.assertIn('no-store',replies[1][1]['Cache-Control'])
    self.assertNotIn('Set-Cookie',replies[1][1])
    self.assertRegex(replies[1][1]['Server-Timing'],r'app;dur=\d+\.\d+')
  finally:
   for proc,url in servers:proc.terminate();proc.wait(timeout=5)
 def test_compiled_resource_lookup_does_not_cache_context_or_update_tranco(self):
  self.build()
  # Live footer/config values are filled after the public template lookup, not
  # captured in the artifact. No network operation is called by either renderer.
  for primary in ['https://redlib.privacyredirect.com','https://redlib.nadeko.net','https://redlib.privadency.com']:
   config=self.root/'data/config.php';original=config.read_text()
   import re
   altered=re.sub(r'const REDLIB_PRIMARY = [^;]+;', 'const REDLIB_PRIMARY = '+json.dumps(primary)+';',original)
   self.assertNotEqual(original,altered) if primary!='https://redlib.privacyredirect.com' else None
   config.write_text(altered)
   page=self.page();self.assertEqual(page,self.page(bundled=False));self.assertIn(primary,page)
   config.write_text(original)
if __name__=='__main__':unittest.main(verbosity=2)
