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
INLINE_LUMA_HASHES={
    'docker/apache/fast-home.conf':('67cb03d4cb37c667c3ce3ae8af706e86607540092583883d11e574dab1f27e08', '88e604889acf92561ed673cfe1da83708b2595658851f9133e4d5e67ba550941'),
    'images.php':('b642f1138f0100294bc1e10807966b62e0da06d726aae915a928981b0c0d5791', '5a397d0d1c877ad7c58feabdc1c8368c35cfce3b7902103557c8b4ee3cd2ba1d'),
    'lib/build_view_resources.php':('20ba6984889ee7464c0603ab2aaf30f2625ed32f2b4ed5525d3fe5900aea1772', '018a442305a43151b94939002844b4ab703aee8bf9799eb9e8a58c3abbdc81cd'),
    'lib/frontend.php':('b48f013691de8e1a375ffb00cbcdcc91ee0885112e2d1127a8aef16d9317ed5c', 'acf6a0de836750f9f17532d54d3387e8247b895eae4e57ef29b9ce8208982bd0'),
    'lib/image_results.php':('89c20b29051046fa3c5ed0dc63c5cbae92cd59fad96add65c9edac4c7d8d57f1', '01b13d1d5cf4a4e5379a730ec7144cfc1f758c87ae34ee31d87724d88d1eb423'),
    'lib/luma_search.php':('230f25e3c8fd948251a43897683a7b27d1525127164994246d0c24494faea83d', '0256e48a04cdb0e5fd2a7e1c61e2e8de4309e639f82700472602e5f2f71cb84b'),
    'lib/page_renderer.php':('2de59755060b8a0037e1b5451c5d7db3fa4a1245732912cc0a2dff680b020d1b', 'df5dba53f049dcdf12b5db3751f7591cda2f50efe1922918efa62605127b56f6'),
    'lib/security_headers.php':('74cc28e05a786b9293f19481bc759f4cd4dc1ed7c7faef2e35c20702aec2d624', '4eae74ed37e6c741d9b831b59547224a282f2288cc8268e9fd86c1173f3d8869'),
    'lib/theme_picker.php':('3440b780c25812effec9ffff568edc8150ada76ac38e3974b1f115d32d189be9', 'b5d015a83bd0f750b94c54f3d9a78015397284f221c3aed2bd80931728ad4fe2'),
    'luma.php':('4233fc2125a79fc02e69188834d4e444eafbdd84a9c107769bec019d2ce7d376', '2d6ac8dfffb6d7d8fae2aee6544474c79f0797d42f6cc8413c9e8c851f3e026f'),
    'static/images-infinite.js':('9fe55bdaeb203c8e295711a6f3c557bd87c3172cfb273e33c50564c6a81496d1', '1c7ac6cab128a5f21c7b8ce5cf8a9c2a80393b28e8a704111ee18524334f1a8d'),
    'template/search-actions.html':('cbbd1c8fe244e4b9e8c6138c558bf53f8549ff4a658e992cc8d0df3361a9ffe5', 'bcdf170c4c96915c17f7ab6956aae2a8ca3554aa6e56451edbdcf454e456658b'),
}
INLINE_LUMA_ADDED_HASHES={
    'scraper/luma.php':'b5211b0222b236c3724c3eb52992423891a6af50fbfd5ab90aacb401f003419a',
    'static/themes/GoroDaimon.css':'39117f58ef086792483b282edc112cd2262adedb15c87a113d73f2e11f87e150',
    'static/profile-placeholder.svg':'b048232b69541bdca3e898edac9284949c2ec39d67cb117d8eb012866de23469',
}
SEARCH_PERFORMANCE_HASHES={
    'docker/docker-entrypoint.sh':('7d881735057d2c83881e4c408cd03cdeca12aebbf742f8353ce7addd801a090f', '16587d897284372a059aaf653f9372c39b7a4d1e13f299ce0ccd5bd15ee47014', '15a57e29739ff67c084f778f2a67a3b29835397c183aa04e211232cf384f9141'),
    'lib/provider_dns.php':('e15f861e7090808ac374c943ef1b578a19118bb8e2c1d9a59f7d124aba933b9d', '5e51b43256d04f2eef85c4937c06d7f9c88bde859a0aad232fde664cb66386da', 'ce5b1f236d7cd29436834f73ffab0d759707988e74dbee4f06a9c3f42ac05467'),
    'lib/provider_http.php':('9bfe6baf855db5b57ec097d4bc2c3c60d74ded0277984d0d0792281a35a9179f', 'ab2e1f05886c13b195e15028cb6808287b72222a781b221d7724a124321bcf1c', 'c2c86f9fa53504abd13b033a8cd54e8a355050e80b4231f562b784e15ef99973'),
    'lib/service_search.php':('efaa1f4071c6cea706bfbb0d36e11ca18c74d3c70d9edef1c19348dfda1a7023', 'e2f59a6dde4c1a316745e9fc7057951c8fd334c5e6f09416aafad535ef3ff1db', 'c67a93992cd2a517c6c7c90a5a24eb8a47c74429d5413af0a4fc72a403bc44b2'),
}

def _runtime_source_path(name):
    """Use only regular source files reached without traversal or symlinks."""
    if not isinstance(name,str) or not name or '\\' in name:
        raise ValueError('Unexpected runtime source path')
    relative=Path(name)
    if relative.is_absolute() or relative.as_posix()!=name or not relative.parts or \
       any(part in ('.','..') for part in relative.parts):
        raise ValueError('Unexpected runtime source path')
    path=R/relative
    if any(R.joinpath(*relative.parts[:end]).is_symlink() for end in range(1,len(relative.parts)+1)) or \
       not path.is_file():
        raise ValueError('Unexpected runtime source file: '+name)
    return path

def _read_runtime_manifest(name):
    def unique_object(pairs):
        value=dict(pairs)
        if len(value)!=len(pairs):
            raise ValueError('Duplicate runtime maintenance metadata')
        return value
    return json.loads(_runtime_source_path(name).read_text(),object_pairs_hook=unique_object)

def _reverse_runtime_record(name,raw,record):
    if hashlib.sha256(raw).hexdigest()!=record['new_sha256']:
        raise ValueError('Unexpected reviewed runtime bytes: '+name)
    for edit in reversed(record['edits']):
        before,after=edit['before'].encode(),edit['after'].encode()
        if raw.count(after)!=1:
            raise ValueError('Ambiguous runtime edit: '+name)
        raw=raw.replace(after,before,1)
        if raw.count(before)!=1:
            raise ValueError('Ambiguous runtime preimage: '+name)
    if hashlib.sha256(raw).hexdigest()!=record['old_sha256']:
        raise ValueError('Runtime predecessor mismatch: '+name)
    return raw

def search_performance_maintenance_manifest():
    """Pin the four transport/startup changes, including reviewed edit order."""
    manifest=_read_runtime_manifest('data/runtime-changes-search-performance.json')
    if not isinstance(manifest,dict) or \
       set(manifest)!={'release','baseline_commit','baseline_tree','files','added_files'} or \
       manifest['release']!='0.9.42' or \
       manifest['baseline_commit']!='0c214e0d883eaf462a8a4f0a4f8333897ae0d601' or \
       manifest['baseline_tree']!='0896f2c872bfd1bcfe8148c5962ccb41803e48d1' or \
       manifest['added_files']!={} or \
       not isinstance(manifest['files'],dict) or set(manifest['files'])!=set(SEARCH_PERFORMANCE_HASHES):
        raise ValueError('Unexpected search performance scope or baseline')
    for name,record in manifest['files'].items():
        if not isinstance(record,dict) or set(record)!={'old_sha256','new_sha256','edits'} or \
           (record['old_sha256'],record['new_sha256'])!=SEARCH_PERFORMANCE_HASHES[name][:2] or \
           not isinstance(record['edits'],list) or not record['edits']:
            raise ValueError('Unexpected search performance record: '+name)
        for edit in record['edits']:
            if not isinstance(edit,dict) or set(edit)!={'before','after'} or \
               not all(isinstance(edit[key],str) and edit[key] for key in ('before','after')) or \
               edit['before']==edit['after']:
                raise ValueError('Unexpected search performance edit metadata: '+name)
        edits=json.dumps(record['edits'],sort_keys=True,separators=(',',':')).encode()
        if hashlib.sha256(edits).hexdigest()!=SEARCH_PERFORMANCE_HASHES[name][2]:
            raise ValueError('Unexpected reviewed search performance edits or order: '+name)
        if hashlib.sha256(_runtime_source_path(name).read_bytes()).hexdigest()!=record['new_sha256']:
            raise ValueError('Unexpected reviewed search performance bytes: '+name)
    return manifest

def search_performance_baseline_bytes(name):
    """Recover exact 0c bytes before entering the frozen earlier layers."""
    path=_runtime_source_path(name)
    manifest=search_performance_maintenance_manifest()
    if name in CURRENT_ONLY_HASHES:
        raise ValueError('Privacy cleanup is current-only; historical source unavailable')
    raw=path.read_bytes()
    return _reverse_runtime_record(name,raw,manifest['files'][name]) if name in manifest['files'] else raw

def inline_luma_maintenance_manifest():
    """Pin the inline search/theme delta to its exact reviewed source boundary."""
    manifest=_read_runtime_manifest('data/runtime-changes-inline-luma.json')
    if not isinstance(manifest,dict) or \
       set(manifest)!={'release','baseline_commit','baseline_tree','files','added_files'} or \
       manifest['release']!='0.9.42' or \
       manifest['baseline_commit']!='055731829a4c1c0ddca94ef500c3f1a4faaebec3' or \
       manifest['baseline_tree']!='df601cc0c8bf9a9f534dda6b38f879d164248775' or \
       not isinstance(manifest['files'],dict) or set(manifest['files'])!=set(INLINE_LUMA_HASHES):
        raise ValueError('Unexpected inline LUMA maintenance scope or baseline')
    expected_added={name:{'new_sha256':digest} for name,digest in INLINE_LUMA_ADDED_HASHES.items()}
    if manifest['added_files']!=expected_added:
        raise ValueError('Unexpected reviewed inline LUMA added fingerprints')
    for name,digest in INLINE_LUMA_ADDED_HASHES.items():
        if hashlib.sha256(_runtime_source_path(name).read_bytes()).hexdigest()!=digest:
            raise ValueError('Unexpected reviewed inline LUMA added bytes: '+name)
    for name,record in manifest['files'].items():
        if not isinstance(record,dict) or set(record)!={'old_sha256','new_sha256','edits'} or \
           (record['old_sha256'],record['new_sha256'])!=INLINE_LUMA_HASHES[name] or \
           not isinstance(record['edits'],list) or not record['edits']:
            raise ValueError('Unexpected reviewed inline LUMA maintenance record: '+name)
        for edit in record['edits']:
            if not isinstance(edit,dict) or set(edit)!={'before','after'} or \
               not all(isinstance(edit[key],str) and edit[key] for key in ('before','after')) or \
               edit['before']==edit['after']:
                raise ValueError('Unexpected inline LUMA maintenance edit metadata: '+name)
    return manifest

def inline_luma_baseline_bytes(name):
    """Recover reviewed 055 bytes before inline LUMA and the white theme."""
    raw=search_performance_baseline_bytes(name)
    manifest=inline_luma_maintenance_manifest()
    if name in CURRENT_ONLY_HASHES:
        raise ValueError('Privacy cleanup is current-only; historical source unavailable')
    if name in manifest['added_files']:
        raise ValueError('Inline LUMA file was added; historical source unavailable')
    if name not in manifest['files']:return raw
    return _reverse_runtime_record(name,raw,manifest['files'][name])

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
        if path.is_symlink() or not path.is_file() or hashlib.sha256(inline_luma_baseline_bytes(name)).hexdigest()!=digest:
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
    raw=inline_luma_baseline_bytes(name)
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
