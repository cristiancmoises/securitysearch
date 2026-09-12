#!/usr/bin/env python3
"""Prerequisite control flow and diagnostics; native acceptance remains separate."""
import importlib.util
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import http_runtime as m
R=Path(__file__).resolve().parents[1]
class Runtime(unittest.TestCase):
 def result(self,code=0,out=m.SUCCESS,err=b''):
  return subprocess.CompletedProcess([],code,out,err)
 def test_success_requires_exact_protocol_and_cli_apcu(self):
  with patch.object(m.subprocess,'run',return_value=self.result()) as run:
   m.require_native_runtime(R)
  self.assertEqual(run.call_args.args[0],['php','-d','apc.enable_cli=1',str(R/'tests/native-runtime.php')]);self.assertEqual(run.call_args.kwargs['timeout'],15)
 def test_missing_extensions_clear_and_nonzero(self):
  with patch.object(m.subprocess,'run',return_value=self.result(2,b'',b'NATIVE_RUNTIME_MISSING: curl, mbstring, imagick\n')):
   with self.assertRaisesRegex(m.RuntimePrerequisiteError,'NATIVE_RUNTIME_MISSING: curl, mbstring, imagick'):m.require_native_runtime(R)
 def test_false_success_refused(self):
  for result in [self.result(out=b''),self.result(out=b'skipped\n'),self.result(err=b'warning'),self.result(out=m.SUCCESS*2)]:
   with self.subTest(result=result),patch.object(m.subprocess,'run',return_value=result):
    self.assertRaises(m.RuntimePrerequisiteError,m.require_native_runtime,R)
 def test_unexpected_failure_redacted(self):
  with patch.object(m.subprocess,'run',return_value=self.result(1,b'SECRET',b'SECRET query cookie token')):
   with self.assertRaises(m.RuntimePrerequisiteError) as e:m.require_native_runtime(R)
  self.assertNotIn('SECRET',str(e.exception));self.assertIn('exit=1',str(e.exception))
 def test_missing_executable_redacted(self):
  with patch.object(m.subprocess,'run',side_effect=OSError('SECRET')):
   with self.assertRaises(m.RuntimePrerequisiteError) as e:m.require_native_runtime(R)
  self.assertNotIn('SECRET',str(e.exception))
 def test_timeout_redacted(self):
  with patch.object(m.subprocess,'run',side_effect=subprocess.TimeoutExpired(['SECRET'],15)):
   with self.assertRaises(m.RuntimePrerequisiteError) as e:m.require_native_runtime(R)
  self.assertNotIn('SECRET',str(e.exception));self.assertIn('timed out',str(e.exception))
 def test_failure_precedes_fixture_and_worker_creation(self):
  spec=importlib.util.spec_from_file_location('controller',R/'tests/http-regression.py');controller=importlib.util.module_from_spec(spec);spec.loader.exec_module(controller)
  with patch.object(controller,'require_native_runtime',side_effect=m.RuntimePrerequisiteError('missing')),patch.object(controller.tempfile,'TemporaryDirectory') as temp,patch.object(controller.subprocess,'Popen') as worker:
   self.assertRaises(m.RuntimePrerequisiteError,controller.main)
  temp.assert_not_called();worker.assert_not_called()
 def test_status_failure_has_fixed_context(self):
  for label in ('reset','images','append'):
   m.require_status(200,200,label)
   with self.assertRaisesRegex(AssertionError,'expected=200 actual=500'):m.require_status(500,200,label)
 def test_status_label_does_not_accept_url(self):
  self.assertRaises(ValueError,m.require_status,500,200,'https://secret.invalid/?q=SECRET')
 def test_original_network_denial_and_controller_assertions_retained(self):
  s=(R/'tests/http-regression.py').read_text()
  for marker in ('disable_functions=curl_exec,curl_multi_exec,dns_get_record,gethostbynamel,gethostbyname,fsockopen,pfsockopen,stream_socket_client,socket_connect',"'allow_url_fopen=0'",'OFFLINE_NETWORK_ATTEMPT',"quality=high&format=gif&newer=2025-01-01",'PHP_CLI_SERVER_WORKERS'):
   self.assertIn(marker,s)
  self.assertIn("root/'php-errors.log'",s);self.assertNotIn('error_log=/dev/stderr',s)
 def test_actual_php_no_ini_reports_missing_without_workers(self):
  # Real PHP, deliberately without ini modules; not a simulated native PASS.
  p=subprocess.run(['php','-n',str(R/'tests/native-runtime.php')],capture_output=True,timeout=15)
  self.assertEqual(p.returncode,2);self.assertIn(b'NATIVE_RUNTIME_MISSING:',p.stderr);self.assertIn(b'mbstring',p.stderr)
 def test_real_php_diagnostic_uses_dedicated_file(self):
  with tempfile.TemporaryDirectory() as temp:
   log=Path(temp)/'php-errors.log'
   p=subprocess.run(['php','-n','-d','display_errors=0','-d','log_errors=1','-d','error_log='+str(log),'-r','trigger_error("HTTP_AUDIT_SYNTHETIC_WARNING", E_USER_WARNING); echo "fixture";'],capture_output=True,timeout=15)
   self.assertEqual(p.returncode,0);self.assertEqual(p.stdout,b'fixture');self.assertEqual(p.stderr,b'')
   self.assertIn('HTTP_AUDIT_SYNTHETIC_WARNING',log.read_text());self.assertIn('PHP Warning:',log.read_text())
 def test_real_cli_rejects_native_failure_before_workers(self):
  # A private local shim executes actual PHP -n, forcing the negative case on
  # fully provisioned systems too. It does not supply replacement extensions.
  with tempfile.TemporaryDirectory() as temp:
   import shutil
   php=shutil.which('php');self.assertIsNotNone(php)
   wrapper=Path(temp)/'php';wrapper.write_text('#!/bin/sh\nexec '+__import__('shlex').quote(php)+' -n "$@"\n');wrapper.chmod(0o700)
   env=dict(os.environ,PATH=temp+os.pathsep+os.environ['PATH'])
   p=subprocess.run(['python3','-B',str(R/'tests/http-regression.py')],env=env,capture_output=True,timeout=20)
  self.assertEqual(p.returncode,2);self.assertIn(b'HTTP_AUDIT_PREREQUISITE:',p.stderr);self.assertNotIn(b'Traceback',p.stderr)
if __name__=='__main__':unittest.main(verbosity=2)
