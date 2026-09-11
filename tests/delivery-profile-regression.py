#!/usr/bin/env python3
"""Validate actual profile wrapper contracts; no SSH, Docker or provider calls."""
import copy,importlib.util,json,tempfile,unittest,subprocess,contextlib,io,sys
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('profile',ROOT/'scripts/delivery-profile.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def report():return {'schema':1,'samples':[{'sample':i,'status':'ok','asset_version':31,'http_status':200,'curl_errno':0,'wire_body_bytes':9100,'decoded_body_bytes':39315,'ttfb_ms':2.2,'total_ms':2.8,'headers':{'server-timing':'app;dur=0.25','cache-control':'private, no-store','content-encoding':'gzip','x-securitysearch-render':'compiled'}}for i in (1,2,3)]}
class Profile(unittest.TestCase):
 def test_safe_report_retains_only_allowlisted_fields(self):
  r=report();r['secret']='hidden';r['samples'][0]['headers']['set-cookie']='hidden';r['samples'][0]['body']='hidden'
  clean=m.clean_report(json.dumps(r));self.assertNotIn('hidden',json.dumps(clean));self.assertEqual(clean['samples'][0]['headers']['x-securitysearch-render'],'compiled')
 def test_invalid_identity_size_and_numbers_rejected(self):
  for value in ['bad','[]','x'*32769,json.dumps({'schema':True,'samples':[]})]:
   with self.assertRaises((RuntimeError,ValueError)):m.clean_report(value)
  for key,value in [('asset_version',30),('curl_errno',True),('http_status',503),('total_ms',float('nan')),('ttfb_ms',-1)]:
   r=report();r['samples'][0][key]=value
   with self.assertRaises(RuntimeError):m.clean_report(json.dumps(r))
 def test_control_characters_in_headers_rejected(self):
  r=report();r['samples'][0]['headers']['server-timing']='ok\r\nunsafe'
  with self.assertRaises(RuntimeError):m.clean_report(json.dumps(r))
 def test_read_only_fixed_command_and_private_file(self):
  with tempfile.TemporaryDirectory()as t,patch.object(Path,'home',return_value=Path(t)),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],0,json.dumps(report()),''))as run,patch.object(sys,'argv',['delivery-profile.py']),contextlib.redirect_stdout(io.StringIO()):
   self.assertEqual(m.main(),0);args=run.call_args.args[0];self.assertEqual(args,m.SSH+[m.COMMAND]);self.assertNotIn('restart',' '.join(args));self.assertNotIn('stop',' '.join(args))
   files=list((Path(t)/'Downloads').glob('*.json'));self.assertEqual(len(files),1);self.assertEqual(files[0].stat().st_mode&0o777,0o600)
 def test_unavailable_probe_is_reported_not_claimed_pass(self):
  r=report();r['samples'][0]['status']='unavailable';r['samples'][0]['http_status']=503
  with tempfile.TemporaryDirectory()as t,patch.object(Path,'home',return_value=Path(t)),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],2,json.dumps(r),'')),patch.object(sys,'argv',['delivery-profile.py']),contextlib.redirect_stdout(io.StringIO()):self.assertEqual(m.main(),2)
 def test_probe_has_no_variable_destinations_or_search(self):
  src=(ROOT/'scripts/delivery-profile.php').read_text();self.assertIn("curl_init('http://127.0.0.1/')",src);self.assertIn('CURLOPT_FOLLOWLOCATION=>false',src);self.assertIn('CURLOPT_PROXY',src);self.assertIn('$i<3',src);self.assertNotIn('$_GET',src);self.assertNotIn('/web?',src);self.assertNotIn('file_put_contents',src)
if __name__=='__main__':unittest.main(verbosity=2)
