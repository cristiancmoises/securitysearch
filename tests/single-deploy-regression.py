#!/usr/bin/env python3
"""New gate validation and transaction ordering; Docker/provider operations are doubles."""
import importlib.util,json,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
R=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('deploy42',R/'scripts/deploy-ionos.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def good():return {'provider':'skunkyart','page':'images','status':'ok','first_count':2,'pages':[{'number':1,'count':2,'has_next':True},{'number':2,'count':1,'has_next':False}]}
class Deploy(unittest.TestCase):
 def gate(self,data,success=True):
  with tempfile.TemporaryDirectory() as t,patch.object(m,'run',return_value=json.dumps(data)) as calls:
   if success:m.live_skunkyart_gate('candidate',Path(t))
   else:
    with self.assertRaises(RuntimeError):m.live_skunkyart_gate('candidate',Path(t))
   return json.loads((Path(t)/'skunkyart-live.json').read_text()),calls.call_args_list
 def test_real_provider_identity_required(self):
  d=good();d['provider']='binternet';r,c=self.gate(d,False);self.assertEqual(r['status'],'unavailable')
 def test_success_has_two_bounded_pages(self):
  r,c=self.gate(good());self.assertEqual(r['status'],'ok');self.assertEqual(len(r['pages']),2);self.assertEqual(len(c),2)
 def test_empty_first_page_fails(self):d=good();d['first_count']=0;self.gate(d,False)
 def test_http_error_never_success(self):d=good();d['status']='unavailable';d['secret']='DO_NOT_LOG';r,_=self.gate(d,False);self.assertNotIn('DO_NOT_LOG',json.dumps(r))
 def test_missing_pagination_fails(self):d=good();d['pages']=d['pages'][:1];self.gate(d,False)
 def test_single_exhausted_page_valid(self):d=good();d['pages']=d['pages'][:1];d['pages'][0]['has_next']=False;self.gate(d)
 def test_counts_and_boolean_types_required(self):
  for value in ('2',True,-1,101):d=good();d['first_count']=value;self.gate(d,False)
 def test_bad_second_page_fails(self):d=good();d['pages'][1]['number']=3;self.gate(d,False)
 def test_only_one_full_audit_in_normal_path(self):
  main=(R/'scripts/deploy-ionos.py').read_text().split('def main():',1)[1];self.assertEqual(main.count('offline_audit(image,backup)'),1)
 def test_no_prebuild_cleanup_invocation(self):
  main=(R/'scripts/deploy-ionos.py').read_text().split('def main():',1)[1];self.assertNotIn('cleanup_before_build(',main);self.assertNotIn('run_cleanup(',main)
 def test_gates_precede_service_stop(self):
  main=(R/'scripts/deploy-ionos.py').read_text().split('def main():',1)[1];stop=main.index('stopped = True')
  for text in ('offline_audit(image,backup)','live_skunkyart_gate(candidate,backup)','live_binternet_gate(candidate,backup)','live_google_gate(candidate,backup)','live_news_gate(candidate,backup)'):self.assertLess(main.index(text),stop)
 def test_cleanup_never_part_of_cutover_rollback_transaction(self):
  text=(R/'scripts/deploy-ionos.py').read_text();self.assertNotIn('postdeploy_cleanup',text);self.assertIn('persist_release(backup',text)
if __name__=='__main__':unittest.main(verbosity=2)
