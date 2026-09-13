#!/usr/bin/env python3
"""Release 36: prefix preservation, narrow renderer scope and runtime acceptance."""
import hashlib,json,unittest
from pathlib import Path
from runtime_history import historical_bytes
R=Path(__file__).resolve().parents[1]
class Release36(unittest.TestCase):
 def test_previous_commands_preserved(self):
  old=json.loads((R/'data/audit-baseline-0.9.35.json').read_text())['commands']
  new=[x for x in (R/'scripts/test.sh').read_text().splitlines() if x.startswith('run_test ')]
  self.assertEqual(len(old),102);self.assertEqual(new[:102],old);self.assertEqual(len(new),130);self.assertEqual(len(set(new)),130)
 def test_version_and_asset(self):
  self.assertEqual((R/'data/release-version.txt').read_text().strip(),'0.9.42')
  self.assertIn('const VERSION = 42;',(R/'data/config.php').read_text())
  for p in ('scripts/deploy-ionos.py','scripts/package-v0.9.42.py'):self.assertIn('ASSET_VERSION = 42',(R/p).read_text())
 def test_previous_transport_png_ui_and_provider_bytes_preserved(self):
  data=json.loads((R/'data/preserved-runtime-0.9.36.json').read_text())
  self.assertIn('lib/thumbnail_png.php',data);self.assertIn('proxy.php',data)
  for p,h in data.items():
   raw=historical_bytes(p)
   if p=='scripts/deployment_state.py':
    allowed=b", 'offline-audit-result.json', 'audit-only.json'"
    self.assertEqual(raw.count(allowed),1)
    raw=raw.replace(allowed,b'',1)
   self.assertEqual(hashlib.sha256(raw).hexdigest(),h,p)
 def test_metadata_selection_has_no_network_cache_or_visitor_state(self):
  s=(R/'lib/image_results.php').read_text()
  for token in ('curl_','apcu_','file_get_contents','file_put_contents','$_COOKIE','$_GET','shell_exec','fsockopen'):self.assertNotIn(token,s)
  self.assertIn('array_slice($image[\'source\'],0,self::MAX_SOURCES)',s)
  self.assertIn('public const PAGE_SIZE = 24;',s);self.assertIn('public const MAX_SOURCES = 32;',s)
 def test_native_and_live_deployment_gates_still_mandatory(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s.split('def main():',1)[1]
  for name in ('offline_audit(image,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)','live_google_gate(candidate,backup)','production_guard.verify('):self.assertIn(name,body)
  self.assertLess(body.index('persist_release('),body.index('committed = True'))
 def test_release_source_requires_renderer_tests(self):
  s=(R/'scripts/package-v0.9.42.py').read_text()
  for p in ('lib/image_results.php','tests/image-preview-regression.php','tests/image-preview-http-regression.py'):self.assertIn(repr(p),s)
if __name__=='__main__':unittest.main(verbosity=2)
