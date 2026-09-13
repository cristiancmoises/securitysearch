#!/usr/bin/env python3
import hashlib,importlib.util,json,unittest
from pathlib import Path
from unittest.mock import patch
from runtime_history import historical_bytes,before42_bytes
R=Path(__file__).resolve().parents[1]
class Release42(unittest.TestCase):
 def test_current_version_and_asset(self):self.assertEqual((R/'data/release-version.txt').read_text(),'0.9.42\n');self.assertIn('const VERSION = 42;',(R/'data/config.php').read_text())
 def test_original_124_prefix_preserved(self):
  a=json.loads((R/'data/audit-commands-0.9.41.json').read_text())['commands'];b=json.loads((R/'data/audit-commands-0.9.42.json').read_text())['commands'];self.assertEqual(b[:124],a);self.assertEqual(len(b),130);self.assertEqual(len(set(b)),130)
 def test_exact_runtime_manifest_scope(self):
  d=json.loads((R/'data/runtime-changes-0.9.42.json').read_text());self.assertEqual(d['baseline_tree'],'50ff829dc83f875cf3eb66dae7ca60e31e800961');self.assertEqual(set(d['files']),{'data/config.php','lib/frontend.php','template/search-actions.html','scripts/deployment_state.py','static/style.css','static/home-base.css'})
  for name,row in d['files'].items():self.assertEqual(hashlib.sha256(before42_bytes(name)).hexdigest(),row['old_sha256'])
 def test_all_historical_runtime_hashes_verified(self):
  d=json.loads((R/'data/preserved-runtime-0.9.38.json').read_text());self.assertEqual(len(d),232)
  for name,h in d.items():self.assertEqual(hashlib.sha256(historical_bytes(name)).hexdigest(),h,name)
 def test_modified_adapter_routing_cannot_claim_history(self):
  real=Path.read_bytes
  with patch.object(Path,'read_bytes',lambda p:real(p)+b'changed' if p==R/'lib/frontend.php' else real(p)):
   with self.assertRaises(ValueError):historical_bytes('lib/frontend.php')
 def test_fixed_service_origin_no_typo(self):
  s=(R/'scraper/skunkyart.php').read_text();self.assertIn('https://skunkyart.securityops.co',s);self.assertNotIn('securiyops.co',s);self.assertIn('extends service_search',s)
 def test_native_shortcut_and_registry(self):
  self.assertIn('value="skunkyart"',(R/'template/search-actions.html').read_text());self.assertIn('"skunkyart" => "DeviantArt via SkunkyArt"',(R/'lib/frontend.php').read_text())
 def test_docs_disclose_retiring_rollback(self):
  s=(R/'docs/RETENTION.md').read_text();self.assertIn('rollback',s);self.assertIn('No automatic pre-build cleanup',s);self.assertIn('never --force',s)
 def test_current_wrappers_and_docs(self):
  for name in ('scripts/deploy-ionos.fish','scripts/push-securitysearch.fish','README.md','README.pt-BR.md'):self.assertIn('0.9.42',(R/name).read_text())
 def test_no_mandatory_audit_only_in_readme_command(self):
  text=(R/'README.md').read_text().split('```fish',1)[1].split('```',1)[0];self.assertNotIn('--audit-only',text)
if __name__=='__main__':unittest.main(verbosity=2)
