#!/usr/bin/env python3
"""Release identity, audit preservation and image-proxy scope guards."""
import hashlib,json,unittest
from pathlib import Path
R=Path(__file__).resolve().parents[1]
class Release35(unittest.TestCase):
 def test_previous_commands_preserved(self):
  old=json.loads((R/'data/audit-baseline-0.9.34.json').read_text())['commands']
  new=[x for x in (R/'scripts/test.sh').read_text().splitlines() if x.startswith('run_test ')]
  self.assertEqual(len(old),97);self.assertEqual(new[:97],old);self.assertEqual(len(new),119);self.assertEqual(len(new),len(set(new)))
 def test_version_and_asset(self):
  self.assertEqual((R/'data/release-version.txt').read_text().strip(),'0.9.40');self.assertIn('const VERSION = 40;',(R/'data/config.php').read_text());self.assertIn('ASSET_VERSION = 40',(R/'scripts/deploy-ionos.py').read_text());self.assertIn('ASSET_VERSION = 40',(R/'scripts/package-v0.9.40.py').read_text())
 def test_providers_and_privacy_files_preserved(self):
  for p,h in json.loads((R/'data/preserved-runtime-0.9.32.json').read_text()).items():self.assertEqual(hashlib.sha256((R/p).read_bytes()).hexdigest(),h,p)
 def test_no_network_cache_or_request_inputs_in_png_helper(self):
  s=(R/'lib/thumbnail_png.php').read_text()
  for v in ('$_GET','$_COOKIE','curl_','apcu_','shell_exec','file_put_contents'):self.assertNotIn(v,s)
 def test_scope_before_existing_conversion(self):
  s=(R/'proxy.php').read_text();self.assertIn('!$still_preview && $_GET["s"] === "thumb" && $resize_mime === "image/png"',s);self.assertLess(s.index('thumbnail_png::eligible'),s.index('$imagick_resource_limits = ['));self.assertEqual(s.count('($payload["http"]["code"] ?? null) !== 200'),2)
 def test_all_deployment_acceptance_retained(self):
  s=(R/'scripts/deploy-ionos.py').read_text();body=s.split('def main():',1)[1]
  for name in ('offline_audit(image,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)','live_google_gate(candidate,backup)','production_guard.verify('):self.assertIn(name,body)
  self.assertLess(body.index('persist_release('),body.index('committed = True'))
if __name__=='__main__':unittest.main(verbosity=2)
