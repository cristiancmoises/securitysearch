#!/usr/bin/env python3
import csv, importlib.util, json, pathlib, tempfile, unittest
ROOT=pathlib.Path(__file__).resolve().parents[1]
s=importlib.util.spec_from_file_location('delivery_analysis',ROOT/'tools/analyze-delivery.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
class Analysis(unittest.TestCase):
 def setUp(self):self.tmp=tempfile.TemporaryDirectory();self.addCleanup(self.tmp.cleanup);self.path=pathlib.Path(self.tmp.name)/'results.csv'
 def put(self,rows):
  with self.path.open('w',newline='') as f:
   w=csv.DictWriter(f,fieldnames=['phase','round','site','status','ttfb_ms','total_ms','curl_exit','http_code','error','requested_url']);w.writeheader();w.writerows(rows)
 def row(self,site,i,total,status='eligible'):return dict(phase='measured',round=i,site=site,status=status,total_ms=total,ttfb_ms=total-1,curl_exit=0,http_code=200,error='SECRET',requested_url='https://SECRET')
 def test_pair_intersection_not_own_sample(self):
  self.put([self.row('securityops.co',1,100),self.row('securityops.co',2,300),self.row('4get.ca',1,80)]);r=m.analyze(self.path);self.assertEqual(r['paired_securityops_vs_4get']['n'],1);self.assertEqual(r['paired_securityops_vs_4get']['difference_of_paired_medians_ms']['total_ms'],20)
 def test_no_secret_or_ranking(self):
  self.put([self.row('securityops.co',1,100)]);r=json.dumps(m.analyze(self.path));self.assertNotIn('SECRET',r);self.assertIsNone(m.analyze(self.path)['paired_securityops_vs_4get'])
 def test_shared_failure_not_diagnosis(self):
  self.put([self.row('securityops.co',1,100,'transport_error'),self.row('4get.ca',1,100,'http_error')]);r=m.analyze(self.path);self.assertEqual(len(r['shared_failure_rounds']['1']),2);self.assertIsNone(r['sites']['4get.ca']['median_ms']['tcp_ms'])
 def test_duplicate_refused(self):
  row=self.row('securityops.co',1,100);self.put([row,row]);self.assertRaises(ValueError,m.analyze,self.path)
 def test_nonfinite_eligible_refused(self):
  self.put([self.row('securityops.co',1,float('inf'))]);self.assertRaises(ValueError,m.analyze,self.path)
 def test_symlink_refused(self):
  target=pathlib.Path(self.tmp.name)/'x';target.write_text('secret');self.path.symlink_to(target);self.assertRaises(ValueError,m.analyze,self.path)
if __name__=='__main__':unittest.main(verbosity=2)
