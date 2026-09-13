#!/usr/bin/env python3
"""Release 41 preserves verified history while checking each reviewed runtime delta."""
import ast,hashlib,importlib.util,json,re,unittest
from pathlib import Path
from unittest.mock import patch
from audit_fixture import load
from runtime_history import historical_bytes
R=Path(__file__).resolve().parents[1]
class Release41(unittest.TestCase):
 def test_all_119_prior_commands_are_an_exact_prefix(self):
  old=json.loads((R/'data/audit-commands-0.9.40.json').read_text())['commands'];new=load('audit_evidence').source_commands(R)
  self.assertEqual(len(old),119);self.assertEqual(new[:119],old);self.assertEqual(len(new),124);self.assertEqual(len(set(new)),124)
 def test_current_version_and_assets(self):
  self.assertEqual((R/'data/release-version.txt').read_text(),'0.9.41\n');self.assertIn('const VERSION = 41;',(R/'data/config.php').read_text())
  self.assertEqual(load('deploy-ionos').ASSET_VERSION,41);self.assertEqual(load('package-v0.9.41').ASSET_VERSION,41)
 def test_exact_six_reviewed_runtime_paths(self):
  manifest=json.loads((R/'data/runtime-changes-0.9.41.json').read_text());self.assertEqual(manifest['baseline_tree'],'b0ab9966f02dd0c41ad8f936347b64cee5642c9f')
  self.assertEqual(set(manifest['files']),set('data/config.php docker/apache/fast-home.conf docker/apache/http/httpd.conf docker/apache/https/httpd.conf lib/image_results.php static/images-infinite.js'.split()))
  for name,row in manifest['files'].items():
   self.assertEqual(hashlib.sha256((R/name).read_bytes()).hexdigest(),row['new_sha256'])
   self.assertEqual(hashlib.sha256(historical_bytes(name)).hexdigest(),row['old_sha256'])
 def test_all_232_historical_runtime_hashes_still_verified(self):
  hashes=json.loads((R/'data/preserved-runtime-0.9.38.json').read_text());self.assertEqual(len(hashes),232)
  for name,digest in hashes.items():self.assertEqual(hashlib.sha256(historical_bytes(name)).hexdigest(),digest,name)
 def test_modified_runtime_cannot_claim_approved_history(self):
  original=Path.read_bytes
  def tampered(path):return original(path)+b'\nUNREVIEWED' if path==R/'lib/image_results.php' else original(path)
  with patch.object(Path,'read_bytes',tampered):
   with self.assertRaises(ValueError):historical_bytes('lib/image_results.php')
 def test_original_manual_benchmark_is_byte_identical(self):
  self.assertEqual(hashlib.sha256((R/'tools/secops-web-benchmark-v3.fish').read_bytes()).hexdigest(),'bd6f2207de8d8d36b41f47f391b4cba54c7d264fb6dbd5605de244f6a57f2e1d')
 def test_standalone_benchmark_contains_exact_reviewed_python(self):
  text=(R/'tools/secops-web-benchmark-v4.fish').read_text();a=text.index("set -l program '")+len("set -l program '");b=text.index("\n'",a)
  python=text[a:b].replace("\\'","'").replace('\\\\','\\');self.assertEqual(python,(R/'tools/ttfb_benchmark.py').read_text());compile(python,'benchmark','exec')
 def test_benchmark_never_runs_network_in_deployment_or_audit(self):
  commands=load('audit_evidence').source_commands(R)
  self.assertEqual([x for x in commands if 'ttfb_benchmark' in x],['python3 tools/ttfb_benchmark.py --self-test'])
  source=(R/'scripts/deploy-ionos.py').read_text();self.assertNotIn('ttfb_benchmark',source);self.assertNotIn('benchmark-v4',source)
 def test_scroll_path_has_no_forced_geometry_read(self):
  code=(R/'static/images-infinite.js').read_text();self.assertNotIn('getBoundingClientRect',code);self.assertNotIn('offsetWidth',code)
  for marker in ('viewportObserver','if (!inViewport) return;','MAX_CARDS = 480','MAX_BYTES = 1024 * 1024','credentials: \'same-origin\''):self.assertIn(marker,code)
 def test_live_acceptance_and_no_fallback_still_required(self):
  code=(R/'scripts/deploy-ionos.py').read_text();main=code.split('def main():',1)[1]
  for marker in ('offline_audit(image,backup)','live_google_gate(candidate,backup)','live_news_gate(candidate,backup)','live_binternet_gate(candidate,backup)','production_guard.verify('):self.assertIn(marker,main)
  self.assertIn('max_attempts = 3',code);self.assertIn("data.get('provider') != 'google'",code)
 def test_packager_requires_new_tests_tools_and_old_evidence(self):
  source=(R/'scripts/package-v0.9.41.py').read_text()
  for name in ('data/runtime-changes-0.9.41.json','data/audit-commands-0.9.40.json','data/audit-commands-0.9.41.json','tests/image-label-regression.php','tests/home-representation-http-regression.py','tests/release-41-contracts.py','tools/ttfb_benchmark.py','tools/secops-web-benchmark-v4.fish'):
   self.assertTrue((R/name).is_file(),name);self.assertIn(repr(name),source)
 def test_current_documentation_resolves(self):
  for name in ('README.md','README.pt-BR.md','docs/INDEX.md','docs/OPERATIONS-0.9.41.md','docs/OPERATIONS-0.9.41.pt-BR.md','docs/PUBLISHING.md','docs/PUBLISHING.pt-BR.md','docs/RELEASE-0.9.41.md','docs/PERFORMANCE-0.9.41.md','docs/AUDIT-0.9.41.md'):
   p=R/name;self.assertTrue(p.is_file(),name)
   for link in re.findall(r'\[[^\]]*\]\(([^)]+)\)',p.read_text()):
    if '://' not in link and not link.startswith(('#','mailto:')):self.assertTrue((p.parent/link.split('#',1)[0]).exists(),name+': '+link)
 def test_default_home_has_no_reported_foreign_origins(self):
  # This checks shipped code, not the provenance of an external Lighthouse trace.
  for name in ('template/home.html','lib/home_styles.php','lib/page_renderer.php','static/images-infinite.js'):
   text=(R/name).read_text().lower()
   for host in ('intermotors.pl','salesmanago.pl'):self.assertNotIn(host,text)
 def test_current_operator_docs_preserve_ssh_port(self):
  for name in ('README.md','README.pt-BR.md','docs/OPERATIONS-0.9.41.md','docs/OPERATIONS-0.9.41.pt-BR.md','docs/UPGRADE-0.9.30-to-0.9.41.md'):
   text=(R/name).read_text();self.assertIn('5119',text);self.assertNotIn('5124',text)
 def test_source_launchers_verify_downloaded_bytes_before_execution(self):
  for name in ('scripts/deploy-ionos.fish','scripts/push-securitysearch.fish'):
   text=(R/name).read_text();self.assertIn('sha256sum --check',text);self.assertIn('--no-execute',text);self.assertIn('0.9.41',text)
 def test_only_ordinary_versioned_release_entrypoints(self):
  self.assertEqual(load('publish-v0.9.41').VERSION,'0.9.41')
  for name in ('docs/PUBLISHING.md','docs/PUBLISHING.pt-BR.md'):
   text=(R/name).read_text();self.assertIn('0.9.41-publish-four-remotes.fish',text);self.assertIn('DEPLOY COMPLETE',text)
if __name__=='__main__':unittest.main(verbosity=2)
