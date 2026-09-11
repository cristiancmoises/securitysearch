#!/usr/bin/env python3
"""Real report validation, with Docker subprocesses mocked; not live search evidence."""
import contextlib,importlib.util,io,json,subprocess,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('google_gate',ROOT/'scripts/deploy-ionos.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def good(page,count=3):return dict(schema=1,version=m.VERSION,provider='google',page=page,status='ok',result_count=count)
def limited(page,retry_after=0):
 r=good(page);r.update(status='unavailable',result_count=None,failure=dict(reason='rate_limited',http_status=429,retry_after=retry_after,url='secret',body='secret'));return r
class Gate(unittest.TestCase):
 def invoke(self,rows,success=False):
  with tempfile.TemporaryDirectory() as t,patch.object(m,'run',side_effect=rows) as call,patch.object(m.time,'sleep') as sleep,contextlib.redirect_stdout(io.StringIO()):
   if success:m.live_google_gate('fixture-candidate',Path(t))
   else:
    with self.assertRaises(RuntimeError):m.live_google_gate('fixture-candidate',Path(t))
   return json.loads((Path(t)/'google-live.json').read_text()),call.call_args_list,sleep.call_args_list
 def test_success_requires_both_and_google_only(self):
  report,calls,sleeps=self.invoke([json.dumps(good('web')),json.dumps(good('images'))],True)
  self.assertEqual(report['status'],'ok');self.assertEqual(len(calls),2);self.assertFalse(sleeps)
  for call in calls:
   args=call.args;self.assertIn('apache',args);self.assertIn('18',args);self.assertIn('google',args);self.assertNotIn('brave',args)
 def test_empty_and_bad_counts_fail_without_second_page_request(self):
  for count in [0,-1,101,True,'3',None,[]]:
   report,calls,sleeps=self.invoke([json.dumps(good('web',count))]);self.assertEqual(len(calls),1);self.assertEqual(report['attempts'][1]['status'],'not_tested');self.assertFalse(sleeps)
 def test_wrong_identity_or_schema_cannot_qualify(self):
  for key,value in [('version','old'),('provider','brave'),('page','images'),('schema',2),('schema',True)]:
   r=good('web');r[key]=value;report,calls,sleeps=self.invoke([json.dumps(r)]);self.assertEqual(report['status'],'unavailable');self.assertFalse(sleeps)
 def test_rate_limit_without_retry_after_uses_bounded_backoff_then_succeeds(self):
  report,calls,sleeps=self.invoke([json.dumps(limited('web')),json.dumps(good('web')),json.dumps(good('images'))],True)
  self.assertEqual(len(calls),3);self.assertEqual([x.args[0] for x in sleeps],[60]);self.assertEqual(report['attempts'][0]['backoff_seconds'],60);self.assertNotIn('secret',json.dumps(report))
 def test_rate_limit_honors_bounded_retry_after(self):
  report,calls,sleeps=self.invoke([json.dumps(limited('web',120)),json.dumps(good('web')),json.dumps(limited('images',999)),json.dumps(good('images'))],True)
  self.assertEqual([x.args[0] for x in sleeps],[120,300]);self.assertEqual(len(calls),4)
 def test_three_rate_limits_fail_and_do_not_probe_images(self):
  report,calls,sleeps=self.invoke([json.dumps(limited('web')),json.dumps(limited('web')),json.dumps(limited('web'))])
  self.assertEqual(len(calls),3);self.assertEqual([x.args[0] for x in sleeps],[60,120]);self.assertEqual(report['attempts'][-1]['status'],'not_tested')
 def test_refusal_and_challenge_never_retry(self):
  for reason,status in [('refused',403),('challenge',403)]:
   r=good('web');r.update(status='unavailable',result_count=None,failure=dict(reason=reason,http_status=status,retry_after=300))
   report,calls,sleeps=self.invoke([json.dumps(r)]);self.assertEqual(len(calls),1);self.assertFalse(sleeps)
 def test_malformed_execution_keeps_sanitized_report(self):
  for raw in ['bad JSON', 'x'*32769, '[]']:
   report,calls,sleeps=self.invoke([raw]);self.assertEqual(report['status'],'unavailable');self.assertNotIn(raw,json.dumps(report));self.assertFalse(sleeps)
 def test_web_only_does_not_qualify(self):
  report,calls,sleeps=self.invoke([json.dumps(good('web')),json.dumps(good('images',0))]);self.assertEqual(report['status'],'unavailable');self.assertEqual(len(calls),2)
 def test_nonzero_cannot_claim_success(self):
  error=subprocess.CalledProcessError(1,['fixture'],output=json.dumps(good('web')))
  report,calls,sleeps=self.invoke([error]);self.assertEqual(report['status'],'unavailable')
 def test_gate_is_opt_in_before_production_stop(self):
  src=(ROOT/'scripts/deploy-ionos.py').read_text();body=src[src.index('def main():'):]
  self.assertIn("os.environ.get('SECURITYSEARCH_VERIFY_GOOGLE') == '1'",body)
  self.assertLess(body.index('live_google_gate('),body.index('stopped = True'))
  for previous in ('offline_audit(', 'live_news_gate(', 'live_binternet_gate('):self.assertLess(body.index(previous),body.index('stopped = True'))
if __name__=='__main__':unittest.main(verbosity=2)
