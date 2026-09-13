#!/usr/bin/env python3
"""Exercise the real Apache configuration and generated files over localhost HTTP."""
import gzip
import importlib.util
from pathlib import Path
import unittest
R=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('existing_http_assets',R/'tests/static-assets-http-regression.py')
base=importlib.util.module_from_spec(spec);spec.loader.exec_module(base)
class HomeRepresentation(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  base.HTTPAssets.setUpClass();cls.fixture=base.HTTPAssets()
 @classmethod
 def tearDownClass(cls):base.HTTPAssets.tearDownClass()
 def request(self,*args,**kwargs):return self.fixture.request(*args,**kwargs)
 def test_internal_plain_and_gzip_are_denied(self):
  for suffix in ('','.gz'):
   for path in ('/home-anonymous.generated.fast'+suffix,'/home-anonymous%2Egenerated%2efast'+suffix,'/home-anonymous.generated.fast'+suffix+'?x=1'):
    with self.subTest(path=path):
     st,h,b=self.request(path,ae='identity');self.assertEqual(st,404)
     self.assertNotIn('content-encoding',h);self.assertNotIn('x-securitysearch-render',h)
     self.assertEqual(h.get('cache-control'),'no-store');self.assertNotIn('public',h.get('cache-control',''))
 def test_denied_response_is_decodable_with_gzip_request(self):
  st,h,b=self.request('/home-anonymous.generated.fast.gz',ae='gzip');self.assertEqual(st,404)
  # A real error compression filter may run; the header must describe actual bytes.
  if h.get('content-encoding')=='gzip':gzip.decompress(b)
  else:self.assertFalse(b.startswith(b'\x1f\x8b'))
  self.assertNotIn('x-securitysearch-render',h)
 def test_root_gzip_is_exact_precompressed_representation(self):
  st,h,b=self.request('/',ae='gzip');self.assertEqual(st,200)
  self.assertEqual(h.get('content-encoding'),'gzip');self.assertEqual(gzip.decompress(b),self.fixture.home)
  self.assertEqual(h.get('x-securitysearch-render'),'static-home')
  self.assertEqual(len(b),int(h['content-length']))
 def test_root_identity_is_exact_and_not_gzip(self):
  st,h,b=self.request('/',ae='identity');self.assertEqual(st,200);self.assertNotIn('content-encoding',h)
  self.assertEqual(b,self.fixture.home)
 def test_root_range_uses_identity_bytes(self):
  st,h,b=self.request('/',ae='gzip',extra={'Range':'bytes=0-15'});self.assertEqual(st,206)
  self.assertNotIn('content-encoding',h);self.assertEqual(b,self.fixture.home[:16])
  self.assertEqual(h['content-range'],f'bytes 0-15/{len(self.fixture.home)}')
 def test_stale_if_range_falls_back_to_complete_identity(self):
  st,h,b=self.request('/',ae='gzip',extra={'Range':'bytes=0-15','If-Range':'Thu, 01 Jan 1970 00:00:00 GMT'})
  self.assertEqual(st,200);self.assertNotIn('content-encoding',h);self.assertEqual(b,self.fixture.home)
 def test_root_head_keeps_representation_metadata_without_body(self):
  st,h,b=self.request('/',method='HEAD',ae='gzip');self.assertEqual(st,200);self.assertEqual(b,b'')
  self.assertEqual(h.get('content-encoding'),'gzip');self.assertGreater(int(h['content-length']),0)
 def test_personalized_or_query_request_never_uses_snapshot(self):
  for path,headers in (('/',{'Cookie':'theme=Black'}),('/',{'Authorization':'Bearer fixture'}),('/?s=0',{})):
   with self.subTest(path=path,headers=headers):
    st,h,b=self.request(path,extra=headers);self.assertNotEqual(h.get('x-securitysearch-render'),'static-home')
if __name__=='__main__':unittest.main(verbosity=2)
