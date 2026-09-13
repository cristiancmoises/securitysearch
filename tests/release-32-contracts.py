#!/usr/bin/env python3
"""Version, unchanged critical runtime, complete audit and cleanup-order contracts."""
import ast,hashlib,json,re,unittest
from pathlib import Path
from runtime_history import historical_bytes
R=Path(__file__).resolve().parents[1]
class Contract(unittest.TestCase):
 def test_previous_85_commands_preserved_in_order(self):
  old=json.loads((R/'data/audit-baseline-0.9.31.json').read_text())['commands'];new=[x for x in (R/'scripts/test.sh').read_text().splitlines() if x.startswith('run_test ')]
  self.assertEqual(len(old),85);self.assertEqual(new[:85],old);self.assertEqual(len(new),124);self.assertEqual(len(new),len(set(new)))
 def test_version_and_asset(self):
  self.assertEqual((R/'data/release-version.txt').read_text().strip(),'0.9.41');self.assertIn('const VERSION = 41;',(R/'data/config.php').read_text())
  self.assertIn("VERSION = '0.9.41'",(R/'scripts/deploy-ionos.py').read_text());self.assertIn('ASSET_VERSION = 41',(R/'scripts/deploy-ionos.py').read_text())
 def test_critical_runtime_unchanged(self):
  hashes=json.loads((R/'data/preserved-runtime-0.9.32.json').read_text())
  self.assertGreaterEqual(len(hashes),12)
  for path,expected in hashes.items():self.assertEqual(hashlib.sha256(historical_bytes(path)).hexdigest(),expected,path)
 def test_cleanup_before_build_after_validation_under_lock(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s[s.index('def main():'):]
  self.assertLess(body.index('fcntl.flock('),body.index('cleanup_before_build('));self.assertLess(body.index('cleanup_before_build('),body.index("run('docker','build'"));self.assertLess(body.index('An existing source/configuration mount'),body.index('cleanup_before_build('))
 def test_json_receipt_written_before_transaction_commits(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s[s.index('def main():'):]
  self.assertLess(body.index('persist_release('),body.index('committed = True'));self.assertIn('if stopped and not committed:',body)
 def test_no_generic_prune_or_force_in_cleanup(self):
  s=(R/'scripts/predeploy_cleanup.py').read_text();tree=ast.parse(s)
  calls=[n for n in ast.walk(tree) if isinstance(n,ast.Call) and isinstance(n.func,ast.Name) and n.func.id=='run']
  for call in calls:
   vals=[a.value for a in call.args if isinstance(a,ast.Constant)];self.assertNotIn('prune',vals);self.assertNotIn('--force',vals);self.assertNotIn('--volumes',vals)
if __name__=='__main__':unittest.main(verbosity=2)
