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

if ! git rev-parse --verify --quiet HEAD >/dev/null; then
    echo "ERROR: no commit is available to package." >&2
    exit 1
fi

archive_dir="dist"
archive_name="securitysearch-v${version}.tar.gz"
mkdir -p "$archive_dir"
archive="$archive_dir/$archive_name"

git archive \
    --format=tar.gz \
    --prefix="securitysearch-v${version}/" \
    --output="$archive" \
    HEAD

(cd "$archive_dir" && sha256sum "$archive_name") > "${archive}.sha256"
echo "Created $archive"
echo "Checksum ${archive}.sha256"
