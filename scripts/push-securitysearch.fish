#!/usr/bin/env fish
# Enter four host-specific tokens privately; never put tokens in command arguments.
if test (count $argv) -gt 1
    echo 'Usage: fish push-securitysearch.fish [checkout]' >&2
    exit 2
end
set -l repo "$HOME/securitysearch"
if test (count $argv) -eq 1
    set repo "$argv[1]"
end
set -l here (dirname (status filename))
python3 "$here/push-remotes.py" "$repo"
exit $status
