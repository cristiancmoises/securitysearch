"""Verify approved maintenance bytes before reversing the frozen release deltas.

Google and Cara privacy cleanups are current-only: historical source is not restored.
"""
import hashlib,json
from pathlib import Path
R=Path(__file__).resolve().parents[1]
PROVIDER_SCOPE={'api/v1/web.php','api/v1/images.php','api/v1/videos.php','api/v1/news.php','api/v1/music.php','lib/search_guard.php','lib/search_health.php','scraper/brave.php','data/config.php','lib/frontend.php'}
GOOGLE_LEGACY_SHA256='6f2a7f87c727c319387413d46b5de88ecdaeabc6774d99b9f4ee1da5a8922198'
GOOGLE_CURRENT_SHA256='b375bb3f60d3cc0a820a7494751c30150dc2aaa3919cc49299a009023f9365c7'
CARA_LEGACY_SHA256='922075bc5898f6bf2b17a21924abf1e7713053d53dc05b3cdf093c072248ccb6'
CARA_CURRENT_SHA256='90560e9a0b0e90ce452ededeaf91ccaecbe21d2ac14d3a90d762ec6ef6fbde11'
CURRENT_ONLY_HASHES={'scraper/google_cse.php':(GOOGLE_LEGACY_SHA256,GOOGLE_CURRENT_SHA256),'scraper/cara.php':(CARA_LEGACY_SHA256,CARA_CURRENT_SHA256)}
BRAVE_DATA_SHA256='2d7eaf6b17a1bf9ba53343fb786bb87ebda1c79951f8062d225c4c24edf7dc8f'
LUMA_HASHES={
    'template/search-actions.html':('71ae0efa6e5c0ed91cd415f2a2853c259637e35f3f7c3ee680a53a3d9ce66781','cbbd1c8fe244e4b9e8c6138c558bf53f8549ff4a658e992cc8d0df3361a9ffe5'),
    'static/style.css':('fb9633e3668c8a2e1337593d2d5a2e2bbfa404e930cb6d7b4397d0912de4b5d0','305d4f82e0c9c03adcc9c9497ac49a7c10d9df8c45c9af38ad4709c564b09456'),
    'static/home-base.css':('e57b2f4b84a3332f5388304460151ed00642dcf66b7ff0ef0a375303bfb78f39','0e6c959310fce3a7a10c2ab586ece6103d73aea91bf820f6997f932b154162cd'),
    'lib/page_renderer.php':('238b8733cad1431d1428bd06a570b52d3b0e77df504b8b6156bab3f89e65db72','2de59755060b8a0037e1b5451c5d7db3fa4a1245732912cc0a2dff680b020d1b'),
    'lib/security_headers.php':('4eae74ed37e6c741d9b831b59547224a282f2288cc8268e9fd86c1173f3d8869','74cc28e05a786b9293f19481bc759f4cd4dc1ed7c7faef2e35c20702aec2d624'),
    'docker/apache/fast-home.conf':('88e604889acf92561ed673cfe1da83708b2595658851f9133e4d5e67ba550941','67cb03d4cb37c667c3ce3ae8af706e86607540092583883d11e574dab1f27e08'),
    'data/home-css-manifest.json':('ace58c0b4f0a884a14ea072ce9e50c91e77950088bd022005829f86e529d667f','fdf29a224e5d525d57e954b8563c9d591be845c39b5e81ba118c8a53e7b3d1af')}
LUMA_ADDED_HASHES={'luma.php':'4233fc2125a79fc02e69188834d4e444eafbdd84a9c107769bec019d2ce7d376','lib/luma_search.php':'230f25e3c8fd948251a43897683a7b27d1525127164994246d0c24494faea83d'}

def luma_maintenance_manifest():
    """Check the reviewed LUMA delta without changing earlier release evidence."""
    manifest=json.loads((R/'data/runtime-changes-luma-search.json').read_text())
    if not isinstance(manifest,dict) or \
       set(manifest)!={'release','baseline_commit','baseline_tree','files','added_files'} or \
       manifest['release']!='0.9.42' or \
       manifest['baseline_commit']!='1d45134d760defabe0de534383a16f81e0a6b938' or \
       manifest['baseline_tree']!='93c505330fa485aed7617184ec171959b777f50b' or \
       not isinstance(manifest['files'],dict) or set(manifest['files'])!=set(LUMA_HASHES):
        raise ValueError('Unexpected LUMA maintenance scope or baseline')
    expected_added={name:{'new_sha256':digest} for name,digest in LUMA_ADDED_HASHES.items()}
    if manifest['added_files']!=expected_added:
        raise ValueError('Unexpected reviewed LUMA endpoint fingerprints')
    for name,digest in LUMA_ADDED_HASHES.items():
        path=R/name
        if path.is_symlink() or not path.is_file() or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:
            raise ValueError('Unexpected reviewed LUMA endpoint bytes: '+name)
    for name,record in manifest['files'].items():
        if not isinstance(record,dict) or set(record)!={'old_sha256','new_sha256','edits'} or \
           (record['old_sha256'],record['new_sha256'])!=LUMA_HASHES[name] or \
           not isinstance(record['edits'],list) or not record['edits']:
            raise ValueError('Unexpected reviewed LUMA maintenance record: '+name)
        for edit in record['edits']:
            if not isinstance(edit,dict) or set(edit)!={'before','after'} or \
               not all(isinstance(edit[key],str) and edit[key] for key in ('before','after')) or \
               edit['before']==edit['after']:
                raise ValueError('Unexpected LUMA maintenance edit metadata: '+name)
    return manifest

def luma_baseline_bytes(name):
    """Recover the exact pre-LUMA source; added endpoints have no predecessor."""
    manifest=luma_maintenance_manifest()
    if name in CURRENT_ONLY_HASHES:
        raise ValueError('Privacy cleanup is current-only; historical source unavailable')
    if name in manifest['added_files']:
        raise ValueError('LUMA endpoint was added; historical source unavailable')
    raw=(R/name).read_bytes()
    if name not in manifest['files']:return raw
    record=manifest['files'][name]
    if hashlib.sha256(raw).hexdigest()!=record['new_sha256']:
        raise ValueError('Unexpected reviewed LUMA maintenance bytes: '+name)
    for edit in reversed(record['edits']):
        before,after=edit['before'].encode(),edit['after'].encode()
        if raw.count(after)!=1:
            raise ValueError('Ambiguous LUMA maintenance edit: '+name)
        raw=raw.replace(after,before,1)
        if raw.count(before)!=1:
            raise ValueError('Ambiguous LUMA maintenance preimage: '+name)
    if hashlib.sha256(raw).hexdigest()!=record['old_sha256']:
        raise ValueError('Pre-LUMA runtime mismatch: '+name)
    return raw

def provider_maintenance_manifest():
    manifest=json.loads((R/'data/runtime-changes-provider-reliability.json').read_text())
    if set(manifest)!={'release','baseline_commit','baseline_tree','files','current_only_files','added_files'} or \
       manifest['release']!='0.9.42' or \
       manifest['baseline_commit']!='62bc700c4d8615d0e20ee0eb9d375674ccaabbc4' or \
       manifest['baseline_tree']!='7b0097e353a4663a09127c233301ebacc18c42bc' or \
       set(manifest['files'])!=PROVIDER_SCOPE:
        raise ValueError('Unexpected provider maintenance delta scope or baseline')
    expected_current={name:{'old_sha256':old,'new_sha256':new,
        'policy':'reviewed-current-privacy-cleanup; historical bytes unavailable'}
        for name,(old,new) in CURRENT_ONLY_HASHES.items()}
    if manifest['current_only_files']!=expected_current:
        raise ValueError('Unexpected reviewed-current privacy policy')
    if manifest['added_files']!={'lib/brave_data.php':{'new_sha256':BRAVE_DATA_SHA256}}:
        raise ValueError('Unexpected added provider parser scope')
    for name,record in manifest['files'].items():
        if not isinstance(record,dict) or set(record)!={'old_sha256','new_sha256','edits'} or \
           not isinstance(record['edits'],list) or not record['edits']:
            raise ValueError('Unexpected maintenance record: '+name)
        for edit in record['edits']:
            if not isinstance(edit,dict) or set(edit)!={'before','after'} or \
               not all(isinstance(edit[key],str) for key in ('before','after')):
                raise ValueError('Unexpected maintenance edit metadata: '+name)
    return manifest

def maintenance_baseline_bytes(name):
    """Recover original v42 bytes only for the exact ten reversible changes."""
    manifest=provider_maintenance_manifest()
    if name in manifest['current_only_files']:
        raise ValueError('Privacy cleanup is current-only; historical bytes unavailable')
    raw=luma_baseline_bytes(name)
    if name not in manifest['files']:return raw
    record=manifest['files'][name]
    if hashlib.sha256(raw).hexdigest()!=record['new_sha256']:
        raise ValueError('Unexpected reviewed maintenance bytes: '+name)
    if not record['edits']:
        raise ValueError('Missing maintenance edits: '+name)
    for edit in reversed(record['edits']):
        before,after=edit['before'].encode(),edit['after'].encode()
        if not before or not after or before==after or raw.count(after)!=1:
            raise ValueError('Ambiguous maintenance edit: '+name)
        raw=raw.replace(after,before,1)
        if raw.count(before)!=1:
            raise ValueError('Ambiguous maintenance preimage: '+name)
    if hashlib.sha256(raw).hexdigest()!=record['old_sha256']:
        raise ValueError('Original v42 runtime mismatch: '+name)
    return raw

def assert_reviewed_current(case,name,legacy_expected):
    """Check archived hash metadata, then pin the privacy-cleaned current source."""
    if name not in CURRENT_ONLY_HASHES:
        raise ValueError('Unexpected current-only source')
    record=provider_maintenance_manifest()['current_only_files'][name]
    for archive in ('data/preserved-runtime-0.9.37.json','data/preserved-runtime-0.9.38.json'):
        archived=json.loads((R/archive).read_text())[name]
        case.assertEqual(legacy_expected,archived,'Legacy metadata changed: '+name)
        case.assertEqual(archived,record['old_sha256'],'Archived hash changed: '+name)
    case.assertEqual(hashlib.sha256((R/name).read_bytes()).hexdigest(),
                     record['new_sha256'],'Reviewed-current privacy cleanup changed: '+name)

def assert_reviewed_google_current(case,legacy_expected):
    assert_reviewed_current(case,'scraper/google_cse.php',legacy_expected)

def before42_bytes(name):
    raw=maintenance_baseline_bytes(name)
    manifest=json.loads((R/'data/runtime-changes-0.9.42.json').read_text())
    delta=manifest['files']
    if set(delta)!={'data/config.php','lib/frontend.php','template/search-actions.html','scripts/deployment_state.py','static/style.css','static/home-base.css'}:
        raise ValueError('Unexpected v42 production delta scope')
    if name not in delta:return raw
    record=delta[name]
    if hashlib.sha256(raw).hexdigest()!=record['new_sha256']:raise ValueError('Unexpected v42 runtime bytes: '+name)
    for edit in reversed(record['edits']):
        before,after=edit['before'].encode(),edit['after'].encode()
        if raw.count(after)!=1:raise ValueError('Ambiguous v42 edit: '+name)
        raw=raw.replace(after,before,1)
    if hashlib.sha256(raw).hexdigest()!=record['old_sha256']:raise ValueError('Historical v41 runtime mismatch: '+name)
    return raw

def historical_bytes(name):
    raw=before42_bytes(name)
    delta=json.loads((R/'data/runtime-changes-0.9.41.json').read_text())['files']
    if set(delta)!=set('data/config.php docker/apache/fast-home.conf docker/apache/http/httpd.conf docker/apache/https/httpd.conf lib/image_results.php static/images-infinite.js'.split()):
        raise ValueError('Unexpected v41 production delta scope')
    if name in delta:
        record=delta[name]
        if hashlib.sha256(raw).hexdigest()!=record['new_sha256']:raise ValueError('Unexpected v41 runtime bytes: '+name)
        for edit in reversed(record['edits']):
            before,after=edit['before'].encode(),edit['after'].encode()
            if raw.count(after)!=1:raise ValueError('Ambiguous v41 edit: '+name)
            raw=raw.replace(after,before,1)
        if hashlib.sha256(raw).hexdigest()!=record['old_sha256']:raise ValueError('Historical v40 runtime mismatch: '+name)
    changes=json.loads((R/'data/search-state-changes-0.9.40.json').read_text())['files']
    if set(changes)!={'lib/frontend.php','web.php','music.php'}:raise ValueError('Unexpected production delta scope')
    if name not in changes:return raw
    record=changes[name]
    if hashlib.sha256(raw).hexdigest()!=record['new_sha256']:raise ValueError('Unexpected new runtime bytes: '+name)
    for edit in reversed(record['edits']):
        before,after=edit['before'].encode(),edit['after'].encode()
        if raw.count(after)!=1:raise ValueError('Ambiguous allowed edit: '+name)
        raw=raw.replace(after,before,1)
    if hashlib.sha256(raw).hexdigest()!=record['old_sha256']:raise ValueError('Historical runtime mismatch: '+name)
    return raw
