#!/usr/bin/env python3
"""Exact application delta, audit-prefix preservation and publication requirements."""
import hashlib
import json
import re
from pathlib import Path
import unittest
from runtime_history import historical_bytes
from audit_fixture import load
R=Path(__file__).resolve().parents[1]
class Release40(unittest.TestCase):
 def test_all_previous_commands_in_order(self):
  old=json.loads((R/'data/audit-commands-0.9.38.json').read_text())['commands']
  new=load('audit_evidence').source_commands(R)
  self.assertEqual(len(old),115);self.assertEqual(new[:115],old);self.assertEqual(len(new),124);self.assertEqual(len(set(new)),124)
 def test_exact_three_runtime_file_edits(self):
  delta=json.loads((R/'data/search-state-changes-0.9.40.json').read_text())['files']
  self.assertEqual(set(delta),{'lib/frontend.php','web.php','music.php'})
  for path,record in delta.items():
   self.assertEqual(hashlib.sha256(historical_bytes(path)).hexdigest(),record['old_sha256'])
   self.assertNotEqual(record['new_sha256'],record['old_sha256'])
 def test_all_other_visitor_runtime_hashes_preserved(self):
  hashes=json.loads((R/'data/preserved-runtime-0.9.38.json').read_text())
  changed={'lib/frontend.php','web.php','music.php'}
  self.assertTrue(changed <= set(hashes));self.assertEqual(len(hashes),232)
  for path,digest in hashes.items():
   self.assertEqual(hashlib.sha256(historical_bytes(path)).hexdigest(),digest,path)
  self.assertEqual(len(set(hashes)-changed),229)
 def test_normalized_query_is_the_only_oracle_input(self):
  s=(R/'web.php').read_text()
  self.assertNotIn('$_GET["s"]',s)
  for token in ('$oracle->check_query($get["s"])','$oracle->generate_response($get["s"])','htmlspecialchars($get["s"])'):self.assertIn(token,s)
 def test_music_buffer_precedes_output_and_provider_execution(self):
  s=(R/'music.php').read_text();self.assertEqual(s.count('ob_start();'),1)
  self.assertLess(s.index('ob_start();'),s.index('new bot_protection'))
  self.assertLess(s.index('ob_start();'),s.index('search_guard::run'))
 def test_native_fixture_requires_real_dependencies(self):
  s=(R/'tests/http-regression.py').read_text()
  self.assertLess(s.index('require_native_runtime(ROOT)'),s.index('with tempfile.TemporaryDirectory'))
  for token in ("'/music?s=0&scraper=sc'","'/web?s%5B%5D=bad&scraper=brave'",'output_buffering=0'):self.assertIn(token,s)
 def test_version_asset_and_package(self):
  self.assertEqual((R/'data/release-version.txt').read_text(),'0.9.41\n')
  self.assertIn('const VERSION = 41;',(R/'data/config.php').read_text())
  s=(R/'scripts/package-v0.9.41.py').read_text()
  for path in ('tests/search-state-regression.php','tests/search-state-http-regression.py','tests/release-40-contracts.py','tests/runtime_history.py','data/search-state-changes-0.9.40.json','data/audit-commands-0.9.40.json'):self.assertIn(repr(path),s)
 def test_acceptance_and_cleanup_guards_remain(self):
  s=(R/'scripts/deploy-ionos.py').read_text();main=s.split('def main():',1)[1]
  for token in ('offline_audit(image,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)','live_google_gate(candidate,backup)','production_guard.verify('):self.assertIn(token,main)
  self.assertLess(main.index('return audit_only(name, old)'),main.index("raw = run('docker','exec'"))
 def test_verified_v30_baseline_and_direct_upgrade_are_documented(self):
  record=json.loads((R/'data/upgrade-baseline-0.9.30.json').read_text())
  self.assertEqual(record['tree'],'488b01ae481e8e4e95dad3743c2a175c43065c97')
  self.assertEqual(record['commit'],'331cf550d8b279736126e7362fb699f99fc2bc66')
  self.assertEqual(record['target_release'],'0.9.40');self.assertFalse(record['ionos_queried'])
  for name in ('README.md','README.pt-BR.md'):
   text=(R/name).read_text();self.assertIn(record['tree'],text);self.assertIn('--prepare-only',text)
   self.assertIn('119',text);self.assertIn('DEPLOY COMPLETE',text);self.assertIn('v0.9.30',text)
 def test_current_docs_are_packaged_and_not_only_old_release_notes(self):
  required=['docs/INDEX.md','docs/UPGRADE-0.9.30-to-0.9.40.md','docs/OPERATIONS-0.9.40.md','docs/OPERATIONS-0.9.40.pt-BR.md','docs/PUBLISHING.md','docs/PUBLISHING.pt-BR.md','docs/TROUBLESHOOTING-0.9.40.md','docs/RELEASE.md','data/upgrade-baseline-0.9.30.json']
  code=(R/'scripts/package-v0.9.41.py').read_text()
  for name in required:self.assertTrue((R/name).is_file(),name);self.assertIn(repr(name),code)
 def test_local_links_in_current_docs_resolve(self):
  names=['README.md','README.pt-BR.md','docs/INDEX.md','docs/UPGRADE-0.9.30-to-0.9.40.md','docs/OPERATIONS-0.9.40.md','docs/OPERATIONS-0.9.40.pt-BR.md','docs/PUBLISHING.md','docs/PUBLISHING.pt-BR.md','docs/TROUBLESHOOTING-0.9.40.md','docs/RELEASE.md','docs/RELEASE-0.9.40.md','docs/AUDIT-0.9.40.md','docs/PERFORMANCE-0.9.40.md']
  for name in names:
   p=R/name
   for link in re.findall(r'\[[^\]]*\]\(([^)]+)\)',p.read_text()):
    if '://' in link or link.startswith(('#','mailto:')):continue
    self.assertTrue((p.parent/link.split('#',1)[0]).exists(),name+': '+link)
 def test_publishing_docs_cover_all_four_remotes_and_partial_failure(self):
  for name in ('docs/PUBLISHING.md','docs/PUBLISHING.pt-BR.md'):
   text=(R/name).read_text()
   for host in ('git.securityops.co','git.securityops.com.br','--host','--verify-only','--prepare-only','DEPLOY COMPLETE'):self.assertIn(host,text)
   self.assertIn('0.9.41-publish-four-remotes.fish',text)
   self.assertNotIn('0.9.39-publish-four-remotes.fish',text)
if __name__=='__main__':unittest.main(verbosity=2)
