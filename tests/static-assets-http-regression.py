#!/usr/bin/env python3
"""Native Apache event/gzip tests on Alpine or Debian; no Docker/network doubles.
Only an isolated loopback listener is used. No PHP-FPM, production port or NPM.
"""
import gzip, http.client, os, pathlib, pwd, shutil, socket, subprocess, tempfile, time, unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
class HTTPAssets(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.server=shutil.which('httpd') or shutil.which('apache2')
  if not cls.server:raise RuntimeError('Native Apache required; do not treat dependency absence as success.')
  cls.tmp=tempfile.TemporaryDirectory();cls.base=pathlib.Path(cls.tmp.name);cls.base.chmod(0o755);cls.root=cls.base/'www';(cls.root/'static/themes').mkdir(parents=True)
  cls.css=b'/* public fixture */\nbody{color:cyan;background:black}\n'*1000;cls.js=b'console.log("fixture");\n'*1000
  (cls.root/'static/app.css').write_bytes(cls.css);(cls.root/'static/app.js').write_bytes(cls.js)
  code='require $argv[1];securitysearch_build_static_assets($argv[2]);'
  subprocess.run(['php','-r',code,str(ROOT/'lib/build_static_assets.php'),str(cls.root/'static')],check=True,capture_output=True,timeout=20)
  # The deploy audit inherits umask 077; the fixture still needs public read access.
  for item in (cls.root,*cls.root.rglob('*')):item.chmod(0o755 if item.is_dir() else 0o644)
  cls.home=b'<!doctype html><html><body>Public homepage fixture</body></html>'
  (cls.root/'home-anonymous.generated.fast').write_bytes(cls.home);(cls.root/'home-anonymous.generated.fast.gz').write_bytes(gzip.compress(cls.home,mtime=0))
  for name in ('home-anonymous.generated.fast','home-anonymous.generated.fast.gz'):(cls.root/name).chmod(0o644)
  with socket.socket() as s:s.bind(('127.0.0.1',0));cls.port=s.getsockname()[1]
  builtin=subprocess.check_output([cls.server,'-l'],text=True)
  modules=['mpm_event','authz_core','unixd','dir','mime','rewrite','headers','env','setenvif','filter','deflate','expires']
  moddirs=[pathlib.Path('/usr/lib/apache2/modules'),pathlib.Path('/usr/lib/apache2')]
  conf=f'ServerRoot "{cls.base}"\nListen 127.0.0.1:{cls.port}\nPidFile "{cls.base}/httpd.pid"\nServerName localhost\nErrorLog "{cls.base}/error.log"\nLogLevel warn\nFileETag None\n'
  if os.geteuid()==0:
   user=next((n for n in ('apache','www-data','nobody') if cls._user(n)),None)
   if not user:raise RuntimeError('Unprivileged Apache user unavailable')
   u=pwd.getpwnam(user);conf+=f'User #{u.pw_uid}\nGroup #{u.pw_gid}\n'
  for module in modules:
   if 'mod_'+module+'.c' in builtin:continue
   path=next((d/('mod_'+module+'.so') for d in moddirs if (d/('mod_'+module+'.so')).is_file()),None)
   if path is None:raise RuntimeError('Apache module unavailable: '+module)
   conf+=f'LoadModule {module}_module "{path}"\n'
  mime=next((p for p in ('/etc/mime.types','/etc/apache2/mime.types') if pathlib.Path(p).is_file()),None)
  conf+=f'TypesConfig "{mime}"\nDocumentRoot "{cls.root}"\n<Directory "{cls.root}">\nRequire all granted\nOptions FollowSymLinks\nAllowOverride None\n'
  home=(ROOT/'docker/apache/http/httpd.conf').read_text();conf+=home[home.index('    RewriteEngine On'):home.index('    # Strip .php')]+'\n</Directory>\n'
  conf+=(ROOT/'docker/apache/fast-home.conf').read_text()+'\n'
  conf+=(ROOT/'docker/apache/precompressed-assets.conf').read_text().replace('/var/www/html/4get',str(cls.root))+'\n'
  conf+='AddOutputFilterByType DEFLATE text/html text/css application/javascript\nExpiresActive On\nExpiresByType text/html "access plus 0 seconds"\n'
  cls.cfg=cls.base/'httpd.conf';cls.cfg.write_text(conf);subprocess.run([cls.server,'-t','-f',str(cls.cfg)],check=True,capture_output=True,timeout=10)
  env=os.environ.copy();env['SECURITYSEARCH_STATIC_HOME']='1';cls.log=(cls.base/'startup.log').open('wb')
  cls.proc=subprocess.Popen([cls.server,'-f',str(cls.cfg),'-DFOREGROUND'],stdout=cls.log,stderr=cls.log,env=env)
  for _ in range(50):
   if cls.proc.poll() is not None:raise RuntimeError('Apache failed: '+(cls.base/'startup.log').read_text())
   try:
    with socket.create_connection(('127.0.0.1',cls.port),.1):break
   except OSError:time.sleep(.05)
  else:raise RuntimeError('Native Apache startup timeout')
 @staticmethod
 def _user(name):
  try:pwd.getpwnam(name);return True
  except KeyError:return False
 @classmethod
 def tearDownClass(cls):
  if hasattr(cls,'proc'):
   cls.proc.terminate()
   try:cls.proc.wait(timeout=5)
   except subprocess.TimeoutExpired:cls.proc.kill();cls.proc.wait(timeout=3)
  if hasattr(cls,'log'):cls.log.close()
  cls.tmp.cleanup()
 def request(self,path='/static/app.css',ae='gzip',method='GET',extra=None):
  c=http.client.HTTPConnection('127.0.0.1',self.port,timeout=3)
  try:
   headers={'Accept-Encoding':ae};headers.update(extra or {});c.request(method,path,headers=headers);r=c.getresponse();return r.status,{k.lower():v for k,v in r.getheaders()},r.read()
  finally:c.close()
 def test_css_exact_bytes_and_policy(self):
  st,h,b=self.request();self.assertEqual(st,200);self.assertEqual(h.get('x-securitysearch-asset'),'gzip-static');self.assertEqual(h.get('content-encoding'),'gzip');self.assertEqual(gzip.decompress(b),self.css);self.assertEqual(h['content-type'],'text/css');self.assertIn('accept-encoding',h['vary'].lower());self.assertIn('immutable',h['cache-control'])
 def test_js_exact_bytes_and_type(self):
  st,h,b=self.request('/static/app.js?v39');self.assertEqual(st,200);self.assertEqual(gzip.decompress(b),self.js);self.assertEqual(h['content-type'],'application/javascript')
 def test_positive_quality_values(self):
  for ae in ('gzip;q=1','br, gzip;q=0.8','GZIP ; q=0.001','gzip;q=1.000'):
   with self.subTest(ae=ae):
    st,h,b=self.request(ae=ae);self.assertEqual(st,200);self.assertEqual(h.get('x-securitysearch-asset'),'gzip-static');self.assertEqual(gzip.decompress(b),self.css)
 def test_explicit_refusal(self):
  for ae in ('identity','gzip;q=0','gzip;q=0.000','gzip;q=0, gzip;q=1'):
   with self.subTest(ae=ae):
    st,h,b=self.request(ae=ae);self.assertEqual(st,200);self.assertNotIn('x-securitysearch-asset',h)
    if ae!='gzip;q=0, gzip;q=1':self.assertNotIn('content-encoding',h);self.assertEqual(b,self.css)
 def test_missing_sidecar_falls_back(self):
  path=self.root/'static/app.css.pre.gz';raw=path.read_bytes();path.unlink()
  try:
   st,h,b=self.request();self.assertEqual(st,200);self.assertNotIn('x-securitysearch-asset',h);self.assertEqual(gzip.decompress(b) if h.get('content-encoding')=='gzip' else b,self.css)
  finally:path.write_bytes(raw);path.chmod(0o644)
 def test_sidecar_not_public(self):
  for path in ('/static/app.css.pre.gz','/static/app.js.pre.gz?x=1'):
   self.assertEqual(self.request(path)[0],404)
 def test_head_semantics(self):
  st,h,b=self.request(method='HEAD');self.assertEqual(st,200);self.assertEqual(b,b'');self.assertGreater(int(h['content-length']),0);self.assertEqual(h.get('x-securitysearch-asset'),'gzip-static')
 def test_cookie_is_not_a_static_asset_variant(self):
  st,h,b=self.request(extra={'Cookie':'theme=Lain'});self.assertEqual(st,200);self.assertEqual(gzip.decompress(b),self.css)
 def test_home_gzip_quality_and_personalization_boundary(self):
  st,h,b=self.request('/',ae='gzip;q=1');self.assertEqual(st,200);self.assertEqual(gzip.decompress(b),self.home);self.assertEqual(int(h['content-length']),(self.root/'home-anonymous.generated.fast.gz').stat().st_size)
  for path,extra in (('/',{'Cookie':'theme=Black'}),('/',{'Authorization':'Bearer fixture'}),('/?s=fixture',{})):
   st,h,b=self.request(path,extra=extra);self.assertNotEqual(h.get('x-securitysearch-render'),'static-home')
 def test_encoded_sidecar_paths_are_denied(self):
  for path in ('/static/app.css%2epre%2egz','/static/app.css.pre%2Egz','/static/app.js%2Epre.gz'):
   with self.subTest(path=path):
    st,h,b=self.request(path);self.assertEqual(st,404);self.assertNotIn('content-encoding',h);self.assertNotIn('x-securitysearch-asset',h);self.assertNotIn('immutable',h.get('cache-control',''))
 def test_ranges_use_original_bytes(self):
  for extra in ({'Range':'bytes=0-31'},{'Range':'bytes=0-31','If-Range':'Thu, 01 Jan 1970 00:00:00 GMT'}):
   st,h,b=self.request(extra=extra);self.assertNotIn('content-encoding',h);self.assertNotIn('x-securitysearch-asset',h)
   if 'If-Range' in extra:self.assertEqual(st,200);self.assertEqual(b,self.css)
   else:self.assertEqual(st,206);self.assertEqual(b,self.css[:32]);self.assertEqual(h.get('content-range'),f'bytes 0-31/{len(self.css)}')
 def test_denied_sidecar_is_not_a_compressed_success(self):
  st,h,b=self.request('/static/app.css.pre.gz');self.assertEqual(st,404);self.assertNotIn('x-securitysearch-asset',h);self.assertNotIn('content-encoding',h)
if __name__=='__main__':unittest.main(verbosity=2)
