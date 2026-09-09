#!/usr/bin/env python3
"""Offline credential boundary and four-remote publishing state tests."""
import importlib.util
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('push',Path(__file__).parents[1]/'scripts/push-remotes.py')
p=importlib.util.module_from_spec(spec);spec.loader.exec_module(p)
class Tests(unittest.TestCase):
 def test_askpass_host_bound_and_no_token_in_file(self):
  secret='not-a-real-token-"-$-123'
  with tempfile.TemporaryDirectory() as d:
   script=Path(d)/'askpass';script.write_text(p.ASKPASS)
   self.assertNotIn(secret,script.read_text())
   env=dict(os.environ,SS_AUTH_HOST='codeberg.org',SS_AUTH_USER='berkeley',SS_AUTH_TOKEN=secret)
   def ask(prompt):return subprocess.run(['python3',str(script),prompt],env=env,text=True,capture_output=True)
   self.assertEqual(ask("Username for 'https://codeberg.org/berkeley/securitysearch.git': ").stdout.strip(),'berkeley')
   self.assertEqual(ask("Password for 'https://berkeley@codeberg.org/berkeley/securitysearch.git': ").stdout.strip(),secret)
   for url in ['http://codeberg.org/berkeley/securitysearch.git','https://evil.test/berkeley/securitysearch.git','https://codeberg.org.evil.test/berkeley/securitysearch.git','https://codeberg.org/other/securitysearch.git','https://codeberg.org:8443/berkeley/securitysearch.git']:
    result=ask("Password for '"+url+"': ");self.assertNotEqual(result.returncode,0);self.assertNotIn(secret,result.stdout+result.stderr)
 def test_environment_and_redacted_failure(self):
  with patch.dict(os.environ,{'GIT_TRACE':'1','GIT_DIR':'/wrong','SS_AUTH_TOKEN':'old','GIT_CURL_VERBOSE':'1'}):
   env=p.clean_environment();self.assertNotIn('GIT_TRACE',env);self.assertNotIn('GIT_DIR',env);self.assertNotIn('SS_AUTH_TOKEN',env)
  pub=p.Publisher(Path('/repo'),Path('/safe/askpass'))
  with patch.object(p.subprocess,'run',return_value=subprocess.CompletedProcess([],1,'','SECRET token dump')) as run:
   with self.assertRaises(RuntimeError) as err:pub.git('fetch','url',host='github.com',user='user',token='SECRET')
   self.assertNotIn('SECRET',str(err.exception));self.assertNotIn('SECRET',str(run.call_args.args[0]))
   self.assertEqual(run.call_args.kwargs['env']['SS_AUTH_HOST'],'github.com')
 def test_preflight_does_not_write_remote(self):
  pub=p.Publisher(Path('/repo'),Path('/safe/askpass'));calls=[]
  def git(*args,**auth):
   calls.append(args)
   if args[0]=='rev-parse':return 'a'*40
   if args[0]=='fetch' and auth['host']=='github.com':raise RuntimeError('Offline fixture failure')
   return ''
  pub.git=git
  with self.assertRaises(RuntimeError):pub.preflight('b'*40,['one','two','three','four'])
  self.assertFalse(any(c[0]=='push' and '--dry-run' not in c for c in calls))
 def test_partial_push_is_reported_not_reverted(self):
  pub=p.Publisher(Path('/repo'),Path('/safe/askpass'));calls=[];commit='b'*40
  def git(*args,**auth):
   calls.append((args,auth['host']))
   if args[0]=='push' and auth['host']=='github.com':raise RuntimeError('Unverified')
   if args[0]=='ls-remote':return commit+'\trefs/heads/main'
   return ''
  pub.git=git
  with self.assertRaises(RuntimeError):pub.publish(commit,['one','two','three','four'])
  self.assertEqual(len([c for c,h in calls if c[0]=='push']),4)
  self.assertFalse(any('--force' in c or '--delete' in c for c,h in calls))
if __name__=='__main__':unittest.main()
