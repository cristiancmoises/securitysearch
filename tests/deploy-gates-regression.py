#!/usr/bin/env python3
"""Offline tests of audit isolation and candidate gate enforcement itself."""
import importlib.util
import contextlib
import io
import json
from pathlib import Path
import subprocess
import tempfile
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('deploy',Path(__file__).parents[1]/'scripts/deploy-ionos.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
for status in [0,1]:
 with tempfile.TemporaryDirectory() as d:
  calls=[];stderr=io.StringIO()
  def run(*args,capture=False):calls.append(args);return 'a'*64 if args[:2]==('docker','create') else ''
  with contextlib.redirect_stderr(stderr),patch.object(m,'run',side_effect=run),patch.object(m,'inspect',return_value={'State':{'ExitCode':status}}),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],status,'fixture audit output')):
   try:m.offline_audit('image',Path(d));assert status==0
   except RuntimeError:assert status!=0
  create=next(x for x in calls if x[:2]==('docker','create'))
  assert create[2:4]==('--network','none') and '-e' not in create and '-v' not in create
  assert calls[-1]==('docker','rm','--force','a'*64)
  assert (Path(d)/'offline-audit.log').read_text()=='fixture audit output'
  assert ('fixture audit output' in stderr.getvalue()) == (status != 0)
# The complete private log is retained; only a bounded tail goes to the terminal.
with tempfile.TemporaryDirectory() as d:
 calls=[];stderr=io.StringIO()
 output='first-line-must-not-be-printed\n'+'line\n'*140+'LAST-FAILURE\n'
 def run(*args,capture=False):calls.append(args);return 'b'*64 if args[:2]==('docker','create') else ''
 with contextlib.redirect_stderr(stderr),patch.object(m,'run',side_effect=run),patch.object(m,'inspect',return_value={'State':{'ExitCode':1}}),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],1,output)):
  try:m.offline_audit('image',Path(d));raise AssertionError('Failed audit allowed')
  except RuntimeError as error:assert 'production is unchanged' in str(error)
 assert 'LAST-FAILURE' in stderr.getvalue() and 'first-line-must-not-be-printed' not in stderr.getvalue()
 assert (Path(d)/'offline-audit.log').read_text()==output
 assert calls[-1]==('docker','rm','--force','b'*64)
# Early failed suites remain visible even when successful suites fill the tail.
structured = (
 '=== OFFLINE TEST: php tests/regression.php ===\n'
 'PHP Fatal error: expected current Redlib navigation\n'
 'FAILED (exit 255): php tests/regression.php\n'
 '=== OFFLINE TEST: python3 tests/http-regression.py ===\n'
 'AssertionError: fallback origin order\n'
 'FAILED (exit 1): python3 tests/http-regression.py\n'
 '=== OFFLINE TEST: python3 passing-test.py ===\n' + 'PASS irrelevant\n' * 200 +
 '=== OFFLINE AUDIT SUMMARY ===\nCommands passed: 45; failed: 2.\n'
)
excerpt = m.audit_failure_excerpt(structured)
assert 'PHP Fatal error: expected current Redlib navigation' in excerpt
assert 'AssertionError: fallback origin order' in excerpt
assert 'PASS irrelevant' not in excerpt and 'Commands passed: 45; failed: 2.' in excerpt
assert m.audit_failure_excerpt(None) == '[The audit container produced no output.]'
assert len(m.audit_failure_excerpt('x'*100000)) <= 24000
many = ''.join('=== OFFLINE TEST: command-'+str(i)+' ===\n'+('details\n'*500)+
               'FAILED (exit 2): command-'+str(i)+'\n' for i in range(10))
assert len(m.audit_failure_excerpt(many)) <= 24000
assert '4 additional failures' in m.audit_failure_excerpt(many)
with tempfile.TemporaryDirectory() as d:
 stderr=io.StringIO()
 def run(*args,capture=False):return 'c'*64 if args[:2]==('docker','create') else ''
 with contextlib.redirect_stderr(stderr),patch.object(m,'run',side_effect=run),patch.object(m,'inspect',return_value={'State':{'ExitCode':1}}),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],1,structured)):
  try:m.offline_audit('image',Path(d));raise AssertionError('Failed audit allowed')
  except RuntimeError:pass
 assert (Path(d)/'offline-audit.log').read_text()==structured
 assert 'expected current Redlib navigation' in stderr.getvalue()
 assert 'fallback origin order' in stderr.getvalue()
print('PASS: bounded early-failure excerpts, truncation, unstructured fallback and full-log retention.')
for report,ok in [({'status':'ok','first_count':3,'pages':[{'number':1}]},True),({'status':'empty','first_count':0},False),({'status':'unavailable','error_class':'RuntimeException'},False)]:
 with tempfile.TemporaryDirectory() as d,patch.object(m,'run',side_effect=['',json.dumps(report)]) as run:
  try:m.live_binternet_gate('fixture',Path(d));assert ok
  except RuntimeError:assert not ok
  assert ('timeout','35')==run.call_args.args[5:7]
  assert json.loads((Path(d)/'binternet-live.json').read_text())==report
print('PASS: audit network isolation, cleanup, full-suite failures and live Binternet failure gates.')
