#!/usr/bin/env python3
"""Build immutable source assets and an incremental recovery bundle from v0.9.24.

Use the clean tagged main checkout. Outputs live outside the source checkout.
The bundle requires the already-published v0.9.20 commit and preserves Git history.
No remote connection, tag creation, deployment or publication is performed here.
"""
import argparse
import gzip
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tarfile
import tempfile

VERSION = '0.9.24'
TAG = 'v' + VERSION
PREFIX = 'securitysearch-' + TAG
BASE = '7f9f25a2f56a2b2edb1189e076da29895f95c057'
MAX_BYTES = 256 * 1024 * 1024

import importlib.util as _media_import
_media_spec = _media_import.spec_from_file_location('release_media_policy', Path(__file__).with_name('release_media_policy.py'))
media_policy = _media_import.module_from_spec(_media_spec)
_media_spec.loader.exec_module(media_policy)


class Failure(RuntimeError):
    pass


def require(ok, message):
    if not ok:
        raise Failure(message)


def git(repo, *args):
    result = subprocess.run(['git', '-C', str(repo), *args], capture_output=True, timeout=300)
    require(result.returncode == 0, 'Git ' + args[0] + ' failed. Local files were preserved.')
    return result.stdout


def text(repo, *args):
    return git(repo, *args).decode('utf-8').strip()


def digest(data):
    return hashlib.sha256(data).hexdigest()


def save_once(path, data):
    """Never replace existing release bytes, including on a repeated run."""
    require(not path.is_symlink(), 'Refused symlink output: ' + path.name)
    if path.exists():
        require(path.is_file() and path.read_bytes() == data,
                'Existing release file differs and was preserved: ' + path.name)
        return
    fd, temp = tempfile.mkstemp(prefix='.' + path.name + '-', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as out:
            out.write(data)
            out.flush()
            os.fsync(out.fileno())
        os.chmod(temp, 0o644)
        try:
            os.link(temp, path)
        except FileExistsError:
            require(path.is_file() and not path.is_symlink() and path.read_bytes() == data,
                    'Concurrent output differs: ' + path.name)
    finally:
        os.unlink(temp)


def source_archive(repo, commit):
    media_policy.check_tree(repo, commit)
    raw = git(repo, 'archive', '--format=tar', '--prefix=' + PREFIX + '/', commit)
    require(len(raw) <= 2 * MAX_BYTES, 'Uncompressed source exceeds the release limit.')
    required = {'README.md', 'README.pt-BR.md', 'data/release-version.txt',
                'scripts/redlib-probe.php','lib/redlib_selection.php',
                'scripts/news-rss-probe.php','scraper/newswire.php','lib/news_rss.php','lib/news_sources.php','lib/news_selection.php',
                'tests/news-primary-regression.php','tests/picture-http-regression.py',
                'docs/RELEASE-0.9.24.md', 'docs/AUDITFIX-0.9.20-r1.md',
                'scripts/publish-v0.9.24.py', 'scripts/package-v0.9.24.py',
                'tests/provider-http-harness-regression.py',
                'docs/screenshots/securitysearch-0.9.21-home-black.png',
                'docs/screenshots/securitysearch-0.9.21-theme-picker.png'}
    paths = set()
    with tarfile.open(fileobj=io.BytesIO(raw), mode='r:') as archive:
        require(archive.pax_headers.get('comment') == commit, 'Missing Git archive provenance.')
        for item in archive:
            name = item.name.rstrip('/')
            parts = Path(name).parts
            require(parts and parts[0] == PREFIX and '..' not in parts and not name.startswith('/'),
                    'Unsafe source archive member.')
            require(item.isfile() or item.isdir(), 'Links, devices and special source files are refused.')
            rel = '/'.join(parts[1:])
            require(rel not in paths, 'Duplicate archive member.')
            paths.add(rel)
            require(not re.search(r'(^|/)[^/]*(prompt|god[-_. ]?tier)[^/]*($|/)', rel, re.I),
                    'A forbidden prompt artifact is tracked; no archive created.')
            require(not re.search(r'(^|/)(\.git|\.env(?!\.example$)|id_rsa|id_ed25519|credentials|secrets)(/|$)', rel, re.I)
                    and not rel.startswith('data/api_keys/')
                    and not rel.endswith(('.key', '.pem', '.p12', '.pfx')),
                    'Potential private-data path is tracked; inspect it before packaging.')
    require(required <= paths, 'Source archive is missing release files: ' + ', '.join(sorted(required - paths)))
    if 'icons' not in paths:
        buffer = io.BytesIO(raw)
        with tarfile.open(fileobj=buffer, mode='a') as archive:
            item = tarfile.TarInfo(PREFIX + '/icons')
            item.type = tarfile.DIRTYPE
            item.mode = 0o755
            item.mtime = int(text(repo, 'show', '-s', '--format=%ct', commit))
            item.uid = item.gid = 0
            archive.addfile(item)
        raw = buffer.getvalue()
    output = io.BytesIO()
    with gzip.GzipFile(fileobj=output, mode='wb', filename='', mtime=0, compresslevel=9) as gz:
        gz.write(raw)
    data = output.getvalue()
    require(len(data) <= MAX_BYTES, 'Compressed source exceeds the release limit.')
    return data


def package(repo, outdir):
    repo = repo.expanduser().resolve()
    require(text(repo, 'rev-parse', '--show-toplevel') == str(repo), 'Pass the checkout root.')
    require(text(repo, 'symbolic-ref', '--quiet', '--short', 'HEAD') == 'main', 'Use main; no branch switch is made.')
    require(not text(repo, 'status', '--porcelain'), 'Uncommitted or untracked files exist; nothing will be packaged.')
    require(text(repo, 'rev-parse', '--is-shallow-repository') == 'false', 'Complete Git history is required.')
    commit = text(repo, 'rev-parse', 'HEAD')
    require(text(repo, 'cat-file', '-t', 'refs/tags/' + TAG) == 'tag', 'Create the annotated local tag first.')
    tag_object = text(repo, 'rev-parse', 'refs/tags/' + TAG)
    require(text(repo, 'rev-parse', TAG + '^{commit}') == commit, 'Existing tag differs from HEAD; it was preserved.')
    require(text(repo, 'show', commit + ':data/release-version.txt') == VERSION, 'Wrong application version.')
    git(repo, 'merge-base', '--is-ancestor', BASE, commit)
    media_policy.check_history(repo, commit, BASE)
    # A recovery bundle must not silently reintroduce prompt artifacts from later history.
    history = text(repo, 'log', '--format=', '--name-only', BASE + '..' + commit)
    require(not re.search(r'(?im)(^|/)[^\n/]*(prompt|god[-_. ]?tier)[^\n/]*($|/)', history),
            'A forbidden artifact exists in the incremental history; no bundle created.')
    outdir = outdir.expanduser().absolute()
    require(not outdir.is_symlink(), 'Output directory cannot be a symlink.')
    outdir = outdir.resolve()
    require(outdir != repo and repo not in outdir.parents, 'Keep release output outside the checkout.')
    outdir.mkdir(parents=True, exist_ok=True)
    source = source_archive(repo, commit)
    archive_name = PREFIX + '.tar.gz'
    checksum = (digest(source) + '  ' + archive_name + '\n').encode()
    # Reuse the exact verified bundle on retry; do not rely on pack ordering across Git versions.
    bundle_path = outdir / (PREFIX + '.bundle')
    if bundle_path.exists() or bundle_path.is_symlink():
        require(bundle_path.is_file() and not bundle_path.is_symlink(), 'Unsafe existing recovery bundle.')
        git(repo, 'bundle', 'verify', str(bundle_path))
        refs = dict(line.split()[::-1] for line in text(repo, 'bundle', 'list-heads', str(bundle_path)).splitlines())
        require(refs == {'refs/heads/main': commit, 'refs/tags/' + TAG: tag_object},
                'Recovery bundle names different refs; it was preserved.')
        bundle = bundle_path.read_bytes()
    else:
        with tempfile.TemporaryDirectory(prefix='securitysearch-bundle-') as tmp:
            target = Path(tmp) / 'release.bundle'
            git(repo, 'bundle', 'create', str(target), 'refs/heads/main', 'refs/tags/' + TAG, '^' + BASE)
            git(repo, 'bundle', 'verify', str(target))
            bundle = target.read_bytes()
    require(len(bundle) <= MAX_BYTES, 'Recovery bundle exceeds its size limit.')
    files = {archive_name: source, archive_name + '.sha256': checksum,
             bundle_path.name: bundle,
             bundle_path.name + '.sha256': (digest(bundle) + '  ' + bundle_path.name + '\n').encode(),
             'RELEASE-0.9.24.md': git(repo, 'show', commit + ':docs/RELEASE-0.9.24.md')}
    manifest = {'schema': 1, 'version': VERSION, 'asset_version': 28, 'commit': commit,
                'tag': TAG, 'tag_object': tag_object, 'bundle_requires_commit': BASE,
                'artifacts': {name: {'sha256': digest(data), 'size': len(data)} for name, data in files.items()},
                'published_assets': [archive_name, archive_name + '.sha256'],
                'validation_scope': 'Exact source/package provenance; not a live provider or VPS audit.'}
    files['release-manifest.json'] = (json.dumps(manifest, sort_keys=True, indent=2) + '\n').encode()
    files['SHA256SUMS'] = ''.join(digest(data) + '  ' + name + '\n' for name, data in sorted(files.items())).encode()
    for name, data in files.items():
        save_once(outdir / name, data)
    # Independently compare every archived payload and its provenance to git archive.
    spec = importlib.util.spec_from_file_location('release_publisher', repo / 'scripts/publish-v0.9.24.py')
    publisher = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(publisher)
    publisher.prepare(repo, outdir, repo / 'docs/RELEASE-0.9.24.md')
    require(text(repo, 'rev-parse', 'HEAD') == commit and not text(repo, 'status', '--porcelain'),
            'Checkout changed during packaging. No publication should be attempted.')
    print('Verified source package: ' + str(outdir / archive_name), flush=True)
    print('SHA-256: ' + digest(source), flush=True)
    print('Recovery bundle: ' + str(bundle_path), flush=True)
    print('Release commit: ' + commit + '\nAnnotated tag object: ' + tag_object, flush=True)
    return outdir


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--repo', type=Path, default=Path.cwd())
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    repo = args.repo.expanduser().resolve()
    return package(repo, args.output or repo.parent / 'securitysearch-release-v0.9.24')


if __name__ == '__main__':
    try:
        main()
    except (Exception, KeyboardInterrupt) as error:
        print('Stopped: ' + (str(error) if isinstance(error, Failure) else type(error).__name__) + '. Existing tags/assets were preserved.', file=sys.stderr)
        sys.exit(1)
