#!/usr/bin/env python3
"""Real filesystem fault tests for atomic deployment evidence."""
import importlib.util,json,os,stat,tempfile,unittest
from pathlib import Path
from unittest.mock import patch,Mock
from types import SimpleNamespace
R=Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('state',R/'scripts/deployment_state.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class Records(unittest.TestCase):
 def setUp(self):self.t=tempfile.TemporaryDirectory();self.p=Path(self.t.name);self.identity=m.identity(self.p)
 def tearDown(self):self.t.cleanup()
 def write(self,name='release.json',value=None):m.write_record(self.p,name,value or {'container':'fixture'},self.identity)
 def test_roundtrip_atomic_replacement(self):
  self.write();self.write(value={'second':True});self.assertEqual(json.loads((self.p/'release.json').read_text()),{'second':True});self.assertFalse(list(self.p.glob('.record-*')))
 def test_private_file_mode(self):self.write();self.assertEqual(stat.S_IMODE((self.p/'release.json').stat().st_mode),0o600)
 def test_plan_receipt_allowed(self):self.write('predeploy-cleanup.json');self.assertTrue((self.p/'predeploy-cleanup.json').is_file())
 def test_bad_names_refused(self):
  for name in ('../release.json','config.php','google-live.json','/tmp/release.json'):
   with self.assertRaises(m.StateError):self.write(name)
 def test_symlink_target_refused(self):
  (self.p/'real').write_text('preserved');(self.p/'release.json').symlink_to(self.p/'real')
  with self.assertRaises(m.StateError):self.write()
  self.assertEqual((self.p/'real').read_text(),'preserved')
 def test_symlink_directory_refused(self):
  link=self.p/'link';real=self.p/'real';real.mkdir();link.symlink_to(real,target_is_directory=True)
  with self.assertRaises(m.StateError):m.write_record(link,'release.json',{},m.identity(real))
 def test_replaced_directory_refused(self):
  self.identity=(self.identity[0],self.identity[1]+1)
  with self.assertRaises(m.StateError):self.write()
 def test_missing_directory_not_recreated(self):
  p=self.p/'absent'
  with self.assertRaises(m.StateError):m.write_record(p,'release.json',{},self.identity)
  self.assertFalse(p.exists())
 def test_replace_failure_preserves_previous_receipt(self):
  self.write(value={'old':True})
  with patch.object(m.os,'replace',side_effect=OSError(28,'disk full')):
   with self.assertRaises(m.StateError):self.write()
  self.assertEqual(json.loads((self.p/'release.json').read_text()),{'old':True});self.assertFalse(list(self.p.glob('.record-*')))
 def test_oversized_record_refused(self):
  with self.assertRaises(m.StateError):self.write(value={'body':'x'*(2*1024*1024)})
 def test_nan_refused(self):
  with self.assertRaises(ValueError):self.write(value={'x':float('nan')})
 def test_receipt_precedes_committed_flag(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s[s.index('def main():'):];self.assertLess(body.index('persist_release(backup'),body.index('committed = True'))
class PreBuildIntegration(unittest.TestCase):
 def setUp(self):
  spec=importlib.util.spec_from_file_location('deployment_integration',R/'scripts/deploy-ionos.py');self.d=importlib.util.module_from_spec(spec);spec.loader.exec_module(self.d)
  self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.backup=Path(self.tmp.name);self.expected=m.identity(self.backup)
  self.plan={'schema':1,'mode':'executed','target':'0.9.32','removed_containers':[],'removed_images':[]}
  def cleanup(*args,**kw):kw['record'](self.plan);return self.plan
  self.c=SimpleNamespace(docker=Mock(),inspect=Mock(return_value={'Id':'active'}),run_cleanup=Mock(side_effect=cleanup))
 def invoke(self,**changes):
  with patch.dict(os.environ,{'SECURITYSEARCH_CLEAN_OLDER':'1','SECURITYSEARCH_CONTAINER':'security-search'},clear=False),patch.object(self.d,'deployment_module',side_effect=lambda name:m if name=='deployment_state' else self.c),patch.object(self.d,'run',return_value='/tmp'),patch.object(self.d.shutil,'disk_usage',return_value=SimpleNamespace(free=changes.get('free',3*1024**3))),patch.object(self.d.os,'statvfs',return_value=SimpleNamespace(f_favail=changes.get('inodes',20000))):
   self.d.cleanup_before_build(self.backup,55,self.expected,'active')
 def test_opt_in_delegates_under_same_lock_and_records(self):
  self.invoke();self.assertEqual(self.c.run_cleanup.call_args.kwargs['lock_fd'],55);self.assertTrue(self.c.run_cleanup.call_args.kwargs['execute']);self.assertEqual(json.loads((self.backup/'predeploy-cleanup.json').read_text()),self.plan)
 def test_different_docker_context_has_no_deletions(self):
  self.c.inspect.return_value={'Id':'different'}
  with self.assertRaisesRegex(RuntimeError,'context differs'):self.invoke()
  self.c.run_cleanup.assert_not_called()
 def test_low_capacity_stops_before_build(self):
  with self.assertRaisesRegex(RuntimeError,'Insufficient build space'):self.invoke(free=1024)
  self.assertTrue((self.backup/'predeploy-cleanup.json').exists())
 def test_low_inode_capacity_stops(self):
  with self.assertRaisesRegex(RuntimeError,'Insufficient build space'):self.invoke(inodes=2)
 def test_no_opt_in_has_no_cleanup(self):
  with patch.dict(os.environ,{'SECURITYSEARCH_CLEAN_OLDER':'0'}),patch.object(self.d,'deployment_module') as module:
   self.d.cleanup_before_build(self.backup,55,self.expected,'active');module.assert_not_called()
 def test_custom_name_refused(self):
  with patch.dict(os.environ,{'SECURITYSEARCH_CLEAN_OLDER':'1','SECURITYSEARCH_CONTAINER':'other'}):
   with self.assertRaisesRegex(RuntimeError,'explicit security-search'):self.d.cleanup_before_build(self.backup,55,self.expected,'active')

if __name__=='__main__':unittest.main(verbosity=2)
