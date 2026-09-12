#!/usr/bin/env python3
"""Current identity, preserved audit inventory and guarded request-path changes."""
import ast,hashlib,json,unittest
from pathlib import Path
R=Path(__file__).resolve().parents[1]
class Release(unittest.TestCase):
 def test_all_previous_commands_preserved(self):
  old=json.loads((R/'data/audit-baseline-0.9.33.json').read_text())['commands'];new=[x for x in (R/'scripts/test.sh').read_text().splitlines() if x.startswith('run_test ')]
  self.assertEqual(len(old),93);self.assertEqual(new[:93],old);self.assertEqual(len(new),119);self.assertEqual(len(new),len(set(new)))
 def test_current_version_and_asset(self):
  self.assertEqual((R/'data/release-version.txt').read_text().strip(),'0.9.40');self.assertIn('const VERSION = 40;',(R/'data/config.php').read_text())
  self.assertIn("VERSION = '0.9.40'",(R/'scripts/deploy-ionos.py').read_text());self.assertIn('ASSET_VERSION = 40',(R/'scripts/deploy-ionos.py').read_text())
  self.assertIn('ASSET_VERSION = 40',(R/'scripts/package-v0.9.40.py').read_text())
  self.assertIn('to 119.',(R/'README.md').read_text());self.assertIn('totalizam 119.',(R/'README.pt-BR.md').read_text())
 def test_provider_privacy_and_ui_hashes_unchanged(self):
  for path,sha in json.loads((R/'data/preserved-runtime-0.9.32.json').read_text()).items():self.assertEqual(hashlib.sha256((R/path).read_bytes()).hexdigest(),sha,path)
 def test_cache_hit_precedes_proxy_import(self):
  s=(R/'favicon.php').read_text();self.assertLess(s.index('favicon_cache::read('),s.index('require_once __DIR__."/lib/curlproxy.php"'));self.assertLess(s.index('$this->send_icon($icon)'),s.index('favicon_policy::acquire('))
 def test_new_cache_has_no_network_or_query_storage(self):
  s=(R/'lib/favicon_cache.php').read_text()
  for token in ('curl_','apcu_','file_put_contents','$_GET','$_COOKIE','shell_exec','system('):self.assertNotIn(token,s)
 def test_deployment_gates_retained(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s.split('def main():',1)[1]
  for name in ('offline_audit(image,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)','live_google_gate(candidate,backup)','production_guard.verify('):self.assertIn(name,body)
  self.assertLess(body.index('persist_release('),body.index('committed = True'))
if __name__=='__main__':unittest.main(verbosity=2)
