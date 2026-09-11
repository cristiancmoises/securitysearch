#!/usr/bin/env python3
"""Real report validation, with Docker subprocesses mocked; not live search evidence."""
import contextlib,importlib.util,io,json,os,errno,shutil,subprocess,tempfile,unittest
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
  report,calls,sleeps=self.invoke([json.dumps(limited('web',120)),json.dumps(good('web')),json.dumps(limited('images',300)),json.dumps(good('images'))],True)
  self.assertEqual([x.args[0] for x in sleeps],[120,300]);self.assertEqual(len(calls),4)
 def test_retry_after_beyond_budget_is_not_shortened(self):
  report,calls,sleeps=self.invoke([json.dumps(limited('web',999))])
  self.assertEqual(len(calls),1);self.assertFalse(sleeps)
  self.assertEqual(report['attempts'][0]['retry_deferred_seconds'],999)
  self.assertEqual(report['status'],'unavailable')
 def test_write_is_atomic_private_and_has_no_temporary_files(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);identity=m.google_backup_identity(root)
   m.write_google_evidence(root,{'status':'old'},identity)
   m.write_google_evidence(root,{'status':'new'},identity)
   self.assertEqual(json.loads((root/'google-live.json').read_text()),{'status':'new'})
   self.assertEqual((root/'google-live.json').stat().st_mode & 0o777,0o600)
   self.assertEqual(sorted(p.name for p in root.iterdir()),['google-live.json'])
 def test_missing_backup_refused_before_any_probe(self):
  with tempfile.TemporaryDirectory() as t,patch.object(m,'run') as call:
   with self.assertRaisesRegex(RuntimeError,'stage=check-directory errno=2'):
    m.live_google_gate('fixture-candidate',Path(t)/'missing')
   self.assertFalse(call.called);self.assertFalse((Path(t)/'missing').exists())
 def test_deleted_directory_after_backoff_stops_before_retry(self):
  with tempfile.TemporaryDirectory() as t,patch.object(m,'run',return_value=json.dumps(limited('web'))) as call:
   root=Path(t)/'backup';root.mkdir()
   with patch.object(m.time,'sleep',side_effect=lambda unused:shutil.rmtree(root)):
    with self.assertRaisesRegex(RuntimeError,'stage=check-directory errno=2'):
     m.live_google_gate('fixture-candidate',root)
   self.assertEqual(call.call_count,1);self.assertFalse(root.exists())
 def test_replaced_directory_rejected_by_identity(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t)/'backup';root.mkdir();identity=m.google_backup_identity(root)
   root.rename(Path(t)/'retained-original');root.mkdir()
   with self.assertRaisesRegex(RuntimeError,'identity changed'):
    m.write_google_evidence(root,{'status':'ok'},identity)
   self.assertFalse((root/'google-live.json').exists())
 def test_linked_directory_or_report_not_followed(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);other=root/'other';other.mkdir();linked=root/'linked';linked.symlink_to(other)
   with self.assertRaisesRegex(RuntimeError,'not a real directory'):m.google_backup_identity(linked)
   identity=m.google_backup_identity(other);target=root/'target';target.write_text('untouched')
   (other/'google-live.json').symlink_to(target)
   with self.assertRaisesRegex(RuntimeError,'not a regular file'):
    m.write_google_evidence(other,{'status':'ok'},identity)
   self.assertEqual(target.read_text(),'untouched')
 def test_replace_failure_preserves_previous_evidence(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t);identity=m.google_backup_identity(root)
   m.write_google_evidence(root,{'status':'old'},identity)
   with patch.object(m.os,'replace',side_effect=OSError(errno.ENOSPC,'SECRET')):
    with self.assertRaisesRegex(RuntimeError,'stage=write-report file=google-live.json errno=28') as failure:
     m.write_google_evidence(root,{'status':'new'},identity)
    self.assertNotIn('SECRET',str(failure.exception))
   self.assertEqual(json.loads((root/'google-live.json').read_text()),{'status':'old'})
   self.assertEqual(sorted(p.name for p in root.iterdir()),['google-live.json'])
 def test_probe_os_error_preserves_errno_without_secret_filename(self):
  error=FileNotFoundError(errno.ENOENT,'secret','/private/SECRET-TOKEN')
  report,calls,sleeps=self.invoke([error])
  self.assertEqual(len(calls),1);self.assertFalse(sleeps)
  self.assertEqual(report['attempts'][0]['errno'],2)
  self.assertEqual(report['attempts'][0]['os_error'],'FileNotFoundError')
  self.assertNotIn('SECRET',json.dumps(report));self.assertNotIn('/private',json.dumps(report))
 def test_error_metadata_does_not_reflect_paths_or_tokens(self):
  error=FileNotFoundError(errno.ENOENT,'secret','/private/SECRET-TOKEN')
  text=m.safe_deployment_error(error)
  self.assertIn('errno=2',text);self.assertNotIn('SECRET',text);self.assertNotIn('/private',text)
  self.assertIn('executable=docker',m.safe_deployment_error(FileNotFoundError(errno.ENOENT,'missing','docker')))
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
