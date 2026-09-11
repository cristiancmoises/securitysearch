#!/usr/bin/env python3
"""Filesystem/systemd-boundary fixtures only; never operates on host units."""
from pathlib import Path
import importlib.util, subprocess, tempfile, unittest
s=importlib.util.spec_from_file_location('timer',Path(__file__).resolve().parents[1]/'scripts/install-rank-timer.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class Tests(unittest.TestCase):
 def test_new_and_repeat(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);calls=[]
   def run(args,**kw):calls.append(args);return subprocess.CompletedProcess(args,0)
   self.assertEqual(m.install(root,'/usr/bin/docker',run),0)
   before={p.name:p.read_bytes() for p in root.iterdir()}
   m.install(root,'/usr/bin/docker',run)
   self.assertEqual(before,{p.name:p.read_bytes() for p in root.iterdir()})
   service=before[m.UNIT+'.service'].decode()
   self.assertIn('--user apache',service);self.assertIn('ExecStartPost=',service);self.assertIn('build_home_snapshot.php --build',service)
   self.assertEqual(calls[1],['systemctl','enable','--now',m.UNIT+'.timer'])
 def test_exact_v1_upgrades(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);calls=[]
   for name,text in m._v1_unit_files('/usr/bin/docker').items():(root/name).write_text(text)
   def run(args,**kw):calls.append(args);return subprocess.CompletedProcess(args,0)
   self.assertEqual(m.install(root,'/usr/bin/docker',run),0)
   for name,text in m.unit_files('/usr/bin/docker').items():self.assertEqual((root/name).read_text(),text)
   self.assertEqual(calls[0],['systemctl','daemon-reload'])
 def test_modified_v1_is_preserved(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);files=m._v1_unit_files('/usr/bin/docker')
   (root/(m.UNIT+'.service')).write_text(files[m.UNIT+'.service']+'# local edit\n')
   (root/(m.UNIT+'.timer')).write_text(files[m.UNIT+'.timer'])
   with self.assertRaises(RuntimeError):m.install(root,'/usr/bin/docker',lambda *a,**k:self.fail('No systemd call'))
   self.assertTrue((root/(m.UNIT+'.service')).read_text().endswith('# local edit\n'))
 def test_conflict_no_writes(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);(root/(m.UNIT+'.timer')).write_text('operator managed')
   with self.assertRaises(RuntimeError):m.install(root,'/usr/bin/docker',lambda *a,**k:self.fail('No systemd call'))
   self.assertEqual(len(list(root.iterdir())),1)
 def test_symlink(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);(root/(m.UNIT+'.service')).symlink_to('missing')
   with self.assertRaises(RuntimeError):m.install(root,'/usr/bin/docker',lambda *a,**k:None)
 def test_refresh_failure_keeps_timer(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t)
   def run(args,**kw):return subprocess.CompletedProcess(args,1 if args[1]=='start' else 0)
   self.assertEqual(m.install(root,'/usr/bin/docker',run),1)
   self.assertTrue((root/(m.UNIT+'.timer')).is_file())
if __name__=='__main__':unittest.main(verbosity=2)
