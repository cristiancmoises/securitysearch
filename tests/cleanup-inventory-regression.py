#!/usr/bin/env python3
"""Batch query contract + chronological rollback regression; simulated Docker."""
import copy,importlib.util,json,unittest
from pathlib import Path
R=Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('fixtures',R/'tests/predeploy-cleanup-regression.py');t=importlib.util.module_from_spec(s);s.loader.exec_module(t)
m=t.m
class Inventory(unittest.TestCase):
 def setUp(self):
  self.rows=[t.img(i) for i in range(1,121)];self.calls=[]
 def docker_run(self,*args):
  self.calls.append(args)
  if args[1]=='ls':return '\n'.join(r['Id'] for r in self.rows)
  return json.dumps([r for r in self.rows if r['Id'] in args[2:]][::-1])
 def test_120_images_need_nine_calls_not_121(self):
  actual=m.objects(self.docker_run,'image');self.assertEqual(actual,self.rows)
  self.assertEqual(len(self.calls),9);self.assertTrue(all(len(c[2:])<=16 for c in self.calls if c[1]=='inspect'))
 def test_empty_inventory_no_inspects(self):
  self.rows=[];self.assertEqual(m.objects(self.docker_run,'image'),[]);self.assertEqual(len(self.calls),1)
 def test_duplicate_list_ids_deduplicated(self):
  self.assertEqual(m.objects(lambda *a:(self.docker_run(*a)+'\n'+self.rows[0]['Id']) if a[1]=='ls' else self.docker_run(*a),'image'),self.rows)
 def test_invalid_list_id_stops_before_inspect(self):
  calls=[]
  def run(*a):calls.append(a);return '--evil'
  with self.assertRaises(m.CleanupError):m.objects(run,'container')
  self.assertEqual(len(calls),1)
 def test_truncated_batch_cannot_qualify(self):
  def run(*a):return self.docker_run(*a) if a[1]=='ls' else json.dumps([self.rows[0]])
  with self.assertRaises(m.CleanupError):m.objects(run,'image')
 def test_duplicate_batch_member_cannot_qualify(self):
  def run(*a):return self.docker_run(*a) if a[1]=='ls' else json.dumps([self.rows[0]]*len(a[2:]))
  with self.assertRaises(m.CleanupError):m.objects(run,'image')
 def test_unknown_batch_member_cannot_qualify(self):
  def run(*a):
   if a[1]=='ls':return self.docker_run(*a)
   rows=json.loads(self.docker_run(*a));rows[0]=t.img(9999);return json.dumps(rows)
  with self.assertRaises(m.CleanupError):m.objects(run,'image')
 def test_failed_batch_is_not_partial_success(self):
  def run(*a):
   if a[1]=='inspect':raise m.CleanupError('unavailable')
   return self.docker_run(*a)
  with self.assertRaises(m.CleanupError):m.objects(run,'image')
 def test_malformed_and_wrong_type_batch_rejected(self):
  for raw in ('bad', '{}', 'null', '[null]', '[true]'):
   with self.subTest(raw=raw),self.assertRaises(m.CleanupError):m.objects(lambda *a:self.rows[0]['Id'] if a[1]=='ls' else raw,'image')
 def test_inventory_cap(self):
  with self.assertRaises(m.CleanupError):m.objects(lambda *a:'\n'.join(t.cid(n) for n in range(4001)),'container')
class Rollback(unittest.TestCase):
 def setUp(self):self.cs,self.ims=t.fixture()
 def plan(self):return m.make_plan(self.cs,self.ims,self.cs[0],'0.9.33',t.NOW)
 def test_retirement_not_creation_defines_newest_rollback(self):
  self.cs[2]['Name']='/security-search-rollback-20260911t200000z';self.cs[2]['Created']='2026-09-01T10:00:00Z'
  self.cs[3]['Name']='/security-search-rollback-20260910t200000z';self.cs[3]['Created']='2026-09-09T10:00:00Z'
  p=self.plan();self.assertIn(t.cid(3),p['protected_containers']);self.assertNotIn(t.iid(3),{r['id'] for r in p['images']})
 def test_tied_retirement_dates_both_protected(self):
  self.cs[3]['Name']=self.cs[2]['Name'];p=self.plan();self.assertTrue({t.cid(3),t.cid(4)}<=set(p['protected_containers']))
 def test_malformed_retirement_date_preserved(self):
  self.cs[2]['Name']='/security-search-rollback-20261340t999999z';p=self.plan();self.assertIn(t.cid(3),p['protected_containers'])
 def test_future_retirement_date_preserved(self):
  self.cs[2]['Name']='/security-search-rollback-20270911t200000z';p=self.plan();self.assertTrue({t.cid(3),t.cid(4)}<=set(p['protected_containers']))
 def test_unrecognized_rollback_is_not_deleted(self):
  self.cs[2]['Name']='/security-search-rollback-manual';p=self.plan();self.assertNotIn(t.cid(3),{r['id'] for r in p['containers']})
if __name__=='__main__':unittest.main(verbosity=2)
