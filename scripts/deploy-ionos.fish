#!/usr/bin/env fish
# Save the release archive and checksum into ~/Downloads before running.
cd ~/Downloads; or exit 1
sha256sum -c securitysearch-v0.9.19.tar.gz.sha256; or exit 1
scp -P 5119 securitysearch-v0.9.19.tar.gz securitysearch-v0.9.19.tar.gz.sha256 root@securityops.co:/root/; or exit 1
ssh -p 5119 root@securityops.co 'set -eu; umask 077; cd /root; sha256sum -c securitysearch-v0.9.19.tar.gz.sha256; release_dir=$(mktemp -d /root/securitysearch-v0.9.19-XXXXXXXX); tar -xzf securitysearch-v0.9.19.tar.gz -C "$release_dir" --strip-components=1; python3 "$release_dir/scripts/deploy-ionos.py"'
