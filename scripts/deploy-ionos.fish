#!/usr/bin/env fish
# Invoke only the matching, checksum-verified self-contained operator.
set -l launcher "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish"
if not test -f "$launcher"; or not test -f "$launcher.sha256"
    echo "Download the v0.9.41 deploy-ionos launcher and checksum into ~/Downloads first." >&2
    exit 2
end
pushd "$HOME/Downloads" >/dev/null; or exit 1
sha256sum --check (basename "$launcher.sha256"); or exit 1
popd >/dev/null; or exit 1
fish --no-config --no-execute "$launcher"; or exit 1
exec fish --no-config "$launcher" $argv
