#!/usr/bin/env python3
import copy,hashlib,importlib.util,json,unittest
from html.parser import HTMLParser
from pathlib import Path
from unittest.mock import patch
import runtime_history as runtime
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
 def test_inline_luma_change_has_exact_reviewed_scope(self):
  self.assertTrue(hasattr(runtime,'inline_luma_maintenance_manifest'),'Inline LUMA runtime-history layer is required')
  d=runtime.inline_luma_maintenance_manifest()
  self.assertEqual((d['release'],d['baseline_commit'],d['baseline_tree']),('0.9.42','055731829a4c1c0ddca94ef500c3f1a4faaebec3','df601cc0c8bf9a9f534dda6b38f879d164248775'))
  self.assertEqual(set(d['files']),{'docker/apache/fast-home.conf','images.php','lib/build_view_resources.php','lib/frontend.php','lib/image_results.php','lib/luma_search.php','lib/page_renderer.php','lib/security_headers.php','lib/theme_picker.php','luma.php','static/images-infinite.js','template/search-actions.html'})
  self.assertEqual(set(d['added_files']),{'scraper/luma.php','static/themes/GoroDaimon.css','static/profile-placeholder.svg'})
 def test_inline_luma_reconstructs_the_reviewed_055_runtime(self):
  self.assertTrue(hasattr(runtime,'inline_luma_baseline_bytes'),'Inline LUMA runtime-history layer is required')
  expected={'docker/apache/fast-home.conf':'67cb03d4cb37c667c3ce3ae8af706e86607540092583883d11e574dab1f27e08',
   'images.php':'b642f1138f0100294bc1e10807966b62e0da06d726aae915a928981b0c0d5791',
   'lib/build_view_resources.php':'20ba6984889ee7464c0603ab2aaf30f2625ed32f2b4ed5525d3fe5900aea1772',
   'lib/frontend.php':'b48f013691de8e1a375ffb00cbcdcc91ee0885112e2d1127a8aef16d9317ed5c',
   'lib/image_results.php':'89c20b29051046fa3c5ed0dc63c5cbae92cd59fad96add65c9edac4c7d8d57f1',
   'lib/luma_search.php':'230f25e3c8fd948251a43897683a7b27d1525127164994246d0c24494faea83d',
   'lib/page_renderer.php':'2de59755060b8a0037e1b5451c5d7db3fa4a1245732912cc0a2dff680b020d1b',
   'lib/security_headers.php':'74cc28e05a786b9293f19481bc759f4cd4dc1ed7c7faef2e35c20702aec2d624',
   'lib/theme_picker.php':'3440b780c25812effec9ffff568edc8150ada76ac38e3974b1f115d32d189be9',
   'luma.php':'4233fc2125a79fc02e69188834d4e444eafbdd84a9c107769bec019d2ce7d376',
   'static/images-infinite.js':'9fe55bdaeb203c8e295711a6f3c557bd87c3172cfb273e33c50564c6a81496d1',
   'template/search-actions.html':'cbbd1c8fe244e4b9e8c6138c558bf53f8549ff4a658e992cc8d0df3361a9ffe5'}
  for name,digest in expected.items():
   with self.subTest(name=name):self.assertEqual(hashlib.sha256(runtime.inline_luma_baseline_bytes(name)).hexdigest(),digest)
 def test_inline_luma_added_files_have_no_predecessor(self):
  self.assertIn('static/profile-placeholder.svg',json.loads((R/'data/runtime-changes-inline-luma.json').read_text())['added_files'])
  for name in ('scraper/luma.php','static/themes/GoroDaimon.css','static/profile-placeholder.svg'):
   for reader in (runtime.inline_luma_baseline_bytes,runtime.luma_baseline_bytes,maintenance_baseline_bytes,before42_bytes,historical_bytes):
    with self.subTest(name=name,reader=reader.__name__),self.assertRaises(ValueError):reader(name)
 def test_inline_luma_scope_baseline_and_types_fail_closed(self):
  real=Path.read_text;path=R/'data/runtime-changes-inline-luma.json';manifest=json.loads(real(path))
  variants=[None,[],0,'unreviewed']
  for key in ('release','baseline_commit','baseline_tree'):
   d=copy.deepcopy(manifest);d[key]='unreviewed';variants.append(d)
  d=copy.deepcopy(manifest);d['unexpected']='unreviewed';variants.append(d)
  for scope in ('files','added_files'):
   for bad in (None,[],0,'unreviewed'):
    d=copy.deepcopy(manifest);d[scope]=bad;variants.append(d)
   d=copy.deepcopy(manifest);d[scope]['../unexpected.php']={};variants.append(d)
   for name in manifest[scope]:
    d=copy.deepcopy(manifest);del d[scope][name];variants.append(d)
    for bad in (None,[],0,'unreviewed'):
     d=copy.deepcopy(manifest);d[scope][name]=bad;variants.append(d)
  for index,d in enumerate(variants):
   with self.subTest(index=index),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(d) if p==path else real(p,*a,**k)):
    with self.assertRaises(ValueError):runtime.inline_luma_maintenance_manifest()
 def test_inline_luma_duplicate_metadata_is_rejected(self):
  real=Path.read_text;path=R/'data/runtime-changes-inline-luma.json'
  changed=real(path).replace('"release": "0.9.42"','"release": "unreviewed", "release": "0.9.42"',1)
  with patch.object(Path,'read_text',lambda p,*a,**k:changed if p==path else real(p,*a,**k)):
   with self.assertRaises(ValueError):runtime.inline_luma_maintenance_manifest()
 def test_inline_luma_edits_and_pinned_hashes_fail_closed(self):
  real=Path.read_text;path=R/'data/runtime-changes-inline-luma.json';manifest=json.loads(real(path))
  for name in manifest['files']:
   for bad in ('record','edit','missing_edits','empty','ambiguous','preimage','identical','duplicate','old_hash','new_hash'):
    d=copy.deepcopy(manifest);row=d['files'][name]
    if bad=='record':row['unexpected']='unreviewed'
    elif bad=='edit':row['edits'][0]['unexpected']='unreviewed'
    elif bad=='missing_edits':row['edits']=[]
    elif bad=='empty':row['edits'][0]['after']=''
    elif bad=='ambiguous':row['edits'][0]['after']='\n'
    elif bad=='preimage':row['edits'][0]['before']+='unreviewed'
    elif bad=='identical':row['edits'][0]['before']=row['edits'][0]['after']
    elif bad=='duplicate':row['edits']*=2
    else:row[bad.replace('_hash','_sha256')]='0'*64
    with self.subTest(name=name,bad=bad),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(d) if p==path else real(p,*a,**k)):
     with self.assertRaises(ValueError):historical_bytes(name)
  for bad in (None,{},[None],[{}],[{'before':1,'after':'reviewed'}],[{'before':'reviewed','after':[]}],[{'before':'','after':'reviewed'}]):
   d=copy.deepcopy(manifest);d['files']['lib/frontend.php']['edits']=bad
   with self.subTest(bad=bad),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(d) if p==path else real(p,*a,**k)):
    with self.assertRaises(ValueError):runtime.inline_luma_maintenance_manifest()
  for name in manifest['added_files']:
   for bad in ('hash','record'):
    d=copy.deepcopy(manifest)
    if bad=='hash':d['added_files'][name]['new_sha256']='0'*64
    else:d['added_files'][name]['unexpected']='unreviewed'
    with self.subTest(name=name,bad=bad),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(d) if p==path else real(p,*a,**k)):
     with self.assertRaises(ValueError):runtime.inline_luma_maintenance_manifest()
 def test_inline_luma_current_bytes_and_symlinks_fail_closed(self):
  d=runtime.inline_luma_maintenance_manifest();real=Path.read_bytes;symlink=Path.is_symlink;regular=Path.is_file
  for name in set(d['files']) | set(d['added_files']):
   with self.subTest(name=name),patch.object(Path,'read_bytes',lambda p:real(p)+b'unreviewed' if p==R/name else real(p)):
    with self.assertRaises(ValueError):runtime.inline_luma_baseline_bytes(name)
   for kind in ('symlink','missing'):
    method='is_symlink' if kind=='symlink' else 'is_file';original=symlink if kind=='symlink' else regular
    with self.subTest(name=name,kind=kind),patch.object(Path,method,lambda p:kind=='symlink' if p==R/name else original(p)):
     with self.assertRaises(ValueError):runtime.inline_luma_baseline_bytes(name)
  for name in ('data/runtime-changes-inline-luma.json','scraper','lib'):
   with self.subTest(symlink=name),patch.object(Path,'is_symlink',lambda p:True if p==R/name else symlink(p)):
    with self.assertRaises(ValueError):runtime.inline_luma_baseline_bytes('lib/frontend.php')
 def test_inline_luma_reader_refuses_paths_outside_source(self):
  for name in ('../outside.php','/tmp/outside.php','lib/../../outside.php','./lib/frontend.php','lib//frontend.php','',None,[],{},'lib\\frontend.php'):
   with self.subTest(name=name),self.assertRaises(ValueError):runtime.inline_luma_baseline_bytes(name)
 def test_inline_luma_nonowned_bytes_still_come_from_current_source(self):
  name='web.php';real=Path.read_bytes
  with patch.object(Path,'read_bytes',lambda p:b'current source bytes' if p==R/name else real(p)):
   self.assertEqual(runtime.inline_luma_baseline_bytes(name),b'current source bytes')
   self.assertEqual(runtime.luma_baseline_bytes(name),b'current source bytes')
 def test_luma_change_has_exact_reviewed_scope(self):
  self.assertTrue(hasattr(runtime,'luma_maintenance_manifest'),'LUMA runtime-history layer is required')
  d=runtime.luma_maintenance_manifest()
  self.assertEqual(d['baseline_commit'],'1d45134d760defabe0de534383a16f81e0a6b938')
  self.assertEqual(d['baseline_tree'],'93c505330fa485aed7617184ec171959b777f50b')
  self.assertEqual(set(d['files']),{'template/search-actions.html','static/style.css','static/home-base.css','lib/page_renderer.php','lib/security_headers.php','docker/apache/fast-home.conf','data/home-css-manifest.json'})
  self.assertEqual(set(d['added_files']),{'luma.php','lib/luma_search.php'})
 def test_luma_fast_home_bytes_preserve_reviewed_history(self):
  d=json.loads((R/'data/runtime-changes-luma-search.json').read_text())
  name='docker/apache/fast-home.conf'
  self.assertIn(name,set(d['files']),'LUMA must review anonymous-home response policy too')
  self.assertEqual((d['files'][name]['old_sha256'],d['files'][name]['new_sha256']),('88e604889acf92561ed673cfe1da83708b2595658851f9133e4d5e67ba550941','67cb03d4cb37c667c3ce3ae8af706e86607540092583883d11e574dab1f27e08'))
  self.assertEqual(hashlib.sha256(runtime.luma_baseline_bytes(name)).hexdigest(),'88e604889acf92561ed673cfe1da83708b2595658851f9133e4d5e67ba550941')
 def test_luma_home_css_manifest_is_live_and_reversible(self):
  d=json.loads((R/'data/runtime-changes-luma-search.json').read_text());name='data/home-css-manifest.json'
  self.assertIn(name,set(d['files']),'LUMA must review the living CSS manifest too')
  original=runtime.luma_baseline_bytes(name)
  self.assertEqual(hashlib.sha256(original).hexdigest(),'ace58c0b4f0a884a14ea072ce9e50c91e77950088bd022005829f86e529d667f')
  baseline=json.loads(original);current=json.loads((R/name).read_text());restored=copy.deepcopy(current)
  for scope,css in (('inputs','static/style.css'),('outputs','static/home-base.css')):
   self.assertEqual(current[scope][css],hashlib.sha256((R/css).read_bytes()).hexdigest(),'Living CSS evidence must match current source')
   self.assertEqual(baseline[scope][css],hashlib.sha256(runtime.luma_baseline_bytes(css)).hexdigest(),'Historical CSS evidence must match reconstructed source')
   restored[scope][css]=baseline[scope][css]
  self.assertEqual(restored,baseline,'Only the two reviewed CSS fingerprints may change')
 def test_luma_restores_exact_five_historical_toolbar_actions(self):
  class Toolbar(HTMLParser):
   def __init__(self):super().__init__();self.buttons=[];self.icons=[]
   def handle_starttag(self,tag,attrs):
    if tag=='button':self.buttons.append(dict(attrs))
    elif tag=='svg':self.icons.append(dict(attrs))
  parser=Toolbar();parser.feed(maintenance_baseline_bytes('template/search-actions.html').decode())
  expected=[('Search',None,None,None),('Search Image','/images','destination','images'),('Search Pinterest','/images','destination','binternet'),('Search DeviantArt','/images','destination','skunkyart'),('Search YouTube','/videos','destination','invidious')]
  self.assertEqual([tuple(b.get(k) for k in ('aria-label','formaction','name','value')) for b in parser.buttons],expected)
  self.assertEqual(len(parser.icons),5)
  for b in parser.buttons:self.assertEqual(b['type'],'submit');self.assertNotIn('disabled',b)
  for icon in parser.icons:self.assertEqual((icon['aria-hidden'],icon['focusable']),('true','false'))
 def test_luma_added_endpoints_have_no_historical_source(self):
  for name in ('luma.php','lib/luma_search.php'):
   for reader in (runtime.luma_baseline_bytes,maintenance_baseline_bytes,before42_bytes,historical_bytes):
    with self.subTest(name=name,reader=reader.__name__),self.assertRaises(ValueError):reader(name)
 def test_luma_scope_and_baseline_tampering_fail_closed(self):
  real=Path.read_text;path=R/'data/runtime-changes-luma-search.json';manifest=json.loads(real(path))
  variants=[]
  for key in ('release','baseline_commit','baseline_tree'):
   d=copy.deepcopy(manifest);d[key]='unreviewed';variants.append((key,d))
  d=copy.deepcopy(manifest);d['unexpected']='unreviewed';variants.append(('unknown metadata',d))
  for scope in ('files','added_files'):
   d=copy.deepcopy(manifest);d[scope]['unexpected.php']={};variants.append(('unknown '+scope,d))
   for name in manifest[scope]:
    d=copy.deepcopy(manifest);del d[scope][name];variants.append(('missing '+name,d))
  for label,d in variants:
   with self.subTest(label=label),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(d) if p==path else real(p,*a,**k)):
    with self.assertRaises(ValueError):runtime.luma_maintenance_manifest()
 def test_luma_malformed_metadata_fails_closed(self):
  real=Path.read_text;path=R/'data/runtime-changes-luma-search.json';manifest=json.loads(real(path))
  variants=[None,[],0,'unreviewed']
  for scope in ('files','added_files'):
   for bad in (None,[],0,'unreviewed'):
    d=copy.deepcopy(manifest);d[scope]=bad;variants.append(d)
  name='template/search-actions.html'
  for bad in (None,[],0,'unreviewed'):
   d=copy.deepcopy(manifest);d['files'][name]=bad;variants.append(d)
  for bad in (None,{},[None],[{}],[{'before':1,'after':'reviewed'}],[{'before':'reviewed','after':[]}]) :
   d=copy.deepcopy(manifest);d['files'][name]['edits']=bad;variants.append(d)
  for index,d in enumerate(variants):
   with self.subTest(index=index),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(d) if p==path else real(p,*a,**k)):
    with self.assertRaises(ValueError):runtime.luma_maintenance_manifest()
 def test_luma_edit_tampering_cannot_claim_history(self):
  real=Path.read_text;path=R/'data/runtime-changes-luma-search.json';manifest=json.loads(real(path))
  for name in manifest['files']:
   for bad in ('record','edit','missing_edits','empty','ambiguous','preimage','identical','old_hash','new_hash'):
    changed=copy.deepcopy(manifest);row=changed['files'][name]
    if bad=='record':row['unexpected']='unreviewed'
    elif bad=='edit':row['edits'][0]['unexpected']='unreviewed'
    elif bad=='missing_edits':row['edits']=[]
    elif bad=='empty':row['edits'][0]['after']=''
    elif bad=='ambiguous':row['edits'][0]['after']='\n'
    elif bad=='preimage':row['edits'][0]['before']+='unreviewed'
    elif bad=='identical':row['edits'][0]['before']=row['edits'][0]['after']
    else:row[bad.replace('_hash','_sha256')]='0'*64
    with self.subTest(name=name,bad=bad),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(changed) if p==path else real(p,*a,**k)):
     with self.assertRaises(ValueError):historical_bytes(name)
 def test_luma_current_bytes_and_added_hashes_fail_closed(self):
  manifest=runtime.luma_maintenance_manifest();real=Path.read_bytes
  for name in set(manifest['files']) | set(manifest['added_files']):
   with self.subTest(name=name),patch.object(Path,'read_bytes',lambda p:real(p)+b'unreviewed' if p==R/name else real(p)):
    with self.assertRaises(ValueError):
     if name in manifest['added_files']:runtime.luma_maintenance_manifest()
     else:runtime.luma_baseline_bytes(name)
  text=Path.read_text;path=R/'data/runtime-changes-luma-search.json'
  for name in manifest['added_files']:
   for bad in ('hash','record'):
    d=copy.deepcopy(manifest)
    if bad=='hash':d['added_files'][name]['new_sha256']='0'*64
    else:d['added_files'][name]['unexpected']='unreviewed'
    with self.subTest(name=name,bad=bad),patch.object(Path,'read_text',lambda p,*a,**k:json.dumps(d) if p==path else text(p,*a,**k)):
     with self.assertRaises(ValueError):runtime.luma_maintenance_manifest()
 def test_provider_maintenance_has_exact_ten_reversible_files(self):
  d=provider_maintenance_manifest()
  self.assertEqual(set(d['files']),{'api/v1/web.php','api/v1/images.php','api/v1/videos.php','api/v1/news.php','api/v1/music.php','lib/search_guard.php','lib/search_health.php','scraper/brave.php','data/config.php','lib/frontend.php'})
 def test_privacy_cleanup_has_exact_two_current_only_files(self):
  d=provider_maintenance_manifest()['current_only_files']
  self.assertEqual(set(d),{'scraper/google_cse.php','scraper/cara.php'})
  self.assertEqual(d['scraper/cara.php']['old_sha256'],'922075bc5898f6bf2b17a21924abf1e7713053d53dc05b3cdf093c072248ccb6')
  self.assertEqual(d['scraper/cara.php']['new_sha256'],'90560e9a0b0e90ce452ededeaf91ccaecbe21d2ac14d3a90d762ec6ef6fbde11')
 def test_cara_privacy_cleanup_is_not_reconstructed(self):
  for reader in (runtime.luma_baseline_bytes,maintenance_baseline_bytes,before42_bytes,historical_bytes):
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
  for reader in (runtime.luma_baseline_bytes,before42_bytes,historical_bytes):
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
    current=runtime.luma_baseline_bytes(name);original=maintenance_baseline_bytes(name)
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
