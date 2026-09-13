#!/usr/bin/env python3
"""Actual PHP HTTP fixture; a transport tripwire forbids remote fetches.
The real endpoint/cache/headers run; only cache-miss network delivery is a double.
"""
import http.client, os, pathlib, shutil, socket, struct, subprocess, tempfile, time, unittest, zlib
ROOT=pathlib.Path(__file__).resolve().parents[1]
def png(width=1,height=1):
 def chunk(kind,data):return struct.pack('!I',len(data))+kind+data+struct.pack('!I',zlib.crc32(kind+data)&0xffffffff)
 return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',width,height,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\0'+b'\xff\0\0'*width))+chunk(b'IEND',b'')
class CachedHTTP(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  cls.tmp=tempfile.TemporaryDirectory();cls.root=pathlib.Path(cls.tmp.name)
  for d in ('icons','lib','data'):(cls.root/d).mkdir()
  for name in ('favicon.php','lib/security_headers_minimal.php','lib/favicon_policy.php','lib/favicon_cache.php','lib/favicon404.png','data/config.php'):
   shutil.copy2(ROOT/name,cls.root/name)
  # A cache hit must not even import the proxy. Misses invoke this sentinel,
  # which never resolves DNS or opens a socket and retains normal fallback.
  (cls.root/'lib/curlproxy.php').write_text('''<?php
file_put_contents(__DIR__."/transport-imported", "yes");
class proxy {const req_web=0;const req_image=1;
 function __construct($c=true){}
 function get(...$args){file_put_contents(__DIR__."/transport-called", "yes");throw new RuntimeException("offline tripwire");}
}
''')
  with socket.socket() as s:s.bind(('127.0.0.1',0));cls.port=s.getsockname()[1]
  cls.log=(cls.root/'php.log').open('wb')
  cls.proc=subprocess.Popen(['php','-d','display_errors=0','-d','error_reporting=32767','-S',f'127.0.0.1:{cls.port}','-t',str(cls.root)],stdout=cls.log,stderr=cls.log)
  for _ in range(100):
   if cls.proc.poll() is not None:raise RuntimeError('PHP fixture exited')
   try:
    with socket.create_connection(('127.0.0.1',cls.port),.1):return
   except OSError:time.sleep(.02)
  raise RuntimeError('PHP fixture did not start')
 @classmethod
 def tearDownClass(cls):
  cls.proc.terminate()
  try:cls.proc.wait(timeout=3)
  except subprocess.TimeoutExpired:cls.proc.kill();cls.proc.wait(timeout=3)
  cls.log.close();cls.tmp.cleanup()
 def setUp(self):
  # A previous miss legitimately persists a negative APCu entry for 60 seconds.
  # Isolate independent cases by host instead of disabling APCu or clearing it.
  self.host=self._testMethodName.replace('_','-')+'.fixture.invalid'
  self.file=self.root/'icons'/(self.host+'.png')
  if self.file.exists() or self.file.is_symlink():self.file.unlink()
  self.data=(ROOT/'lib/favicon404.png').read_bytes();self.file.write_bytes(self.data)
  for n in ('transport-imported','transport-called'):(self.root/'lib'/n).unlink(missing_ok=True)
 def request(self,method='GET',headers=None,path=None):
  if path is None:path='/favicon.php?s=https://'+self.host
  c=http.client.HTTPConnection('127.0.0.1',self.port,timeout=3)
  try:
   c.request(method,path,headers=headers or {});r=c.getresponse();return r.status,{k.lower():v for k,v in r.getheaders()},r.read()
  finally:c.close()
 def clean_hit(self):
  self.assertFalse((self.root/'lib/transport-imported').exists());self.assertFalse((self.root/'lib/transport-called').exists())
 def test_original_bytes_and_headers(self):
  st,h,b=self.request();self.assertEqual(st,200);self.assertEqual(b,self.data);self.assertEqual(h['content-type'],'image/png');self.assertEqual(h['x-content-type-options'],'nosniff');self.assertEqual(int(h['content-length']),len(b));self.assertIn('max-age=86400',h['cache-control']);self.assertTrue(h['etag'].startswith('W/"ss-icon-'));self.clean_hit()
 def test_etag_revalidation_has_no_body(self):
  tag=self.request()[1]['etag']
  for field in (tag,tag[2:],'"other", '+tag,'*','W/"comma,inside", '+tag):
   with self.subTest(field=field):
    st,h,b=self.request(headers={'If-None-Match':field});self.assertEqual(st,304);self.assertEqual(b,b'');self.assertEqual(h['etag'],tag);self.assertNotIn('content-length',h);self.assertIn('max-age=86400',h['cache-control'])
  self.clean_hit()
 def test_cached_head_semantics(self):
  st,h,b=self.request(method='HEAD');self.assertEqual(st,200);self.assertEqual(b,b'');self.assertEqual(int(h['content-length']),len(self.data));self.clean_hit()
 def test_conditional_head(self):
  tag=self.request()[1]['etag'];st,h,b=self.request(method='HEAD',headers={'If-None-Match':tag});self.assertEqual(st,304);self.assertEqual(b,b'');self.assertNotIn('content-length',h);self.clean_hit()
 def test_bad_conditions_do_not_hide_body(self):
  tag=self.request()[1]['etag']
  for field in ('"different"',tag+',broken',tag+',','*, '+tag,'x'*4097):
   st,h,b=self.request(headers={'If-None-Match':field});self.assertEqual(st,200);self.assertEqual(b,self.data)
  self.clean_hit()
 def test_new_bytes_invalidate_old_tag(self):
  tag=self.request()[1]['etag'];self.file.write_bytes(png());st,h,b=self.request(headers={'If-None-Match':tag});self.assertEqual(st,200);self.assertEqual(b,png());self.assertNotEqual(tag,h['etag']);self.clean_hit()
 def test_metadata_not_invalidator_for_identical_bytes(self):
  tag=self.request()[1]['etag'];os.utime(self.file,(42,42));st,h,b=self.request(headers={'If-None-Match':tag});self.assertEqual(st,304);self.assertEqual(h['etag'],tag);self.clean_hit()
 def test_error_placeholder_is_never_304(self):
  st,h,b=self.request(headers={'If-None-Match':'*'},path='/favicon.php?s=404');self.assertEqual(st,404);self.assertTrue(b.startswith(b'\x89PNG'));self.assertNotIn('etag',h);self.assertIn('max-age=300',h['cache-control']);self.clean_hit()
 def test_other_preconditions_do_not_select_304(self):
  for key in ('If-Match','If-Unmodified-Since','Range','If-Range'):
   st,h,b=self.request(headers={'If-None-Match':'*',key:'fixture'});self.assertEqual(st,200);self.assertEqual(b,self.data)
 def test_non_safe_method_never_304(self):
  st,h,b=self.request(method='POST',headers={'If-None-Match':'*'});self.assertEqual(st,200);self.assertEqual(b,self.data)
 def test_symlink_cache_does_not_disclose_target(self):
  self.file.unlink();secret=self.root/'outside.png';secret.write_bytes(png());self.file.symlink_to(secret)
  st,h,b=self.request();self.assertEqual(st,404);self.assertNotEqual(b,png());self.assertTrue((self.root/'lib/transport-called').exists())
 def test_invalid_or_oversized_png_is_normal_miss(self):
  for content in (b'not-png',b'x'*131073,png(257,1)):
   self.file.write_bytes(content);st,h,b=self.request();self.assertEqual(st,404);self.assertNotEqual(b,content);self.assertNotIn('etag',h)
 def test_warning_free_invalid_target(self):
  st,h,b=self.request(path='/favicon.php?s=404');self.assertEqual(st,404)
  text=(self.root/'php.log').read_text();self.assertNotIn('Undefined property',text);self.assertNotIn('Undefined variable',text)
 def test_valid_disk_hit_after_failed_refresh(self):
  self.file.write_bytes(b'not-png')
  st,h,b=self.request();self.assertEqual(st,404)
  self.assertTrue((self.root/'lib/transport-called').exists())
  self.file.write_bytes(self.data)
  for n in ('transport-imported','transport-called'):(self.root/'lib'/n).unlink(missing_ok=True)
  st,h,b=self.request();self.assertEqual(st,200);self.assertEqual(b,self.data);self.clean_hit()
 def test_distinct_host_miss_is_not_suppressed_by_previous_host(self):
  self.file.write_bytes(b'not-png')
  st,h,b=self.request();self.assertEqual(st,404)
  self.assertTrue((self.root/'lib/transport-called').exists())
  for n in ('transport-imported','transport-called'):(self.root/'lib'/n).unlink(missing_ok=True)
  other='other-'+self.host
  st,h,b=self.request(path='/favicon.php?s=https://'+other);self.assertEqual(st,404)
  self.assertTrue((self.root/'lib/transport-called').exists())
if __name__=='__main__':unittest.main(verbosity=2)
