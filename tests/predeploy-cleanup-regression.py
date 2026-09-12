#!/usr/bin/env python3
"""Real planning/lock logic, simulated Docker objects; no daemon or deletion."""
import copy
import datetime as dt
import fcntl
import importlib.util
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT=Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('clean',ROOT/'scripts/predeploy_cleanup.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
NOW=dt.datetime(2026,9,11,23,tzinfo=dt.timezone.utc).timestamp()
def cid(n):return format(n,'064x')
def iid(n):return 'sha256:'+cid(n)
def img(n,v='0.9.30',tags=None,created='2026-09-10T10:00:00Z'):
 return {'Id':iid(n),'RepoTags':tags if tags is not None else [f'security-search:v{v}-20260910t{n:06d}z'],'Created':created,'Size':1000}
def con(n,im,v='0.9.30',kind='candidate',state='exited',created='2026-09-10T10:00:00Z'):
 return {'Id':cid(n),'Name':f'/security-search-{kind}-20260910t{n:06d}z','Image':iid(im),'Created':created,
 'Config':{'Image':f'security-search:v{v}-20260910t{im:06d}z','Labels':{'co.securityops.release':v}},
 'State':{'Status':state,'Running':state=='running','Health':{'Status':'healthy'},'StartedAt':'2026-09-10T10:00:00Z'},'RestartCount':0}
def fixture():
 active=con(1,1,state='running');active['Name']='/security-search'
 return [active,con(2,2),con(3,3,kind='rollback'),con(4,4,kind='rollback',created='2026-09-10T11:00:00Z')],[img(i) for i in range(1,5)]
class Engine:
 def __init__(self):self.cs,self.ims=fixture();self.calls=[];self.before_rm=None
 def run(self,*args):
  self.calls.append(args);kind,action=args[:2];rows=self.cs if kind=='container' else self.ims
  if action=='ls':return '\n'.join(r['Id'] for r in rows)
  if action=='inspect':
   found=[]
   for key in args[2:]:
    r=next((r for r in rows if key in [r['Id'],r.get('Name','').lstrip('/'),*(r.get('RepoTags') or [])]),None)
    if r is None:raise m.CleanupError('fixture missing')
    found.append(r)
   return json.dumps(found)
  if action=='rm':
   if self.before_rm:self.before_rm(kind,args[-1])
   key=args[-1];rows[:]=[r for r in rows if r['Id']!=key];return key
  raise AssertionError(args)
class Cleanup(unittest.TestCase):
 def plan(self,cs=None,ims=None):
  c,i=fixture();return m.make_plan(cs or c,ims if ims is not None else i,(cs or c)[0],'0.9.32',NOW)
 def test_old_stopped_removed_current_and_latest_rollback_kept(self):
  p=self.plan();self.assertEqual({x['id'] for x in p['containers']},{cid(2),cid(3)});self.assertEqual(set(p['protected_containers']),{cid(1),cid(4)})
 def test_current_image_protected(self):self.assertNotIn(iid(1),{x['id'] for x in self.plan()['images']})
 def test_any_remaining_container_protects_image(self):
  cs,im=fixture();x=con(10,2);x['Name']='/other-project';cs.append(x);self.assertNotIn(iid(2),{x['id'] for x in self.plan(cs,im)['images']})
 def test_same_or_newer_release_preserved(self):
  for v in ('0.9.32','0.9.33','1.0.0'):
   cs,im=fixture();cs.append(con(5,5,v));im.append(img(5,v));p=self.plan(cs,im);self.assertNotIn(cid(5),{x['id'] for x in p['containers']});self.assertNotIn(iid(5),{x['id'] for x in p['images']})
 def test_recent_and_future_objects_preserved(self):
  for t in ('2026-09-11T22:59:59Z','2026-09-12T22:00:00Z'):
   cs,im=fixture();cs.append(con(5,5,created=t));im.append(img(5,created=t));p=self.plan(cs,im);self.assertNotIn(cid(5),{x['id'] for x in p['containers']});self.assertNotIn(iid(5),{x['id'] for x in p['images']})
 def test_running_paused_restarting_objects_preserved(self):
  for state in ('running','paused','restarting','removing'):
   cs,im=fixture();cs[1]['State']['Status']=state;p=self.plan(cs,im);self.assertNotIn(cid(2),{x['id'] for x in p['containers']})
 def test_unhealthy_production_refused(self):
  for h in ('starting','unhealthy',None):
   cs,im=fixture();cs[0]['State']['Health']={'Status':h}
   with self.assertRaises(m.CleanupError):self.plan(cs,im)
 def test_conflicting_labels_not_owned(self):
  cs,im=fixture();cs[1]['Config']['Labels']['co.securityops.release']='1.0.0';self.assertNotIn(cid(2),{x['id'] for x in self.plan(cs,im)['containers']})
 def test_unrecognized_repository_not_owned(self):
  cs,im=fixture();cs[1]['Config']['Image']='evil/security-search:latest';self.assertNotIn(cid(2),{x['id'] for x in self.plan(cs,im)['containers']})
 def test_unknown_dangling_and_multitag_images_kept(self):
  for tags in ([],['other:latest'],['security-search:v0.9.30-20260910t100000z','other:latest'],['security-search:v0.9.30-20260910t100000z','security-search:v0.9.29-20260910t100000z']):
   im=[img(50,tags=tags)];self.assertEqual(self.plan(ims=im)['images'],[])
 def test_invalid_version_and_timestamp_fail(self):
  for v in ('', '1', '1.2.x','999999.1.1'):
   with self.assertRaises(m.CleanupError):m.version(v)
  for t in ('', 'today','2026-09-10T10:00:00'):
   with self.assertRaises(m.CleanupError):m.stamp(t)
 def test_plan_has_no_mutations(self):
  e=Engine();p=m.run_cleanup('0.9.32',run=e.run,now=NOW);self.assertEqual(p['mode'],'plan');self.assertFalse(any(x[1]=='rm' for x in e.calls))
 def test_execution_without_lock_stops_before_docker(self):
  e=Engine()
  with self.assertRaises(m.CleanupError):m.run_cleanup('0.9.32',execute=True,run=e.run,now=NOW)
  self.assertFalse(e.calls)
 def test_real_lock_blocks_another_open_description(self):
  with tempfile.TemporaryFile() as f:
   os.fchmod(f.fileno(),0o600);m.check_lock(f.fileno())
   fd=os.open('/proc/self/fd/'+str(f.fileno()),os.O_RDONLY)
   try:
    with self.assertRaises(m.CleanupError):m.check_lock(fd)
   finally:os.close(fd)
 def test_safe_execute_and_immutable_ids(self):
  e=Engine();records=[]
  with tempfile.TemporaryFile() as f:
   os.fchmod(f.fileno(),0o600);p=m.run_cleanup('0.9.32',execute=True,lock_fd=f.fileno(),run=e.run,now=NOW,record=lambda x:records.append(copy.deepcopy(x)))
  self.assertEqual(p['removed_containers'],[cid(2),cid(3)]);self.assertEqual(set(p['removed_images']),{iid(2),iid(3)})
  self.assertEqual({c['Id'] for c in e.cs},{cid(1),cid(4)});self.assertEqual(records[0]['removed_containers'],[])
  for call in e.calls:
   self.assertNotIn('--force',call);self.assertNotIn('--volumes',call);self.assertNotIn('prune',call)
   if call[:2]==('image','rm'):self.assertEqual(call[2],'--no-prune');self.assertTrue(call[-1].startswith('sha256:'))
 def test_report_failure_prevents_deletion(self):
  e=Engine()
  with tempfile.TemporaryFile() as f:
   os.fchmod(f.fileno(),0o600)
   with self.assertRaises(OSError):m.run_cleanup('0.9.32',execute=True,lock_fd=f.fileno(),run=e.run,now=NOW,record=lambda p:(_ for _ in ()).throw(OSError('full')))
  self.assertFalse(any(x[1]=='rm' for x in e.calls))
 def test_production_change_stops_next_removal(self):
  e=Engine();e.before_rm=lambda kind,obj:e.cs[0].update(RestartCount=1)
  with tempfile.TemporaryFile() as f:
   os.fchmod(f.fileno(),0o600)
   with self.assertRaises(m.CleanupError):m.run_cleanup('0.9.32',execute=True,lock_fd=f.fileno(),run=e.run,now=NOW)
  self.assertEqual(len([x for x in e.calls if x[1]=='rm']),1)
 def test_docker_failure_never_forces(self):
  e=Engine();original=e.run
  def run(*a):
   if a[1]=='rm':raise m.CleanupError('fixture failure')
   return original(*a)
  with tempfile.TemporaryFile() as f:
   os.fchmod(f.fileno(),0o600)
   with self.assertRaises(m.CleanupError):m.run_cleanup('0.9.32',execute=True,lock_fd=f.fileno(),run=run,now=NOW)
  self.assertEqual(len(e.cs),4)
if __name__=='__main__':unittest.main(verbosity=2)
