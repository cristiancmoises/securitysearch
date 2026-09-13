#!/usr/bin/env python3
"""Release scope, immutable old audit inventory, and native gate identity."""
import hashlib
import json
from pathlib import Path
from runtime_history import historical_bytes
import unittest
from audit_fixture import load
R=Path(__file__).resolve().parents[1]
class Release38(unittest.TestCase):
 def test_previous_110_commands_stay_in_order(self):
  old=json.loads((R/'data/audit-baseline-0.9.37.json').read_text())['commands']
  new=[x for x in (R/'scripts/test.sh').read_text().splitlines() if x.startswith('run_test ')]
  self.assertEqual(len(old),110);self.assertEqual(new[:110],old);self.assertEqual(len(new),130)
  self.assertEqual(load('audit_evidence').source_commands(R),[x[9:] for x in new])
 def test_visitor_runtime_unchanged(self):
  entries=json.loads((R/'data/preserved-runtime-0.9.38.json').read_text());self.assertEqual(len(entries),232)
  old=json.loads((R/'data/preserved-runtime-0.9.37.json').read_text())
  self.assertEqual(set(old)-set(entries),{'scripts/deployment_state.py'})
  self.assertEqual({p:v for p,v in old.items() if p in entries},entries)
  for path,digest in entries.items():self.assertEqual(hashlib.sha256(historical_bytes(path)).hexdigest(),digest,path)
 def test_manual_benchmark_unchanged(self):
  paths=[p for p in R.rglob('*') if p.is_file() and '.git' not in p.parts and 'benchmark' in p.name and p.suffix=='.fish']
  self.assertTrue(any(hashlib.sha256(p.read_bytes()).hexdigest()=='bd6f2207de8d8d36b41f47f391b4cba54c7d264fb6dbd5605de244f6a57f2e1d' for p in paths))
 def test_version_asset_and_archive_required_files(self):
  self.assertEqual((R/'data/release-version.txt').read_text(),'0.9.42\n');self.assertIn('const VERSION = 42;',(R/'data/config.php').read_text())
  s=(R/'scripts/package-v0.9.42.py').read_text()
  for name in ('scripts/audit_evidence.py','scripts/audit_stream.py','tests/audit-only-regression.py','tests/audit-stream-regression.py','tests/audit-evidence-regression.py','tests/release-38-contracts.py','data/audit-commands-0.9.40.json'):self.assertIn(repr(name),s)
 def test_audit_limits_remain_explicit_and_not_optional(self):
  s=(R/'scripts/audit_stream.py').read_text()
  for item in ('MAX_BYTES = 8 * 1024 * 1024','MAX_SECONDS = 1800','os.O_EXCL','os.O_NOFOLLOW','start_new_session=True','stderr=subprocess.STDOUT','time.monotonic()','output_limit','os.fsync'):self.assertIn(item,s)
  self.assertNotIn('assert ',s)
 def test_audit_only_returns_before_normal_workflow(self):
  s=(R/'scripts/deploy-ionos.py').read_text();main=s.split('def main():',1)[1]
  self.assertLess(main.index('return audit_only(name, old)'),main.index("raw = run('docker','exec'"))
  body=s.split('def audit_only(',1)[1].split('def main():',1)[0]
  for token in ('live_google_gate(','live_news_gate(','live_binternet_gate(','predeploy_cleanup(','persist_release(','effective-config.json'):self.assertNotIn(token,body)
  for token in ('offline_audit(image,backup)','live_google_gate(candidate,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)'):self.assertIn(token,main)
 def test_native_runtime_never_emulated(self):
  s=(R/'tests/native-runtime.php').read_text()
  for token in ('mbstring','imagick','apcu','curl','dom','xml'):self.assertIn(token,s)
  self.assertIn('tests/native-runtime.php', '\n'.join(load('audit_evidence').source_commands(R)))
if __name__=='__main__':unittest.main(verbosity=2)
