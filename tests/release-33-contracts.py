#!/usr/bin/env python3
"""Release identity, preserved previous audit inventory, and runtime preservation."""
import ast,hashlib,json,unittest
from pathlib import Path
R=Path(__file__).resolve().parents[1]
class Release(unittest.TestCase):
 def test_all_previous_commands_preserved_in_order(self):
  prior=json.loads((R/'data/audit-baseline-0.9.32.json').read_text())['commands']
  current=[s for s in (R/'scripts/test.sh').read_text().splitlines() if s.startswith('run_test ')]
  self.assertEqual(len(prior),89);self.assertEqual(current[:89],prior);self.assertEqual(len(current),119);self.assertEqual(len(set(current)),119)
 def test_release_and_asset(self):
  self.assertEqual((R/'data/release-version.txt').read_text().strip(),'0.9.40');self.assertIn('const VERSION = 40;',(R/'data/config.php').read_text())
  s=(R/'scripts/deploy-ionos.py').read_text();self.assertIn("VERSION = '0.9.40'",s);self.assertIn('ASSET_VERSION = 40',s)
  package=(R/'scripts/package-v0.9.40.py').read_text();self.assertIn('ASSET_VERSION = 40',package);self.assertIn("'asset_version': ASSET_VERSION",package)
 def test_existing_runtime_hashes(self):
  manifest=json.loads((R/'data/preserved-runtime-0.9.32.json').read_text())
  for path,expected in manifest.items():self.assertEqual(hashlib.sha256((R/path).read_bytes()).hexdigest(),expected,path)
 def test_final_guard_precedes_first_production_mutation(self):
  body=(R/'scripts/deploy-ionos.py').read_text().split('def main():',1)[1]
  check=body.rindex('production_guard.verify(');self.assertLess(check,body.index('stopped = True'))
  self.assertGreater(check,body.index('live_google_gate('));self.assertGreater(check,body.index('rollback.chmod('))
 def test_no_hidden_gate_relaxation(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s.split('def main():',1)[1]
  for name in ('offline_audit(image,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)','live_google_gate(candidate,backup)'):self.assertIn(name,body)
  self.assertIn('production_snapshot = production_guard.snapshot(old)',body)
 def test_no_force_prune_added(self):
  tree=ast.parse((R/'scripts/predeploy_cleanup.py').read_text())
  for n in ast.walk(tree):
   if isinstance(n,ast.Call) and isinstance(n.func,ast.Name) and n.func.id=='run':
    values=[x.value for x in n.args if isinstance(x,ast.Constant)]
    for forbidden in ('--force','--volumes','prune'):self.assertNotIn(forbidden,values)
if __name__=='__main__':unittest.main(verbosity=2)
