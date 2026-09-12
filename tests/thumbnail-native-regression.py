#!/usr/bin/env python3
"""Serving-extension test: actual ImageMagick fallback; only upstream bytes are fixed."""
import subprocess,sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).parent/'fixtures'))
from thumbnail_http_fixture import ProxyFixture,png
ROOT=Path(__file__).resolve().parents[1]
if subprocess.run(['php','-r','exit(class_exists("Imagick")?0:1);']).returncode:
 raise SystemExit('BLOCKED: native PHP Imagick is required; no decoder stub is counted as a pass.')
class NativeThumbnail(unittest.TestCase):
 @classmethod
 def setUpClass(cls):cls.fx=ProxyFixture(ROOT,decoder_tripwire=False)
 @classmethod
 def tearDownClass(cls):cls.fx.close()
 def test_small_png_bytes_retained(self):
  data=png();self.fx.set(data);s,h,b=self.fx.request();self.assertEqual(s,200);self.assertEqual(b,data);self.assertEqual(h['content-type'],'image/png')
 def test_rgba_still_converts_to_jpeg(self):
  self.fx.set(png(color=6));s,h,b=self.fx.request();self.assertEqual(s,200);self.assertEqual(h['content-type'],'image/jpeg');self.assertTrue(b.startswith(b'\xff\xd8'))
 def test_large_png_still_resizes(self):
  self.fx.set(png(400,300));s,h,b=self.fx.request();self.assertEqual(s,200);self.assertEqual(h['content-type'],'image/jpeg');self.assertTrue(b.startswith(b'\xff\xd8'))
 def test_metadata_still_stripped(self):
  self.fx.set(png(metadata=True));s,h,b=self.fx.request();self.assertEqual(s,200);self.assertEqual(h['content-type'],'image/jpeg');self.assertNotIn(b'private-fixture',b)
 def test_poster_still_static(self):
  self.fx.set((ROOT/'tests/fixtures/motion/two.png').read_bytes());s,h,b=self.fx.request('poster');self.assertEqual(s,200);self.assertEqual(h['content-type'],'image/jpeg')
if __name__=='__main__':unittest.main(verbosity=2)
