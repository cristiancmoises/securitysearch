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
    raw=(R/name).read_bytes()
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
