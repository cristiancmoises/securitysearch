"""Reconstruct historical bytes only after verifying an exact approved v40 delta."""
import hashlib,json
from pathlib import Path
R=Path(__file__).resolve().parents[1]
def historical_bytes(name):
    raw=(R/name).read_bytes()
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
