#!/bin/sh
# Create a reproducible source release from the current committed revision.
# Usage: ./release.sh <version>

set -eu

version=${1:-}
if ! printf '%s' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    echo "Usage: $0 MAJOR.MINOR.PATCH" >&2
    exit 64
fi

if ! git diff --check || ! git diff --cached --check; then
    echo "ERROR: fix whitespace errors before packaging." >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "ERROR: commit or stash tracked changes before packaging." >&2
    exit 1
fi

# `git diff` ignores untracked files. Refuse to publish a source archive that
# silently omits a newly referenced controller, template, or other source file.
untracked=$(git ls-files --others --exclude-standard | grep -v '^dist/' || true)
if [ -n "$untracked" ]; then
    echo "ERROR: untracked source files would be missing from the archive:" >&2
    printf '%s\n' "$untracked" >&2
    exit 1
fi

if ! git rev-parse --verify --quiet HEAD >/dev/null; then
    echo "ERROR: no commit is available to package." >&2
    exit 1
fi

# A deletion commit is insufficient for explicitly forbidden prompt artifacts:
# refuse a release while any matching path remains reachable from a local ref.
if git log --all --format= --name-only |
   LC_ALL=C grep -Eiq '(^|/)[^/]*(prompt|god[-_. ]?tier)[^/]*($|/)'; then
    echo "ERROR: prompt artifact remains in reachable history." >&2
    exit 1
fi

archive_dir="dist"
archive_name="securitysearch-v${version}.tar.gz"
mkdir -p "$archive_dir"
archive="$archive_dir/$archive_name"
archive_prefix="securitysearch-v${version}"

tar_tmp=$(mktemp "${TMPDIR:-/tmp}/securitysearch-release.XXXXXX")
staging_tmp=$(mktemp -d "${TMPDIR:-/tmp}/securitysearch-release-dir.XXXXXX")
gzip_tmp=$(mktemp "$archive_dir/.${archive_name}.XXXXXX")
cleanup() {
    rm -f -- "$tar_tmp" "$gzip_tmp"
    rm -rf -- "$staging_tmp"
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM

git archive \
    --format=tar \
    --prefix="${archive_prefix}/" \
    --output="$tar_tmp" \
    HEAD

# Git cannot track an empty directory. The application expects icons/ to exist,
# while its generated icon cache remains intentionally excluded from releases.
# Add the empty directory with commit-derived metadata so repeated builds of the
# same revision remain byte-for-byte reproducible.
if ! tar -tf "$tar_tmp" | grep -Fxq "${archive_prefix}/icons/"; then
    commit_epoch=$(git show -s --format=%ct HEAD)
    mkdir -p "$staging_tmp/$archive_prefix/icons"
    touch -d "@$commit_epoch" \
        "$staging_tmp/$archive_prefix" \
        "$staging_tmp/$archive_prefix/icons"
    tar --append \
        --file="$tar_tmp" \
        --owner=0 \
        --group=0 \
        --numeric-owner \
        --mode='u=rwx,go=rx' \
        --mtime="@$commit_epoch" \
        -C "$staging_tmp" \
        "${archive_prefix}/icons"
fi

gzip -n -9 < "$tar_tmp" > "$gzip_tmp"
archive_listing=$(tar -tzf "$gzip_tmp")
for required_path in \
    "${archive_prefix}/icons/" \
    "${archive_prefix}/banner/securitysearch.webp" \
    "${archive_prefix}/static/misc/secops.gif" \
    "${archive_prefix}/static/images-fallback.js" \
    "${archive_prefix}/static/images-motion.js" \
    "${archive_prefix}/lib/animated_preview.php"
do
    printf '%s\n' "$archive_listing" | grep -Fxq "$required_path" || {
        echo "ERROR: release archive is missing $required_path." >&2
        exit 1
    }
done
if printf '%s\n' "$archive_listing" |
   LC_ALL=C grep -Eiq '(data/api_keys/|securitysearch\.zip|Kuruminha\.css|mimi\.jpg|(^|/)[^/]*(prompt|god[-_. ]?tier)[^/]*($|/))'; then
    echo "ERROR: release archive contains forbidden content." >&2
    exit 1
fi
mv -f -- "$gzip_tmp" "$archive"

(cd "$archive_dir" && sha256sum "$archive_name") > "${archive}.sha256"
echo "Created $archive"
echo "Checksum ${archive}.sha256"
