#!/usr/bin/env python3
"""Package an exact release and a fast-forward bundle for the known public history."""
import gzip
import hashlib
import io
from pathlib import Path
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parents[1]
VERSION = '0.9.19'
TAG = 'v' + VERSION
BASE = '34ac6854420a0dd41b05b1c558ce2d879068bf23'

LAUNCHER = '''#!/usr/bin/env fish
# Run inside this extracted kit; optional argument selects your existing checkout.
set -l kit (path dirname (status filename))
set kit (path resolve "$kit")
set -l repo ~/securitysearch
if test (count $argv) -gt 1
    echo 'Usage: fish publish-securitysearch-v@VERSION@.fish [checkout-directory]' >&2
    exit 2
end
if test (count $argv) -eq 1
    set repo "$argv[1]"
end
set repo (path resolve "$repo"); or exit 1
cd "$kit"; or exit 1
# Exact bytes are pinned here as well as in the included checksums.
printf '%s  %s\\n' '@ARCHIVE_SHA@' 'securitysearch-v@VERSION@.tar.gz' '@BUNDLE_SHA@' 'securitysearch-v@VERSION@.bundle' | sha256sum -c -
or exit 1
cd "$repo"; or exit 1
set -l top (git rev-parse --show-toplevel); or exit 1
if test "$top" != "$repo"
    echo 'Pass the Git checkout root.' >&2
    exit 1
end
if test (git symbolic-ref --quiet --short HEAD) != main
    echo 'Switch your existing checkout to main before importing this release.' >&2
    exit 1
end
if test -n "$(git status --porcelain)"
    echo 'Your checkout contains uncommitted or untracked files. Preserve them before importing.' >&2
    exit 1
end
git bundle verify "$kit/securitysearch-v@VERSION@.bundle"; or exit 1
git fetch --no-tags "$kit/securitysearch-v@VERSION@.bundle" 'refs/heads/main:refs/securitysearch-import/v@VERSION@-main' 'refs/tags/v@VERSION@:refs/securitysearch-import/v@VERSION@-tag'
or exit 1
set -l imported (git rev-parse 'refs/securitysearch-import/v@VERSION@-main^{commit}')
set -l imported_tag (git rev-parse refs/securitysearch-import/v@VERSION@-tag)
if test "$imported" != '@COMMIT@'; or test "$imported_tag" != '@TAG_OBJECT@'
    echo 'Imported commit/tag differs from this kit.' >&2
    exit 1
end
set -l old_tag (git rev-parse --verify --quiet refs/tags/v@VERSION@)
if test -n "$old_tag"; and test "$old_tag" != '@TAG_OBJECT@'
    echo 'A different local release tag already exists; it was preserved.' >&2
    exit 1
end
git merge-base --is-ancestor HEAD "$imported"
or begin
    echo 'Local main has additional commits. The checkout was preserved; integrate the release branch after review.' >&2
    exit 1
end
if test (git rev-parse HEAD) != "$imported"
    set -l backup "backup/securitysearch-before-v@VERSION@-$(date -u +%Y%m%dT%H%M%SZ)"
    git branch "$backup" HEAD; or exit 1
    printf 'Saved previous main as %s\\n' "$backup"
    git merge --ff-only "$imported"; or exit 1
end
if test -z "$old_tag"
    git update-ref refs/tags/v@VERSION@ '@TAG_OBJECT@' ''; or exit 1
end
printf 'Imported SecuritySearch v@VERSION@ at %s\\n' "$imported"
python3 "$repo/scripts/publish-release.py" --assets "$kit"
exit $status
'''


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT, text=True).strip()


def main():
    if git('status', '--porcelain'):
        raise SystemExit('Commit source changes before building a publication kit.')
    head = git('rev-parse', 'HEAD')
    if git('rev-parse', TAG + '^{commit}') != head or git('rev-parse', 'refs/heads/main') != head:
        raise SystemExit('HEAD, main and the release tag must identify the same commit.')
    if git('cat-file', '-t', TAG) != 'tag':
        raise SystemExit('Use an annotated release tag.')
    subprocess.run(['git', 'merge-base', '--is-ancestor', BASE, head], cwd=ROOT, check=True)
    dist = ROOT / 'dist'
    archive = dist / ('securitysearch-' + TAG + '.tar.gz')
    checksum = Path(str(archive) + '.sha256')
    archive_sha = hashlib.sha256(archive.read_bytes()).hexdigest()
    if checksum.read_text().strip() != archive_sha + '  ' + archive.name:
        raise SystemExit('Source archive checksum mismatch.')
    bundle = dist / ('securitysearch-' + TAG + '.bundle')
    subprocess.run(['git', 'bundle', 'create', str(bundle), 'refs/heads/main', 'refs/tags/' + TAG, '^' + BASE], cwd=ROOT, check=True)
    bundle_sha = hashlib.sha256(bundle.read_bytes()).hexdigest()
    values = {'VERSION': VERSION, 'ARCHIVE_SHA': archive_sha, 'BUNDLE_SHA': bundle_sha,
              'COMMIT': head, 'TAG_OBJECT': git('rev-parse', TAG)}
    launcher = LAUNCHER
    for key, value in values.items():
        launcher = launcher.replace('@' + key + '@', value)
    prefix = 'securitysearch-publication-' + TAG
    files = {
        archive.name: archive.read_bytes(), checksum.name: checksum.read_bytes(),
        bundle.name: bundle.read_bytes(), bundle.name + '.sha256': (bundle_sha + '  ' + bundle.name + '\n').encode(),
        'publish-securitysearch-' + TAG + '.fish': launcher.encode(),
        'README.md': (ROOT / 'docs/PUBLISHING.md').read_bytes(),
        'README.pt-BR.md': (ROOT / 'docs/PUBLISHING.pt-BR.md').read_bytes(),
        'RELEASE.md': (ROOT / 'docs/RELEASE-0.9.19.md').read_bytes(),
        'deploy-securitysearch-' + TAG + '.fish': (ROOT / 'scripts/deploy-ionos.fish').read_bytes(),
        'OPERATIONS-0.9.19.md': (ROOT / 'docs/OPERATIONS-0.9.19.md').read_bytes(),
        'OPERATIONS-0.9.19.pt-BR.md': (ROOT / 'docs/OPERATIONS-0.9.19.pt-BR.md').read_bytes(),
        'AUDIT-0.9.19.md': (ROOT / 'docs/AUDIT-0.9.19.md').read_bytes(),
    }
    output = dist / (prefix + '.tar.gz')
    epoch = int(git('show', '-s', '--format=%ct', head))
    with output.open('wb') as out, gzip.GzipFile(fileobj=out, mode='wb', filename='', mtime=0) as gz, tarfile.open(fileobj=gz, mode='w|') as tar:
        for name, data in sorted(files.items()):
            entry = tarfile.TarInfo(prefix + '/' + name)
            entry.size = len(data); entry.mode = 0o755 if name.endswith('.fish') else 0o644
            entry.mtime = epoch; entry.uid = entry.gid = 0
            tar.addfile(entry, io.BytesIO(data))
    digest = hashlib.sha256(output.read_bytes()).hexdigest()
    Path(str(output) + '.sha256').write_text(digest + '  ' + output.name + '\n')
    print('Created ' + str(output))


if __name__ == '__main__':
    main()
