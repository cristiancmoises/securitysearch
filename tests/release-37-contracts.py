#!/usr/bin/env python3
"""Release 37 narrows scope to operator packaging and audit diagnostics."""
import hashlib,json,unittest
from pathlib import Path
from runtime_history import historical_bytes
R=Path(__file__).resolve().parents[1]
class Release37(unittest.TestCase):
 def test_all_106_previous_commands_remain_in_order(self):
  old=json.loads((R/'data/audit-baseline-0.9.36.json').read_text())['commands']
  new=[x for x in (R/'scripts/test.sh').read_text().splitlines() if x.startswith('run_test ')]
  self.assertEqual(len(old),106);self.assertEqual(new[:106],old);self.assertEqual(len(new),119);self.assertEqual(len(set(new)),119)
 def test_visitor_runtime_is_byte_identical_to_v36(self):
  data=json.loads((R/'data/preserved-runtime-0.9.38.json').read_text())
  self.assertGreater(len(data),150)
  for name,digest in data.items():self.assertEqual(hashlib.sha256(historical_bytes(name)).hexdigest(),digest,name)
 def test_release_advances_without_asset_or_provider_change(self):
  self.assertEqual((R/'data/release-version.txt').read_text(),'0.9.40\n')
  self.assertIn('const VERSION = 40;',(R/'data/config.php').read_text())
  for name in ('scripts/deploy-ionos.py','scripts/package-v0.9.40.py'):self.assertIn('ASSET_VERSION = 40',(R/name).read_text())
 def test_codec_has_explicit_bounded_integrity(self):
  s=(R/'scripts/operator_payload.py').read_text()
  for token in ('MAX_JSON = 16 * 1024 * 1024','MAX_PACKED = 8 * 1024 * 1024','MAX_TOTAL = 16 * 1024 * 1024','decoder.eof','decoder.unused_data','decoder.unconsumed_tail','duplicate JSON key','resource integrity','chunk integrity'):
   self.assertIn(token,s)
  self.assertNotIn('assert ',s)
 def test_controller_requires_native_runtime_before_temp_tree(self):
  s=(R/'tests/http-regression.py').read_text();body=s.split('def main():',1)[1]
  self.assertLess(body.index('require_native_runtime(ROOT)'),body.index('with tempfile.TemporaryDirectory'))
  self.assertIn('sys.exit(2)',body);self.assertNotIn('error_log=/dev/stderr',body)
 def test_new_source_archive_requires_new_gates(self):
  s=(R/'scripts/package-v0.9.40.py').read_text()
  for name in ('scripts/operator_payload.py','tests/http_runtime.py','tests/operator-payload-regression.py','tests/http-runtime-regression.py','tests/release-37-contracts.py'):self.assertIn(repr(name),s)
 def test_native_live_and_rollback_guards_are_still_mandatory(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s.split('def main():',1)[1]
  for name in ('offline_audit(image,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)','live_google_gate(candidate,backup)','production_guard.verify('):self.assertIn(name,body)
  self.assertLess(body.index('persist_release('),body.index('committed = True'))
if __name__=='__main__':unittest.main(verbosity=2)
