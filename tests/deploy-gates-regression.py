#!/usr/bin/env python3
"""Offline tests of audit isolation and candidate gate enforcement itself."""
import importlib.util
import json
from pathlib import Path
import subprocess
import tempfile
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('deploy',Path(__file__).parents[1]/'scripts/deploy-ionos.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
for status in [0,1]:
 with tempfile.TemporaryDirectory() as d:
  calls=[]
  def run(*args,capture=False):calls.append(args);return 'a'*64 if args[:2]==('docker','create') else ''
  with patch.object(m,'run',side_effect=run),patch.object(m,'inspect',return_value={'State':{'ExitCode':status}}),patch.object(m.subprocess,'run',return_value=subprocess.CompletedProcess([],status,'fixture audit output')):
   try:m.offline_audit('image',Path(d));assert status==0
   except RuntimeError:assert status!=0
  create=next(x for x in calls if x[:2]==('docker','create'))
  assert create[2:4]==('--network','none') and '-e' not in create and '-v' not in create
  assert calls[-1]==('docker','rm','--force','a'*64)
  assert (Path(d)/'offline-audit.log').read_text()=='fixture audit output'
for report,ok in [({'status':'ok','first_count':3,'pages':[{'number':1}]},True),({'status':'empty','first_count':0},False),({'status':'unavailable','error_class':'RuntimeException'},False)]:
 with tempfile.TemporaryDirectory() as d,patch.object(m,'run',side_effect=['',json.dumps(report)]) as run:
  try:m.live_binternet_gate('fixture',Path(d));assert ok
  except RuntimeError:assert not ok
  assert ('timeout','35')==run.call_args.args[5:7]
  assert json.loads((Path(d)/'binternet-live.json').read_text())==report
print('PASS: audit network isolation, cleanup, full-suite failures and live Binternet failure gates.')
