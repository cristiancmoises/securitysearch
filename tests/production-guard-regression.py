#!/usr/bin/env python3
"""Real immutable snapshot checks; no daemon, commands, configuration or secrets logged."""
import copy,importlib.util,json,unittest
from pathlib import Path
R=Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('guard',R/'scripts/production_guard.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
def fixture():
 return {'Id':'a'*64,'Image':'sha256:'+'b'*64,'Name':'/security-search','Created':'2026-09-01T00:00:00Z','RestartCount':0,
 'State':{'Status':'running','Running':True,'Health':{'Status':'healthy','Log':[{'End':'old'}]},'StartedAt':'2026-09-01T00:00:00Z'},
 'Config':{'Env':['SECRET=do-not-log-me'],'Image':'security-search:v0.9.32-example'},'HostConfig':{'RestartPolicy':{'Name':'always'},'PortBindings':{'80/tcp':[{'HostPort':'5140'}]}},
 'Mounts':[{'Source':'/private/secret-path','Destination':'/data'}],'NetworkSettings':{'Networks':{'net':{'Aliases':['security-search']}}}}
class Guard(unittest.TestCase):
 def setUp(self):self.current=fixture();self.expected=m.snapshot(self.current)
 def test_unchanged_passes(self):m.verify(self.expected,copy.deepcopy(self.current))
 def test_only_digest_kept(self):
  text=json.dumps(self.expected);self.assertNotIn('do-not-log',text);self.assertNotIn('/private',text)
  self.assertTrue(all(len(v)==64 for v in self.expected.values()))
 def test_key_order_does_not_change_fingerprint(self):
  shuffled=dict(reversed(list(self.current.items())));m.verify(self.expected,shuffled)
 def test_healthcheck_log_timing_not_identity(self):
  self.current['State']['Health']['Log']=[{'End':'new'}];m.verify(self.expected,self.current)
 def test_replacement_rejected(self):
  self.current['Id']='c'*64
  with self.assertRaisesRegex(m.ProductionChanged,'identity'):m.verify(self.expected,self.current)
 def test_restart_rejected(self):
  self.current['RestartCount']=1
  with self.assertRaises(m.ProductionChanged):m.verify(self.expected,self.current)
 def test_new_start_time_rejected(self):
  self.current['State']['StartedAt']='2026-09-02T00:00:00Z'
  with self.assertRaises(m.ProductionChanged):m.verify(self.expected,self.current)
 def test_unhealthy_paused_and_restarting_refused(self):
  for change in ({'Running':False},{'Status':'exited'},{'Paused':True},{'Restarting':True},{'Health':{'Status':'unhealthy'}}):
   value=fixture();value['State'].update(change)
   with self.subTest(change=change),self.assertRaises(m.ProductionChanged):m.verify(self.expected,value)
 def test_config_drift_no_private_values_in_error(self):
  self.current['Config']['Env'].append('NEW_SECRET=hide-this')
  with self.assertRaises(m.ProductionChanged) as exc:m.verify(self.expected,self.current)
  self.assertIn('configuration',str(exc.exception));self.assertNotIn('SECRET',str(exc.exception));self.assertNotIn('hide-this',str(exc.exception))
 def test_mount_drift_rejected(self):
  self.current['Mounts'][0]['Source']='/other-secret'
  with self.assertRaisesRegex(m.ProductionChanged,'mounts'):m.verify(self.expected,self.current)
 def test_ports_restartpolicy_and_networks_rejected(self):
  for section in ('HostConfig','NetworkSettings'):
   c=fixture();c[section]={} if section=='HostConfig' else {'Networks':{}}
   with self.subTest(section=section),self.assertRaises(m.ProductionChanged):m.verify(self.expected,c)
 def test_missing_required_metadata_refused(self):
  for key in ('Id','Image','Name','RestartCount','Config','HostConfig','Mounts','NetworkSettings'):
   c=fixture();del c[key]
   with self.subTest(key=key),self.assertRaises(m.ProductionChanged):m.snapshot(c)
 def test_missing_snapshot_cannot_qualify(self):
  for value in (None,{},[],{'identity':'x'}):
   with self.subTest(value=value),self.assertRaises(m.ProductionChanged):m.verify(value,self.current)
 def test_nan_metadata_refused(self):
  self.current['Config']['wrong']=float('nan')
  with self.assertRaises(m.ProductionChanged):m.snapshot(self.current)
if __name__=='__main__':unittest.main(verbosity=2)
