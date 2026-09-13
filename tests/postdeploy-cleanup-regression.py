#!/usr/bin/env python3
"""Actual filesystem fixtures; Docker metadata/removals are simulated, never a live daemon."""
import copy,importlib.util,json,os,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
S=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('retention',S/'scripts/postdeploy_cleanup.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def container(n,version='0.9.42',stamp='20260913t190000z',state='running',kind='rollback'):
 return {'Id':format(n,'064x'),'Image':'sha256:'+format(n,'064x'),'Name':'/security-search' if n==1 else '/security-search-'+kind+'-'+stamp,'Config':{'Image':'security-search:v'+version+'-'+stamp,'Labels':{'co.securityops.release':version}},'State':{'Status':state,'Running':state=='running','Paused':False,'Restarting':False,'Health':{'Status':'healthy'},'StartedAt':'2026-09-13'},'RestartCount':0,'Mounts':[]}
def image(n,tags):return {'Id':'sha256:'+format(n,'064x'),'RepoTags':tags,'RepoDigests':[]}
class Retention(unittest.TestCase):
 def setUp(self):self.active=container(1);self.old=container(2,'0.9.41','20260912t180000z','exited');self.images=[image(1,[self.active['Config']['Image']]),image(2,[self.old['Config']['Image']])]
 def test_old_rollback_retired_only_if_stopped(self):
  result,_=m.plan([self.active,self.old],self.images,self.active);self.assertEqual(result['containers'],[self.old['Id']]);self.assertEqual(result['images'],[self.old['Image']])
 def test_running_paused_restarting_never_selected(self):
  for field in ('Running','Paused','Restarting'):
   x=copy.deepcopy(self.old);x['State'][field]=True;plan,_=m.plan([self.active,x],self.images,self.active);self.assertEqual(plan['containers'],[]);self.assertEqual(plan['images'],[])
 def test_all_other_services_preserved(self):
  x=copy.deepcopy(self.old);x['Name']='/security-search-tor-a';r,_=m.plan([self.active,x],self.images,self.active);self.assertEqual(r['containers'],[]);self.assertEqual(r['images'],[])
 def test_future_version_preserved(self):
  x=container(2,'0.9.43','20260913t190001z','exited');r,_=m.plan([self.active,x],[image(2,[x['Config']['Image']])],self.active);self.assertEqual(r,{'containers':[],'images':[]})
 def test_same_version_older_build_included(self):
  x=container(2,'0.9.42','20260912t190000z','exited');r,_=m.plan([self.active,x],[image(2,[x['Config']['Image']])],self.active);self.assertEqual(r['containers'],[x['Id']])
 def test_audit_only_tags_recognized(self):
  t='security-search-audit-only:v0.9.41-20260913t165336z-9ef310155fe3-audit';r,_=m.plan([self.active],[image(3,[t])],self.active);self.assertEqual(r['images'],['sha256:'+format(3,'064x')])
 def test_foreign_tag_or_digest_protects_image(self):
  for tags,digests in (([self.old['Config']['Image'],'another-app:latest'],[]),([],[]),([self.old['Config']['Image']],['some@sha256:x'])):
   x=image(3,tags);x['RepoDigests']=digests;r,_=m.plan([self.active],[x],self.active);self.assertEqual(r['images'],[])
 def test_multiple_owned_old_tags_allowed(self):
  r,_=m.plan([self.active],[image(3,[self.old['Config']['Image'],self.old['Config']['Image']+'-audit'])],self.active);self.assertEqual(len(r['images']),1)
 def test_current_image_kept_even_with_old_alias(self):
  x=image(1,[self.old['Config']['Image']]);r,_=m.plan([self.active],[x],self.active);self.assertEqual(r['images'],[])
 def test_unhealthy_stops_planning(self):
  self.active['State']['Health']['Status']='unhealthy'
  with self.assertRaises(m.CleanupError):m.plan([self.active],[],self.active)
 def test_unknown_current_reference_refused(self):
  self.active['Config']['Image']='some:latest'
  with self.assertRaises(m.CleanupError):m.plan([self.active],[],self.active)
 def test_changed_production_stops_before_removal(self):
  calls=[];other=container(6)
  with patch.object(m,'objects',side_effect=[[self.active,self.old],self.images]),patch.object(m,'inspect',side_effect=[self.active,other]):
   with self.assertRaises(m.CleanupError):m.cleanup(execute=True,run=lambda *a:calls.append(a))
  self.assertEqual(calls,[])
 def test_no_force_and_only_selected_objects(self):
  calls=[]
  def inspect(run,kind,obj):
   return self.active if obj=='security-search' else self.old if kind=='container' else self.images[1]
  def objects(run,kind):return self.images if kind=='image' else [self.active] if calls else [self.active,self.old]
  with patch.object(m,'inspect',side_effect=inspect),patch.object(m,'objects',side_effect=objects),patch.object(m,'current_upload',return_value=None):
   result=m.cleanup(execute=True,run=lambda *a:calls.append(a))
  self.assertEqual(calls,[('container','rm',self.old['Id']),('image','rm','--no-prune',self.old['Image'])]);self.assertTrue(result['complete'])
 def test_plan_has_no_mutations(self):
  calls=[]
  with patch.object(m,'objects',side_effect=[[self.active,self.old],self.images]),patch.object(m,'inspect',return_value=self.active),patch.object(m,'current_upload',return_value=None):result=m.cleanup(run=lambda *a:calls.append(a))
  self.assertEqual(calls,[]);self.assertEqual(result['removed_containers'],[])
 def test_expected_identity_refuses_other_deploy(self):
  with patch.object(m,'inspect',return_value=self.active):
   with self.assertRaises(m.CleanupError):m.cleanup(expected=('wrong','wrong'))
 def test_multi_tag_execution_removes_only_validated_owned_tags_without_force(self):
  row=image(3,[self.old['Config']['Image'],self.old['Config']['Image']+'-audit']);calls=[]
  def inspect(run,kind,obj):return self.active if obj=='security-search' else copy.deepcopy(row)
  def execute(*args):
   self.assertEqual(args[:3],('image','rm','--no-prune'));self.assertIn(args[3],row['RepoTags']);calls.append(args);row['RepoTags'].remove(args[3])
  with patch.object(m,'inspect',side_effect=inspect),patch.object(m,'objects',side_effect=lambda run,kind:[copy.deepcopy(row)] if kind=='image' else [self.active]),patch.object(m,'current_upload',return_value=None):
   result=m.cleanup(execute=True,run=execute)
  self.assertEqual(len(calls),2);self.assertEqual(row['RepoTags'],[]);self.assertEqual(len(result['removed_images']),1);self.assertEqual(len(result['removed_image_tags']),2)
 def test_multi_tag_partial_failure_does_not_force_or_continue(self):
  row=image(3,[self.old['Config']['Image'],self.old['Config']['Image']+'-audit']);calls=[]
  def inspect(run,kind,obj):return self.active if obj=='security-search' else copy.deepcopy(row)
  def execute(*args):
   calls.append(args)
   if len(calls)==2:raise RuntimeError('Docker refused')
   row['RepoTags'].remove(args[3])
  with patch.object(m,'inspect',side_effect=inspect),patch.object(m,'objects',side_effect=lambda run,kind:[copy.deepcopy(row)] if kind=='image' else [self.active]),patch.object(m,'current_upload',return_value=None):
   with self.assertRaises(m.CleanupError):m.cleanup(execute=True,run=execute)
  self.assertEqual(len(calls),2);self.assertEqual(len(row['RepoTags']),1);self.assertNotIn('--force',str(calls))
 def test_lower_version_but_future_build_preserved(self):
  x=container(2,'0.9.40','20260914t190000z','exited');r,_=m.plan([self.active,x],[image(2,[x['Config']['Image']])],self.active);self.assertEqual(r,{'containers':[],'images':[]})
 def archives(self,mutate=None,execute=True,keep=None):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);upload=root/'20260912T160000Z-aaaaaaaaaaaa';upload.mkdir(mode=0o700)
   archive=upload/'securitysearch-v0.9.41-deploy.tar.gz';archive.write_bytes(b'private archive');archive.chmod(0o600)
   side=Path(str(archive)+'.sha256');side.write_text('checksum');side.chmod(0o600)
   unrelated=upload/'other.gz';unrelated.write_bytes(b'keep');source=upload/'securitysearch-v0.9.41';source.mkdir()
   mounts=[]
   if mutate:mounts=mutate(root,upload,archive,side) or []
   with patch.object(m,'open_dir',side_effect=lambda p:os.open(p,os.O_RDONLY|os.O_DIRECTORY|os.O_NOFOLLOW)):
    result=m.gzip_uploads(m.build_key(self.active['Config']['Image']),mounts,execute=execute,root=root,keep_upload=keep)
   self.assertTrue(unrelated.exists());self.assertTrue(source.exists());return result,archive.exists(),side.exists()
 def test_only_recognized_gzip_pair_deleted(self):r,a,s=self.archives();self.assertFalse(a);self.assertFalse(s);self.assertEqual(len(r['removed']),2)
 def test_current_upload_preserved(self):r,a,s=self.archives(keep='20260912T160000Z-aaaaaaaaaaaa');self.assertTrue(a and s);self.assertEqual(r['removed'],[])
 def test_gzip_dry_run_keeps_bytes(self):r,a,s=self.archives(execute=False);self.assertTrue(a and s);self.assertEqual(len(r['eligible']),2)
 def test_linked_gzip_refused(self):
  def change(root,u,a,s):os.link(a,root/'hardlink')
  r,a,s=self.archives(change);self.assertTrue(a and s);self.assertEqual(r['removed'],[])
 def test_symlink_gzip_refused(self):
  def change(root,u,a,s):a.unlink();a.symlink_to(s)
  r,a,s=self.archives(change);self.assertTrue(a and s);self.assertEqual(r['removed'],[])
 def test_mounted_archive_preserved(self):
  r,a,s=self.archives(lambda root,u,a,s:[{'Mounts':[{'Type':'bind','Source':str(a)}]}]);self.assertTrue(a and s);self.assertEqual(r['removed'],[])
 def test_group_writable_upload_preserved(self):
  r,a,s=self.archives(lambda root,u,a,s:u.chmod(0o775));self.assertTrue(a and s);self.assertEqual(r['removed'],[])
 def test_partial_removal_failure_not_success(self):
  with patch.object(m,'inspect',side_effect=[self.active,self.active,self.old]),patch.object(m,'objects',side_effect=[[self.active,self.old],self.images]):
   with self.assertRaises(m.CleanupError):m.cleanup(execute=True,run=lambda *a:(_ for _ in ()).throw(RuntimeError('refused')))
if __name__=='__main__':unittest.main(verbosity=2)
