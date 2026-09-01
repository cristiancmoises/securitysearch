#!/bin/sh
# Check a private scraper pool without sending a search query.
set -eu

pool_name=${FOURGET_PROXY_GOOGLE:-}
pool_file=${1:-}

if [ -z "$pool_file" ]; then
    if [ -z "$pool_name" ]; then
        echo "Usage: FOURGET_PROXY_GOOGLE=<pool> $0 /path/to/<pool>.txt" >&2
        exit 2
    fi
    pool_file="data/proxies/$pool_name.txt"
fi

if [ ! -r "$pool_file" ]; then
    echo "Egress pool is not readable: $pool_file" >&2
    exit 2
fi

command -v curl >/dev/null 2>&1 || {
    echo "curl is required" >&2
    exit 2
}

line_number=0
tested=0
failed=0

while IFS= read -r line || [ -n "$line" ]; do
    line_number=$((line_number + 1))
    case "$line" in
        ""|[[:space:]]*|\#*) continue ;;
    esac

    kind=$(printf '%s' "$line" | cut -d: -f1)
    host=$(printf '%s' "$line" | cut -d: -f2)
    port=$(printf '%s' "$line" | cut -d: -f3)
    user=$(printf '%s' "$line" | cut -d: -f4)
    password=$(printf '%s' "$line" | cut -d: -f5-)

    # Build curl arguments without ever printing the credential-bearing line.
    set -- --connect-timeout 8 --max-time 15 -fsS \
        -A 'SecuritySearch-egress-check/1.0'
    case "$kind" in
        raw_ip)
            label=direct
            ;;
        http|https)
            if [ -z "$host" ] || [ -z "$port" ]; then
                echo "line $line_number: invalid $kind proxy endpoint" >&2
                failed=$((failed + 1))
                continue
            fi
            set -- "$@" --proxy "$kind://$host:$port"
            label="$kind://$host:$port"
            ;;
        socks4|socks4a|socks5|socks5_hostname|socks5h|socks5a)
            if [ -z "$host" ] || [ -z "$port" ]; then
                echo "line $line_number: invalid $kind proxy endpoint" >&2
                failed=$((failed + 1))
                continue
            fi
            case "$kind" in
                socks4) set -- "$@" --socks4 "$host:$port" ;;
                socks4a) set -- "$@" --socks4a "$host:$port" ;;
                socks5) set -- "$@" --socks5 "$host:$port" ;;
                *) set -- "$@" --socks5-hostname "$host:$port" ;;
            esac
            label="$kind://$host:$port"
            ;;
        *)
            echo "line $line_number: unsupported proxy type '$kind'" >&2
            failed=$((failed + 1))
            continue
            ;;
    esac

    if [ "$kind" != raw_ip ] && [ -n "$user" ]; then
        set -- "$@" --proxy-user "$user:$password"
    fi

    public_ip=$(curl "$@" https://api.ipify.org 2>/dev/null || true)
    google_code=$(curl "$@" -o /dev/null -w '%{http_code}' \
        https://www.google.com/robots.txt 2>/dev/null || true)
    tested=$((tested + 1))

    case "$google_code" in
        2*|3*)
            printf 'line=%s endpoint=%s public_ip=%s google_http=%s ok\n' \
                "$line_number" "$label" "${public_ip:-unknown}" "$google_code"
            ;;
        *)
            printf 'line=%s endpoint=%s public_ip=%s google_http=%s failed\n' \
                "$line_number" "$label" "${public_ip:-unknown}" "${google_code:-error}"
            failed=$((failed + 1))
            ;;
    esac
done < "$pool_file"

if [ "$tested" -eq 0 ]; then
    echo "Egress pool contains no active entries: $pool_file" >&2
    exit 2
fi

printf 'tested=%s failed=%s pool=%s\n' "$tested" "$failed" "$pool_file"
[ "$failed" -eq 0 ]
