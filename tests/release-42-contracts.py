#!/usr/bin/env python3
import copy,hashlib,importlib.util,json,unittest
from pathlib import Path
from unittest.mock import patch
from runtime_history import historical_bytes,before42_bytes,maintenance_baseline_bytes,provider_maintenance_manifest,assert_reviewed_google_current,assert_reviewed_current
R=Path(__file__).resolve().parents[1]
MAINTENANCE_SCOPE={'api/v1/web.php','api/v1/images.php','api/v1/videos.php','api/v1/news.php','api/v1/music.php','lib/search_guard.php','lib/search_health.php','scraper/brave.php','data/config.php','lib/frontend.php'}
MAINTENANCE_HASHES={
 'api/v1/web.php':('5dd359d392cf5a36ad97f1e09dbaac1e2a6feb409b07b2892ea04b8634fa0bd4', '169d9d9448fa23577fca98842a1dbaac2ea00c76c943e6159d351c97631eacd1'),
 'api/v1/images.php':('dc312bbe5a3e3b7b8f48a85838e910c4d92fed112e84d4de363037b6d997af10', 'ea6b3568ac8c01a100119060e815fefebc18de2e333fc94b680bcdae9e7bb79c'),
 'api/v1/videos.php':('e0855a8336b380ab29dcf43e9f3708ec91032fcf1c6a12d1f366b65288471703', '297ac8ae90d3ecdd81dbf51ddcbfb17feb33a2d3c0b1012cd10bac86e6e1895c'),
 'api/v1/news.php':('c115c4daa86ad3193fd172a67c113a81637ffb4f2567f24ac592abc1cc841f89', '261c41ce4a05681a6fe5efc4c9f0a2b00e42ff1572b5ec644fbdbdfabe92d731'),
 'api/v1/music.php':('c2def111f33c9124542a7fb0b047c0a39e3e155f3df7d7b4681ad7bbe25ed04a', '3171934c81d5b84d49ef763e25ced962b74b6ae1be00941c27cea1384b81fa40'),
 'lib/search_guard.php':('36e4c90ede740e9ad44bbbc6b8bb0b08584193c0539132183fb0308fcca7980b', '3c4da36ed8c6bb22f7202a3869ac528fe9a74c2f129e8a84db6d4586d0ebbb23'),
 'lib/search_health.php':('7f85d9fe0bbf72faeca2e4f8f6a5e704e8d2c612d225818d04c1dab0b7854806', 'f93dfcaf5cc35011e42077aa7f37e257f52a8ccb8897d10082286e3a3f470d5c'),
 'scraper/brave.php':('d2ab2faea830dc5f71776f7b56ec9349cb13946576ecfc316b92de933d9c1e15', 'cef95e1e541ffa3d36c6b05b033ba275f49a479b72e4a7f7a7a92c89714772a3'),
 'data/config.php':('b43a80837bc2bae0f5bdf8a846e265fcfa02b8f61acfc060daec70b5dabd7015', '45f2a63de263d4574c33c65f9837bcc8251ba09ef5216fdde71eb1698aaf28c9'),
 'lib/frontend.php':('1681f3a0d1b3803898274a4634fa683c5f586f539363247b90bcd9efd7fa3c2b', 'b48f013691de8e1a375ffb00cbcdcc91ee0885112e2d1127a8aef16d9317ed5c')}
class Release42(unittest.TestCase):
 def test_provider_maintenance_has_exact_ten_reversible_files(self):
  d=provider_maintenance_manifest()
  self.assertEqual(set(d['files']),{'api/v1/web.php','api/v1/images.php','api/v1/videos.php','api/v1/news.php','api/v1/music.php','lib/search_guard.php','lib/search_health.php','scraper/brave.php','data/config.php','lib/frontend.php'})
 def test_privacy_cleanup_has_exact_two_current_only_files(self):
  d=provider_maintenance_manifest()['current_only_files']
  self.assertEqual(set(d),{'scraper/google_cse.php','scraper/cara.php'})
  self.assertEqual(d['scraper/cara.php']['old_sha256'],'922075bc5898f6bf2b17a21924abf1e7713053d53dc05b3cdf093c072248ccb6')
  self.assertEqual(d['scraper/cara.php']['new_sha256'],'90560e9a0b0e90ce452ededeaf91ccaecbe21d2ac14d3a90d762ec6ef6fbde11')
 def test_cara_privacy_cleanup_is_not_reconstructed(self):
  for reader in (maintenance_baseline_bytes,before42_bytes,historical_bytes):
   with self.subTest(reader=reader.__name__),self.assertRaises(ValueError):reader('scraper/cara.php')
 def test_unknown_maintenance_record_metadata_fails_closed(self):
  real=Path.read_text;manifest=json.loads(real(R/'data/runtime-changes-provider-reliability.json'))
  for name in manifest['files']:
   for level in ('record','edit'):
    changed=copy.deepcopy(manifest);row=changed['files'][name]
    target=row if level=='record' else row['edits'][0];target['unexpected']='unreviewed'
    with self.subTest(name=name,level=level),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(changed) if p==R/'data/runtime-changes-provider-reliability.json' else real(p,*a,**k)):
     with self.assertRaises(ValueError):provider_maintenance_manifest()
 def test_missing_maintenance_scope_fails_closed(self):
  real=Path.read_text;manifest=json.loads(real(R/'data/runtime-changes-provider-reliability.json'))
  for scope in ('files','current_only_files','added_files'):
   for name in manifest[scope]:
    changed=copy.deepcopy(manifest);del changed[scope][name]
    with self.subTest(scope=scope,name=name),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(changed) if p==R/'data/runtime-changes-provider-reliability.json' else real(p,*a,**k)):
     with self.assertRaises(ValueError):provider_maintenance_manifest()
 def test_cara_current_pin_and_archived_metadata_fail_closed(self):
  name='scraper/cara.php';legacy='922075bc5898f6bf2b17a21924abf1e7713053d53dc05b3cdf093c072248ccb6'
  assert_reviewed_current(self,name,legacy)
  real=Path.read_bytes
  with patch.object(Path,'read_bytes',lambda p:real(p)+b'unreviewed' if p==R/name else real(p)):
   with self.assertRaises(AssertionError):assert_reviewed_current(self,name,legacy)
  with self.assertRaises(AssertionError):assert_reviewed_current(self,name,'0'*64)
  with self.assertRaises(ValueError):assert_reviewed_current(self,'scraper/unreviewed.php',legacy)
  read=Path.read_text
  for archive in ('data/preserved-runtime-0.9.37.json','data/preserved-runtime-0.9.38.json'):
   def wrong_archive(p,*a,**k):
    text=read(p,*a,**k)
    if p==R/archive:
     d=json.loads(text);d[name]='0'*64;return json.dumps(d)
    return text
   with self.subTest(archive=archive),patch.object(Path,'read_text',wrong_archive):
    with self.assertRaises(AssertionError):assert_reviewed_current(self,name,legacy)
 def test_current_version_and_asset(self):self.assertEqual((R/'data/release-version.txt').read_text(),'0.9.42\n');self.assertIn('const VERSION = 42;',(R/'data/config.php').read_text())
 def test_original_124_prefix_preserved(self):
  a=json.loads((R/'data/audit-commands-0.9.41.json').read_text())['commands'];b=json.loads((R/'data/audit-commands-0.9.42.json').read_text())['commands'];self.assertEqual(b[:124],a);self.assertEqual(len(b),130);self.assertEqual(len(set(b)),130)
 def test_exact_runtime_manifest_scope(self):
  d=json.loads((R/'data/runtime-changes-0.9.42.json').read_text());self.assertEqual(d['baseline_tree'],'50ff829dc83f875cf3eb66dae7ca60e31e800961');self.assertEqual(set(d['files']),{'data/config.php','lib/frontend.php','template/search-actions.html','scripts/deployment_state.py','static/style.css','static/home-base.css'})
  for name,row in d['files'].items():self.assertEqual(hashlib.sha256(before42_bytes(name)).hexdigest(),row['old_sha256'])
 def test_230_historical_hashes_and_two_reviewed_current_files_verified(self):
  d=json.loads((R/'data/preserved-runtime-0.9.38.json').read_text());self.assertEqual(len(d),232)
  for name,h in d.items():
   if name in ('scraper/google_cse.php','scraper/cara.php'):assert_reviewed_current(self,name,h)
   else:self.assertEqual(hashlib.sha256(historical_bytes(name)).hexdigest(),h,name)
 def test_modified_adapter_routing_cannot_claim_history(self):
  real=Path.read_bytes
  with patch.object(Path,'read_bytes',lambda p:real(p)+b'changed' if p==R/'lib/frontend.php' else real(p)):
   with self.assertRaises(ValueError):historical_bytes('lib/frontend.php')
 def test_maintenance_tampering_cannot_claim_history(self):
  real=Path.read_bytes
  for name in MAINTENANCE_SCOPE:
   with self.subTest(name=name),patch.object(Path,'read_bytes',lambda p:real(p)+b'unreviewed' if p==R/name else real(p)):
    with self.assertRaises(ValueError):before42_bytes(name)
 def test_google_privacy_cleanup_is_not_reconstructed(self):
  for reader in (before42_bytes,historical_bytes):
   with self.subTest(reader=reader.__name__),self.assertRaises(ValueError):reader('scraper/google_cse.php')
 def test_unknown_maintenance_scope_fails_closed(self):
  real=Path.read_text
  manifest=json.loads(real(R/'data/runtime-changes-provider-reliability.json'))
  for scope in ('files','current_only_files','added_files'):
   changed=copy.deepcopy(manifest);changed[scope]['unexpected.php']={}
   with self.subTest(scope=scope),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(changed) if p==R/'data/runtime-changes-provider-reliability.json' else real(p,*a,**k)):
    with self.assertRaises(ValueError):before42_bytes('scraper/brave.php')
 def test_exact_maintenance_fingerprints_and_reconstructed_v42(self):
  d=provider_maintenance_manifest();self.assertEqual(set(d['files']),MAINTENANCE_SCOPE);self.assertEqual(set(MAINTENANCE_HASHES),MAINTENANCE_SCOPE)
  for name,row in d['files'].items():
   with self.subTest(name=name):
    old,new=MAINTENANCE_HASHES[name];self.assertEqual((row['old_sha256'],row['new_sha256']),(old,new))
    current=(R/name).read_bytes();original=maintenance_baseline_bytes(name)
    self.assertEqual(hashlib.sha256(current).hexdigest(),new);self.assertEqual(hashlib.sha256(original).hexdigest(),old)
    for edit in row['edits']:self.assertEqual(original.count(edit['before'].encode()),1);self.assertEqual(current.count(edit['after'].encode()),1)
  self.assertEqual(hashlib.sha256((R/'lib/brave_data.php').read_bytes()).hexdigest(),'2d7eaf6b17a1bf9ba53343fb786bb87ebda1c79951f8062d225c4c24edf7dc8f')
 def test_unreviewed_edit_metadata_fails_closed(self):
  real=Path.read_text;manifest=json.loads(real(R/'data/runtime-changes-provider-reliability.json'))
  for name in MAINTENANCE_SCOPE:
   for bad in ('empty','ambiguous','preimage','old_hash','new_hash'):
    changed=copy.deepcopy(manifest);row=changed['files'][name]
    if bad=='empty':row['edits'][0]['after']=''
    elif bad=='ambiguous':row['edits'][0]['after']='\n'
    elif bad=='preimage':row['edits'][0]['before']+='unreviewed'
    else:row[bad.replace('_hash','_sha256')]='0'*64
    with self.subTest(name=name,bad=bad),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(changed) if p==R/'data/runtime-changes-provider-reliability.json' else real(p,*a,**k)):
     with self.assertRaises(ValueError):before42_bytes(name)
 def test_google_current_pin_and_archived_metadata_fail_closed(self):
  legacy=json.loads((R/'data/preserved-runtime-0.9.38.json').read_text())['scraper/google_cse.php'];assert_reviewed_google_current(self,legacy)
  real=Path.read_bytes
  with patch.object(Path,'read_bytes',lambda p:real(p)+b'unreviewed' if p==R/'scraper/google_cse.php' else real(p)):
   with self.assertRaises(AssertionError):assert_reviewed_google_current(self,legacy)
  with self.assertRaises(AssertionError):assert_reviewed_google_current(self,'0'*64)
  read=Path.read_text
  def wrong_archive(p,*a,**k):
   text=read(p,*a,**k)
   if p==R/'data/preserved-runtime-0.9.38.json':
    d=json.loads(text);d['scraper/google_cse.php']='0'*64;return json.dumps(d)
   return text
  with patch.object(Path,'read_text',wrong_archive):
   with self.assertRaises(AssertionError):assert_reviewed_google_current(self,legacy)
 def test_privacy_manifest_has_only_hash_metadata_not_historical_source(self):
  text=(R/'data/runtime-changes-provider-reliability.json').read_text();d=json.loads(text)
  for name in ('scraper/google_cse.php','scraper/cara.php'):
   self.assertNotIn(name,d['files']);self.assertEqual(set(d['current_only_files'][name]),{'old_sha256','new_sha256','policy'})
  self.assertNotRegex(text,r'(?i)https?://[^\s"\x27]*[?&](?:cse_tok|key|token)=')
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
