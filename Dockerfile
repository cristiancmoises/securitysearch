# Security Search (4get fork) — hardened, build-resilient image.
#
# Builds on Alpine 3.21. Includes apk mirror failover — the default
# dl-cdn.alpinelinux.org is geo-routed and sometimes flaky from Brazil,
# Latin America, and parts of Europe.  If a mirror is down at build
# time, the next one is tried automatically.

FROM alpine:3.21

LABEL org.opencontainers.image.title="Security Search"
LABEL org.opencontainers.image.description="Privacy-first proxy search engine (4get fork)"
LABEL org.opencontainers.image.source="https://git.securityops.co/securityops/securitysearch"
LABEL org.opencontainers.image.licenses="AGPL-3.0"
LABEL org.opencontainers.image.vendor="SecurityOps"

WORKDIR /var/www/html/4get

# ---------------------------------------------------------------------------
# Mirror failover for apk
# ---------------------------------------------------------------------------
# Try the default CDN first. If that fails, sequentially swap to known-fast
# regional mirrors. The build only fails if ALL of them are unreachable.
# ---------------------------------------------------------------------------
RUN set -eux; \
    \
    try_apk_update() { \
        for try in 1 2 3; do \
            if apk update 2>&1 | tee /tmp/apk.log | grep -q '^OK:'; then \
                return 0; \
            fi; \
            echo "apk update attempt $try failed, retrying in 3s..."; \
            sleep 3; \
        done; \
        return 1; \
    }; \
    \
    MIRRORS="\
        https://dl-cdn.alpinelinux.org/alpine \
        https://uk.alpinelinux.org/alpine \
        https://mirror.leaseweb.net/alpine \
        https://mirrors.edge.kernel.org/alpine \
        https://mirror.csclub.uwaterloo.ca/alpine \
    "; \
    \
    success=0; \
    for mirror in $MIRRORS; do \
        echo "=== Trying mirror: $mirror ==="; \
        printf '%s/v3.21/main\n%s/v3.21/community\n' "$mirror" "$mirror" \
            > /etc/apk/repositories; \
        if try_apk_update; then \
            echo "=== Mirror $mirror works ==="; \
            success=1; \
            break; \
        fi; \
    done; \
    \
    if [ "$success" != "1" ]; then \
        echo "=== ALL MIRRORS FAILED ==="; \
        cat /tmp/apk.log; \
        exit 1; \
    fi; \
    \
    apk upgrade --no-cache && \
    apk add --no-cache \
        apache2 apache2-ssl \
        php84 php84-apache2 \
        php84-fileinfo php84-openssl php84-iconv php84-common \
        php84-dom php84-sodium php84-curl php84-pecl-apcu \
        php84-pecl-imagick php84-mbstring php84-opcache \
        php84-session php84-tokenizer php84-xml \
        curl tini ca-certificates \
        imagemagick imagemagick-webp imagemagick-jpeg && \
  ln -sf /usr/bin/php84 /usr/bin/php && \    
rm -rf /var/cache/apk/* /tmp/*

# Copy app source. .dockerignore excludes .git, *.bak, docs, icons cache, etc.
COPY . .

# OPcache config — biggest single perf win.
# validate_timestamps=0 means PHP NEVER re-reads source files after the
# image is built.  Perfect for immutable container deploys.
RUN printf '%s\n' \
    'opcache.enable=1' \
    'opcache.enable_cli=0' \
    'opcache.memory_consumption=128' \
    'opcache.interned_strings_buffer=16' \
    'opcache.max_accelerated_files=10000' \
    'opcache.validate_timestamps=0' \
    'opcache.save_comments=1' \
    'opcache.fast_shutdown=1' \
    'opcache.revalidate_freq=0' \
    > /etc/php84/conf.d/00_opcache.ini

# Security-focused PHP defaults.
RUN printf '%s\n' \
    'expose_php = Off' \
    'display_errors = Off' \
    'log_errors = On' \
    'error_log = /dev/stderr' \
    'session.cookie_httponly = 1' \
    'session.cookie_samesite = "Strict"' \
    'session.use_strict_mode = 1' \
    'allow_url_fopen = Off' \
    'allow_url_include = Off' \
    'max_execution_time = 30' \
    'memory_limit = 256M' \
    'post_max_size = 8M' \
    'upload_max_filesize = 8M' \
    > /etc/php84/conf.d/99_security.ini

# Tighten filesystem permissions.  Apache user owns everything; only
# `icons/` is writable for the favicon cache.
RUN chown -R apache:apache /var/www/html/4get && \
    find /var/www/html/4get -type d -exec chmod 755 {} \; && \
    find /var/www/html/4get -type f -exec chmod 644 {} \; && \
    chmod 775 /var/www/html/4get/icons && \
    chmod +x /var/www/html/4get/docker/docker-entrypoint.sh

EXPOSE 80
EXPOSE 443

ENV FOURGET_PROTO=http

# Healthcheck — Docker / orchestrators use this to detect a dead container.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1/ || exit 1

# tini reaps zombie processes from Apache's prefork model.
ENTRYPOINT ["/sbin/tini", "--", "./docker/docker-entrypoint.sh"]
CMD ["start"]
