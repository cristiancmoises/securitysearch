#!/usr/bin/env python3
"""Native PHP HTTP routing + fixed upstream responses, decoder tripwire, no egress."""
import sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).parent/'fixtures'))
from thumbnail_http_fixture import ProxyFixture,png
ROOT=Path(__file__).resolve().parents[1]
class ThumbnailHTTP(unittest.TestCase):
 @classmethod
 def setUpClass(cls):cls.fx=ProxyFixture(ROOT)
 @classmethod
 def tearDownClass(cls):cls.fx.close()
 def setUp(self):self.body=png();self.fx.set(self.body)
 def test_rgb_exact_bytes_without_conversion(self):
  st,h,b=self.fx.request();self.assertEqual(st,200);self.assertEqual(b,self.body);self.assertEqual(h['content-type'],'image/png');self.assertEqual(int(h['content-length']),len(b));self.assertFalse(self.fx.decoded)
 def test_grayscale_exact_bytes(self):
  data=png(23,17,0);self.fx.set(data);st,h,b=self.fx.request();self.assertEqual(st,200);self.assertEqual(b,data);self.assertFalse(self.fx.decoded)
 def test_head_headers_without_body(self):
  st,h,b=self.fx.request(method='HEAD');self.assertEqual(st,200);self.assertEqual(b,b'');self.assertEqual(int(h['content-length']),len(self.body));self.assertFalse(self.fx.decoded)
 def test_no_new_public_cache_or_cookie(self):
  st,h,b=self.fx.request(extra='&private=fixture');self.assertEqual(st,200);self.assertNotIn('public',h.get('cache-control',''));self.assertNotIn('set-cookie',h);self.assertEqual(h['x-content-type-options'],'nosniff');self.assertEqual(h['cross-origin-resource-policy'],'same-origin')
 def test_metadata_uses_existing_conversion(self):
  self.fx.set(png(metadata=True));st,h,b=self.fx.request();self.assertTrue(self.fx.decoded);self.assertEqual(st,404);self.assertNotIn(b'private-fixture',b)
 def test_alpha_uses_existing_conversion(self):
  self.fx.set(png(color=6));self.fx.request();self.assertTrue(self.fx.decoded)
 def test_size_overflow_uses_existing_conversion(self):
  for w,h in ((237,80),(120,181)):
   self.fx.set(png(w,h));self.fx.request();self.assertTrue(self.fx.decoded)
 def test_poster_and_explicit_modes_preserved(self):
  for mode in ('poster','high','portrait','landscape','square','cover'):
   with self.subTest(mode=mode):
    self.fx.set(self.body);self.fx.request(mode);self.assertTrue(self.fx.decoded)
 def test_bad_crc_does_not_use_passthrough(self):
  body=bytearray(self.body);body[-1]^=1;self.fx.set(bytes(body));st,h,b=self.fx.request();self.assertTrue(self.fx.decoded);self.assertEqual(st,404)
 def test_trailing_data_not_relayed(self):
  self.fx.set(self.body+b'private-fixture');st,h,b=self.fx.request();self.assertTrue(self.fx.decoded);self.assertNotIn(b'private-fixture',b)
 def test_upstream_errors_do_not_decode_or_look_successful(self):
  for status in (206,301,403,404,429,500,503,True,None,'200'):
   with self.subTest(status=status):
    self.fx.set(self.body,status);st,h,b=self.fx.request();self.assertEqual(st,404);self.assertNotEqual(b,self.body);self.assertFalse(self.fx.decoded);self.assertEqual(h['cache-control'],'no-store')
 def test_animation_error_has_empty_failure(self):
  self.fx.set((ROOT/'tests/fixtures/motion/two.gif').read_bytes(),429)
  st,h,b=self.fx.request('animated');self.assertEqual(st,422);self.assertEqual(b,b'');self.assertFalse(self.fx.decoded);self.assertEqual(h['cache-control'],'no-store')
 def test_valid_animation_path_preserved(self):
  data=(ROOT/'tests/fixtures/motion/two.gif').read_bytes();self.fx.set(data)
  st,h,b=self.fx.request('animated');self.assertEqual(st,200);self.assertEqual(b,data);self.assertEqual(h['content-type'],'image/gif');self.assertFalse(self.fx.decoded)
 def test_logs_have_no_php_warnings(self):
  self.fx.request();text=(self.fx.root/'php.log').read_text();self.assertNotIn('PHP Warning',text);self.assertNotIn('PHP Fatal',text)
if __name__=='__main__':unittest.main(verbosity=2)
