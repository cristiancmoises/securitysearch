#!/usr/bin/env python3
"""Actual PHP/zlib tests in disposable directories; no provider requests."""
import gzip, hashlib, json, os, pathlib, subprocess, tempfile, unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
BUILDER=ROOT/'lib/build_static_assets.php'
class Assets(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.root=pathlib.Path(self.tmp.name)/'static';self.root.mkdir();(self.root/'themes').mkdir()
 def tearDown(self):self.tmp.cleanup()
 def build(self):
  php='require $argv[1];echo json_encode(securitysearch_build_static_assets($argv[2]));'
  return subprocess.run(['php','-r',php,str(BUILDER),str(self.root)],capture_output=True,text=True,timeout=20)
 def test_round_trip_and_permissions(self):
  raw=b'body{color:cyan;background:black}\n'*600;(self.root/'style.css').write_bytes(raw)
  p=self.build();self.assertEqual(p.returncode,0,p.stderr);r=json.loads(p.stdout);gz=self.root/'style.css.pre.gz'
  self.assertEqual(gzip.decompress(gz.read_bytes()),raw);self.assertEqual(gz.stat().st_mode&0o777,0o644);self.assertEqual(r['files']['style.css']['sha256'],hashlib.sha256(raw).hexdigest())
 def test_repeat_deterministic(self):
  (self.root/'app.js').write_bytes(b'let a=1;\n'*1000);self.assertEqual(self.build().returncode,0);a=(self.root/'app.js.pre.gz').read_bytes();self.assertEqual(self.build().returncode,0);self.assertEqual(a,(self.root/'app.js.pre.gz').read_bytes())
 def test_source_edit_invalidates(self):
  f=self.root/'style.css';f.write_bytes(b'a{color:cyan}\n'*1000);self.build();f.write_bytes(b'b{color:black}\n'*1000);self.build();self.assertEqual(gzip.decompress((self.root/'style.css.pre.gz').read_bytes()),f.read_bytes())
 def test_does_not_copy_images_or_private_subtree(self):
  (self.root/'user.png').write_bytes(b'SECRET'*100);(self.root/'operator-themes').mkdir();(self.root/'operator-themes/user.css').write_bytes(b'SECRET'*100)
  self.assertEqual(self.build().returncode,0);self.assertEqual(list(self.root.rglob('*.pre.gz')),[])
 def test_symlink_input_refused(self):
  secret=pathlib.Path(self.tmp.name)/'private';secret.write_text('secret');(self.root/'x.css').symlink_to(secret)
  self.assertNotEqual(self.build().returncode,0);self.assertEqual(secret.read_text(),'secret')
 def test_symlink_output_refused(self):
  f=self.root/'x.css';f.write_bytes(b'a{}'*1000);target=pathlib.Path(self.tmp.name)/'private';target.write_text('secret');(self.root/'x.css.pre.gz').symlink_to(target)
  self.assertNotEqual(self.build().returncode,0);self.assertEqual(target.read_text(),'secret')
 def test_directory_link_refused(self):
  (self.root/'themes').rmdir();(self.root/'themes').symlink_to(pathlib.Path(self.tmp.name));self.assertNotEqual(self.build().returncode,0)
 def test_size_limit(self):
  (self.root/'huge.css').write_bytes(b' '*(1048576+1));self.assertNotEqual(self.build().returncode,0);self.assertFalse((self.root/'huge.css.pre.gz').exists())
 def test_small_inputs_unchanged(self):
  (self.root/'small.js').write_text('let x=1;');self.assertEqual(self.build().returncode,0);self.assertFalse((self.root/'small.js.pre.gz').exists())
 def test_removed_source_removes_stale_sidecar(self):
  (self.root/'gone.css.pre.gz').write_bytes(b'old');self.assertEqual(self.build().returncode,0);self.assertFalse((self.root/'gone.css.pre.gz').exists())
 def test_only_startup_and_no_source_archive(self):
  text=(ROOT/'docker/docker-entrypoint.sh').read_text();self.assertLess(text.index('build_static_assets.php --build'),text.index('exec httpd'))
  for fn in ('.dockerignore','.gitignore','.gitattributes'):self.assertIn('*.pre.gz',(ROOT/fn).read_text())
if __name__=='__main__':unittest.main(verbosity=2)
