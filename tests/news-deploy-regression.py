#!/usr/bin/env python3
"""Offline, actual selection-report checks; no Docker engine or external network."""
import contextlib, copy, importlib.util, io, json, tempfile, unittest, subprocess
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('news_deploy',ROOT/'scripts/deploy-ionos.py')
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def good(origin=None):
 origin=origin or m.REDLIB_ORIGINS[1]
 return {'status':'ok','origin':origin,'attempts':[{'origin':origin,'status':'ok','feed_count':5,'search_count':3}]}
class NewsGate(unittest.TestCase):
 def call(self,report):
  # The transport is mocked. Do not print a simulated live-verification claim.
  with tempfile.TemporaryDirectory()as t,patch.object(m,'run',side_effect=['',json.dumps(report)]),contextlib.redirect_stdout(io.StringIO()):
   try:return m.live_redlib_gate('fixture',Path(t))
   finally:self.assertTrue((Path(t)/'redlib-live.json').exists())
 def test_runtime_allowlist_matches_deployment(self):
  actual=subprocess.check_output(['php','-r','require "lib/service_pool.php"; echo json_encode(service_pool::allowed());'],cwd=ROOT,text=True)
  self.assertEqual(tuple(json.loads(actual)),m.REDLIB_ORIGINS)
 def test_success(self):self.assertEqual(self.call(good()),m.REDLIB_ORIGINS[1])
 def test_empty_or_challenge_is_not_success(self):
  for field in ['feed_count','search_count']:
   for bad in [0,-1,26,'2',True,None]:
    r=good();r['attempts'][0][field]=bad
    with self.subTest(field=field,bad=bad),self.assertRaisesRegex(RuntimeError,'No approved Redlib'):self.call(r)
 def test_unapproved_or_malformed(self):
  for r in [None,[],{},good('http://127.0.0.1'),{'status':'ok','origin':m.REDLIB_ORIGINS[0]},dict(good(),status='unavailable')]:
   with self.subTest(r=r),self.assertRaises(RuntimeError):self.call(r)
 def test_execution_failure_keeps_report(self):
  with tempfile.TemporaryDirectory()as t,patch.object(m,'run',side_effect=['',RuntimeError('private details')]):
   with self.assertRaises(RuntimeError):m.live_redlib_gate('fixture',Path(t))
   self.assertNotIn('private details',(Path(t)/'redlib-live.json').read_text())
 def test_environment_preservation(self):
  old={'Config':{'Env':['FOURGET_REDLIB_PRIMARY=https://libre.securityops.co','KEEP=yes']}}
  m.set_redlib_primary(old,m.REDLIB_ORIGINS[2]);self.assertIn('KEEP=yes',old['Config']['Env'])
  self.assertEqual(sum(x.startswith('FOURGET_REDLIB_PRIMARY=')for x in old['Config']['Env']),1)
  before=copy.deepcopy(old)
  with self.assertRaises(RuntimeError):m.set_redlib_primary(old,'https://evil.example')
  self.assertEqual(before,old)
 def test_replacement_effective_config(self):
  with patch.object(m,'run',return_value=m.REDLIB_ORIGINS[1]):m.verify_redlib_config('fixture',m.REDLIB_ORIGINS[1])
  with patch.object(m,'run',return_value=m.REDLIB_ORIGINS[0]),self.assertRaises(RuntimeError):m.verify_redlib_config('fixture',m.REDLIB_ORIGINS[1])
 def test_required_transaction_calls(self):
  s=(ROOT/'scripts/deploy-ionos.py').read_text()
  self.assertLess(s.index('selected_redlib = live_redlib_gate'),s.index('stopped = True'))
  self.assertLess(s.index('verify_redlib_config(replacement'),s.index('committed = True'))
if __name__=='__main__':unittest.main(verbosity=2)
