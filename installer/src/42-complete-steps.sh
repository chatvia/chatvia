#------------------------------------------------------------------------------
# Complete installation: the install steps, and what they write
#------------------------------------------------------------------------------

# The certificate, when the answers ask for one (or an earlier attempt could
# not get it). Without it, Chatvia runs on HTTP: the next run of the
# installer tries again.
native_https() {
    if run_step "Getting an HTTPS certificate for $DOMAIN" native_get_certificate; then
        HTTPS=yes
        HTTPS_SERVER=caddy
        APP_URL="https://$DOMAIN"
        if [ "$DRY_RUN" = 0 ]; then conf_set HTTPS_SERVER caddy; fi
    else
        HTTPS=pending
        APP_URL="http://$DOMAIN"
        warn 'No HTTPS certificate yet, so ChatVia.ai runs on HTTP for now.'
        info "Once $DOMAIN points at this server, run this installer again to get it."
    fi
    if [ "$DRY_RUN" = 0 ]; then
        conf_set HTTPS "$HTTPS"
        conf_set APP_URL "$APP_URL"
    fi
}

native_add_swap() {
    if [ ! -f /swapfile ]; then
        fallocate -l 4G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=4096
        chmod 600 /swapfile
        mkswap /swapfile
    fi
    if ! swapon --show=NAME --noheadings | grep -qx /swapfile; then swapon /swapfile; fi
    if ! grep -q '^/swapfile ' /etc/fstab; then echo '/swapfile none swap sw 0 0' >>/etc/fstab; fi
}

native_update_system() {
    apt_get update
    apt_get upgrade
}

# The newest Redis comes from packages.redis.io when it carries this Ubuntu
# release; otherwise Ubuntu's own redis-server stays (26.04 ships Redis 8,
# 24.04 Redis 7).
# Best effort on purpose: any miss leaves the system repositories as they
# are, and the install goes on.
native_add_redis_repo() {
    local release keyring=/usr/share/keyrings/redis-archive-keyring.asc
    # shellcheck source=/dev/null
    release=$(. /etc/os-release && printf '%s' "${VERSION_CODENAME:-}") || return 0
    [ -n "$release" ] || return 0
    curl -fsI --max-time 10 "https://packages.redis.io/deb/dists/$release/Release" >/dev/null 2>&1 || {
        echo "packages.redis.io carries no $release packages yet: Ubuntu's own Redis it is."
        return 0
    }
    curl -fsSL --max-time 30 https://packages.redis.io/gpg -o "$keyring" || return 0
    printf 'deb [signed-by=%s] https://packages.redis.io/deb %s main\n' "$keyring" "$release" \
        >/etc/apt/sources.list.d/redis.list
    apt_get update
}

# Written by hand: add-apt-repository is not on every minimal server. The
# key is the PPA's signing key, checked by its fingerprint. Its packages are
# newer than Ubuntu's, so apt takes them without a pin.
native_add_php_repo() {
    local release keyring=/usr/share/keyrings/ondrej-php.asc
    # shellcheck source=/dev/null
    release=$(. /etc/os-release && printf '%s' "${VERSION_CODENAME:-}")
    apt_get install gnupg
    curl -fsSL --max-time 30 \
        "https://keyserver.ubuntu.com/pks/lookup?op=get&options=mr&search=0x$PHP_PPA_KEY" -o "$keyring.new"
    if ! gpg --show-keys --with-colons "$keyring.new" | grep -q "^fpr:*:$PHP_PPA_KEY:"; then
        rm -f "$keyring.new"
        echo "The key from keyserver.ubuntu.com is not the PPA's signing key ($PHP_PPA_KEY)."
        return 1
    fi
    mv "$keyring.new" "$keyring"
    printf 'deb [signed-by=%s] %s %s main\n' "$keyring" "$PHP_PPA_URL" "$release" \
        >/etc/apt/sources.list.d/ondrej-php.list
    apt_get update
}

# Ubuntu 26.04 has PostgreSQL 18 in its own archive. Older releases get it,
# and the pg_dump that backs up an existing server, from the PostgreSQL
# archive (apt.postgresql.org). Nothing is added when Ubuntu has them.
native_add_postgres_repo() {
    local release keyring=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc
    if apt_has_candidate "postgresql-client-$DB_MAJOR" &&
        { [ "$DATABASE" = existing ] || apt_has_candidate "postgresql-$NATIVE_PG"; }; then
        echo "Ubuntu has the PostgreSQL $DB_MAJOR packages."
        return 0
    fi
    # shellcheck source=/dev/null
    release=$(. /etc/os-release && printf '%s' "${VERSION_CODENAME:-}")
    install -d "$(dirname "$keyring")"
    curl -fsSL --max-time 30 "$PGDG_KEY_URL" -o "$keyring" || return
    printf 'deb [signed-by=%s] %s %s-pgdg main\n' "$keyring" "$PGDG_URL" "$release" \
        >/etc/apt/sources.list.d/pgdg.list
    apt_get update
}

apt_has_candidate() {
    apt-cache policy "$1" 2>/dev/null | grep -q 'Candidate: [0-9]'
}

native_php_packages() {
    local php=php$NATIVE_PHP
    printf '%s\n' "$php-fpm" "$php-cli" "$php-pgsql" "$php-redis" "$php-intl" "$php-mbstring" \
        "$php-curl" "$php-zip" "$php-gd" "$php-xml"
}

# The PPA carries other PHP versions too: /usr/bin/php stays on this one,
# which Supervisor, cron and Deployer run.
native_check_php() {
    local have
    update-alternatives --set php "/usr/bin/php$NATIVE_PHP"
    have=$("php$NATIVE_PHP" -r 'echo PHP_VERSION;')
    if ! version_at_least "$have" "$NATIVE_MIN_PHP"; then
        echo "PHP $have is installed, but ChatVia.ai needs $NATIVE_MIN_PHP or newer."
        return 1
    fi
    php -v
}

native_install_packages() {
    # Poppler, pandoc, catdoc and ffmpeg read file sources, WeasyPrint makes
    # PDF exports, and the Noto fonts give it every script. The backup
    # programs come with native_backup_packages. Node.js is the next step.
    # shellcheck disable=SC2046
    apt_get install \
        git curl unzip ca-certificates cron ufw \
        $(native_php_packages) $(native_composer_package) \
        nginx redis-server supervisor fail2ban \
        $(native_postgres_packages) \
        $(native_backup_packages) \
        poppler-utils pandoc catdoc ffmpeg weasyprint fonts-noto-core fonts-noto-cjk || return
    native_check_php || return
    native_check_backup_programs || return
    native_install_composer || return
    composer --version
}

# Ubuntu's Composer, when it is new enough.
native_composer_package() {
    local candidate
    candidate=$(apt-cache policy composer 2>/dev/null | sed -n 's/^ *Candidate: *//p')
    candidate=${candidate#*:}
    case $candidate in
        [0-9]*) if version_at_least "${candidate%%[-+~]*}" "$MIN_COMPOSER_VERSION"; then echo composer; fi ;;
    esac
}

# Otherwise the current Composer from getcomposer.org, its installer checked
# against the published hash.
native_install_composer() {
    local have setup="$WORK_DIR/composer-setup.php" expected
    have=$(composer --no-ansi --version 2>/dev/null | sed -n 's/^Composer version \([0-9.]*\).*/\1/p')
    if [ -n "$have" ] && version_at_least "$have" "$MIN_COMPOSER_VERSION"; then return 0; fi
    expected=$(curl -fsSL --max-time 30 https://composer.github.io/installer.sig) || return
    curl -fsSL --max-time 30 https://getcomposer.org/installer -o "$setup" || return
    if [ "$(sha384sum "$setup" | cut -d' ' -f1)" != "$expected" ]; then
        rm -f "$setup"
        echo 'The Composer installer from getcomposer.org does not match its published hash.'
        return 1
    fi
    "php$NATIVE_PHP" "$setup" --quiet --install-dir=/usr/local/bin --filename=composer || return
    rm -f "$setup"
    hash -r
}

# PostgreSQL with pgvector, and pg_partman, which splits the analytics event
# log into monthly partitions. None with an existing PostgreSQL server: it
# has them already.
native_postgres_packages() {
    [ "$DATABASE" = existing ] && return 0
    printf '%s\n' "postgresql-$NATIVE_PG" "postgresql-$NATIVE_PG-pgvector" "postgresql-$NATIVE_PG-partman"
}

# The packages of the programs Settings → Backups runs: pg_dump and
# pg_restore (the client of the PostgreSQL installed here: an older pg_dump
# refuses a newer server), redis-cli and gpg. Named here although PostgreSQL
# and Redis bring them too, so a change of those packages cannot drop them.
native_backup_packages() {
    printf '%s\n' "postgresql-client-$DB_MAJOR" redis-tools gnupg
}

# An upgrade installs them too: an install from before Settings → Backups,
# or one where they were removed, gets them back.
native_install_backup_programs() {
    # shellcheck disable=SC2046
    apt_get install $(native_backup_packages) || return
    native_check_backup_programs
}

# Fails, naming them, when a backup program is missing, or when pg_dump is
# older than the PostgreSQL server.
native_check_backup_programs() {
    local program missing='' major
    for program in pg_dump pg_restore redis-cli gpg; do
        if ! command -v "$program" >/dev/null 2>&1; then missing="$missing $program"; fi
    done
    if [ -n "$missing" ]; then
        echo "Not found:$missing. Backups (Settings → Backups) need them." >&2
        return 1
    fi
    major=$(pg_dump --version | sed -n 's/.*(PostgreSQL) \([0-9][0-9]*\).*/\1/p')
    if [ "${major:-0}" -lt "$DB_MAJOR" ]; then
        echo "pg_dump is version ${major:-unknown}, older than PostgreSQL $DB_MAJOR: backups would fail." >&2
        return 1
    fi
    pg_dump --version || return
    pg_restore --version || return
    redis-cli --version || return
    gpg --version | sed -n 1p
}

# An upgrade brings PHP up to the newest release first: a new ChatVia.ai may
# need it. Installs from before the PPA get it here.
native_update_php() {
    native_add_php_repo
    # shellcheck disable=SC2046
    apt_get install $(native_php_packages)
    native_check_php
    systemctl restart "php$NATIVE_PHP-fpm"
}

# Node.js builds the frontend and Shakomako, the chat widget. Shakomako's
# build runs TypeScript files directly, with Node's own type stripping
# (22.18 or newer), which Ubuntu's nodejs package is built without. So
# Node.js 22 comes from NodeSource, pinned above Ubuntu's.
native_install_node() {
    if node_can_build; then
        echo "Node.js $(node -v) is installed."
        return 0
    fi
    install -d -m 755 /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key -o /etc/apt/keyrings/nodesource.asc
    echo 'deb [signed-by=/etc/apt/keyrings/nodesource.asc] https://deb.nodesource.com/node_22.x nodistro main' \
        >/etc/apt/sources.list.d/nodesource.list
    printf '%s\n' '# Written by the Chatvia installer: Node.js from NodeSource, not Ubuntu.' \
        'Package: nodejs' 'Pin: origin deb.nodesource.com' 'Pin-Priority: 1001' >/etc/apt/preferences.d/nodesource
    apt_get update
    apt_get install nodejs
    node_can_build || {
        echo "Node.js $(node -v 2>/dev/null) cannot run TypeScript files." >&2
        return 1
    }
    echo "Node.js $(node -v), npm $(npm -v)"
}

# Node.js 22.18 or newer, with type stripping.
node_can_build() {
    local version
    version=$(node -v 2>/dev/null) || return 1
    version_at_least "${version#v}" "$MIN_NODE_VERSION" || return 1
    printf 'const ok: number = 1;\n' >"$WORK_DIR/check.ts"
    node "$WORK_DIR/check.ts" >/dev/null 2>&1
}

# pg_textsearch (BM25 keyword search) comes as a package from its newest
# GitHub release (GitHub redirects releases/latest to its tag, with no API
# rate limit); pgvector and pg_partman come from Ubuntu (or the PostgreSQL
# archive, where it was added).
native_install_extensions() {
    local arch dir="$WORK_DIR/debs" file url version
    arch=$(dpkg --print-architecture)
    url=$(curl -fsSLI -o /dev/null -w '%{url_effective}' "$PG_TEXTSEARCH_RELEASES/latest") || return
    version=${url##*/v}
    case "$version" in
        [0-9]*.[0-9]*) ;;
        *) echo "Could not read the newest pg_textsearch release from $url." >&2; return 1 ;;
    esac
    if [ "$(dpkg-query -W -f='${Version}' "pg-textsearch-postgresql-$NATIVE_PG" 2>/dev/null)" = "$version-1" ]; then
        echo "pg-textsearch-postgresql-$NATIVE_PG $version is installed."
        return 0
    fi
    mkdir -p "$dir"
    file="$dir/pg-textsearch-v$version-pg$NATIVE_PG-$arch.zip"
    curl -fsSL -o "$file" \
        "$PG_TEXTSEARCH_RELEASES/download/v$version/pg-textsearch-v$version-pg$NATIVE_PG-$arch.zip"
    unzip -o -q "$file" -d "$dir"
    apt_get install "$dir/pg-textsearch-postgresql-${NATIVE_PG}_$version-1_$arch.deb"
}

# The tuned PostgreSQL settings for the memory, processors and connections it
# gets: pgtune's numbers for a web application on SSD storage, as one
# "name value" line each. docker/manage carries the same function for
# ./manage postgres tune (the two files are shipped apart): change it there too.
# pg_tune_settings RAM_MB PROCESSORS MAX_CONNECTIONS
pg_tune_settings() {
    local ram_mb=$1 cpus=$2 connections=$3
    local shared_mb cache_mb maintenance_mb gather work_kb wal_kb pair name kb

    # A quarter of the memory for PostgreSQL's own cache (at most 8 GB), and
    # three quarters as the planner's guess of what the OS caches on top.
    shared_mb=$((ram_mb / 4))
    if [ "$shared_mb" -gt 8192 ]; then shared_mb=8192; fi
    cache_mb=$((ram_mb * 3 / 4))

    # Index builds work in maintenance_work_mem: the search indexes
    # (HNSW, bm25) build much faster with room here.
    maintenance_mb=$((ram_mb / 16))
    if [ "$maintenance_mb" -gt 2048 ]; then maintenance_mb=2048; fi

    # Up to half the processors (at most 4) may serve one query together.
    gather=$(((cpus + 1) / 2))
    if [ "$gather" -gt 4 ]; then gather=4; fi

    # The memory left after shared_buffers, shared over every connection
    # running up to 3 sorts, each over its parallel workers. At least 64 kB.
    work_kb=$(((ram_mb - shared_mb) * 1024 / (connections * 3) / gather))
    if [ "$work_kb" -lt 64 ]; then work_kb=64; fi

    # 3% of shared_buffers for the WAL, at most 16 MB (14 MB and up rounds
    # to the usual 16 MB; wal_buffers counts in blocks of 8 kB).
    wal_kb=$((shared_mb * 1024 * 3 / 100 / 8 * 8))
    if [ "$wal_kb" -ge 14336 ]; then wal_kb=16384; fi

    # Sizes print the way PostgreSQL shows them back (the largest whole
    # unit), so a second run can see that nothing changed.
    for pair in "shared_buffers $((shared_mb * 1024))" \
        "effective_cache_size $((cache_mb * 1024))" \
        "maintenance_work_mem $((maintenance_mb * 1024))" \
        "work_mem $work_kb" \
        "wal_buffers $wal_kb"; do
        name=${pair% *} kb=${pair#* }
        if [ $((kb % 1048576)) -eq 0 ]; then
            printf '%s %sGB\n' "$name" $((kb / 1048576))
        elif [ $((kb % 1024)) -eq 0 ]; then
            printf '%s %sMB\n' "$name" $((kb / 1024))
        else
            printf '%s %skB\n' "$name" "$kb"
        fi
    done
    printf 'min_wal_size 1GB\n'
    printf 'max_wal_size 4GB\n'
    # Spread each checkpoint's writes out, and tell the planner this is an
    # SSD: random reads cost little, and many can be in flight.
    printf 'checkpoint_completion_target 0.9\n'
    printf 'random_page_cost 1.1\n'
    printf 'effective_io_concurrency 200\n'
    # Worker slots, never below PostgreSQL's default of 8: extensions take
    # slots here too. How many actually run in parallel follows the
    # processors.
    printf 'max_worker_processes %s\n' $((cpus > 8 ? cpus : 8))
    printf 'max_parallel_workers_per_gather %s\n' "$gather"
    printf 'max_parallel_workers %s\n' "$cpus"
    printf 'max_parallel_maintenance_workers %s\n' "$gather"
}

# The whole of PostgreSQL's conf.d/chatvia.conf: the search libraries, plus
# sizes computed for this server's memory and processors (the same formulas
# as ./manage postgres tune). The installer owns the file and writes it anew
# on every run, so a server that grew gets new sizes; put your own settings
# in a file read after it, like zz-mine.conf.
native_postgres_conf() {
    local ram_mb cpus name value
    ram_mb=$(($(meminfo_kb MemTotal) / 1024))
    if [ "$ram_mb" -eq 0 ]; then
        # A dry run on macOS: /proc/meminfo is Linux-only.
        ram_mb=$(($(sysctl -n hw.memsize 2>/dev/null || echo 0) / 1048576))
    fi
    cpus=$(nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null) || cpus=1
    case $cpus in '' | *[!0-9]* | 0) cpus=1 ;; esac

    cat <<'EOF'
# Written by the Chatvia installer. Chatvia's keyword search needs
# pg_textsearch loaded when PostgreSQL starts; add your own after it.
shared_preload_libraries = 'pg_textsearch'
EOF
    # No memory reading, no tuning: PostgreSQL's own defaults stay.
    if [ "$ram_mb" -eq 0 ]; then return 0; fi

    printf '\n# Sized by the installer for %s of memory and %s processors: the usual\n' \
        "$(awk -v mb="$ram_mb" 'BEGIN { printf "%.1f GB", mb / 1024 }')" "$cpus"
    printf '# numbers for a web app on SSD storage. Every run of the installer computes\n'
    printf '# them again; settings in a conf.d file read after this one win over them.\n'
    pg_tune_settings "$ram_mb" "$cpus" 100 | while read -r name value; do
        printf "%s = '%s'\t# set by Chatvia tune\n" "$name" "$value"
    done
}

native_configure_postgres() {
    local conf="/etc/postgresql/$NATIVE_PG/main/conf.d/chatvia.conf"
    install -d "$(dirname "$conf")"
    native_postgres_conf >"$conf"
    chmod 644 "$conf"
    systemctl enable postgresql
    systemctl restart postgresql
    wait_until 60 psql_admin -Atc 'SELECT 1'
    psql_admin -Atc 'SHOW shared_preload_libraries'
    psql_admin -Atc 'SHOW shared_buffers'
}

native_create_user() {
    if ! id -u "$NATIVE_USER" >/dev/null 2>&1; then
        adduser --system --group --shell /bin/bash --home "$NATIVE_HOME" "$NATIVE_USER"
    fi
    install -d -o "$NATIVE_USER" -g "$NATIVE_USER" -m 750 "$NATIVE_HOME"
    install -d -o "$NATIVE_USER" -g "$NATIVE_USER" -m 755 "$NATIVE_ROOT" "$NATIVE_ROOT/shared"
}

# Two PHP-FPM pools of its own, run by chatvia: one for pages and one for chat
# answers (nginx sends those to it), and room in OPcache for all of Chatvia's
# PHP files (about 30,000).
native_configure_php() {
    cat >"/etc/php/$NATIVE_PHP/fpm/pool.d/chatvia.conf" <<'EOF'
#@include templates/php-fpm-pool.conf
EOF
    cat >"/etc/php/$NATIVE_PHP/fpm/conf.d/99-chatvia.ini" <<'EOF'
; Written by the Chatvia installer.
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 65407
EOF
    "php-fpm$NATIVE_PHP" -t
    systemctl enable "php$NATIVE_PHP-fpm"
    systemctl restart "php$NATIVE_PHP-fpm"
}

# The site. nginx answers on port 80 itself until Caddy runs (no HTTPS, or
# until Caddy has its certificate); then Caddy holds ports 80 and 443 and
# nginx answers it on 127.0.0.1:8081. An install that runs again keeps
# whichever it has.
native_configure_nginx() {
    local mode=direct
    if systemctl is-active --quiet caddy; then mode=behind; fi
    native_write_nginx_site "$mode"
    ln -sfn /etc/nginx/sites-available/chatvia /etc/nginx/sites-enabled/chatvia
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    systemctl enable nginx
    systemctl reload-or-restart nginx
}

# native_write_nginx_site direct|behind
native_write_nginx_site() {
    local name=$DOMAIN listen=()
    if is_ip "$DOMAIN"; then name=_; fi
    if [ "$1" = behind ]; then
        name=_
        listen=(-e 's|^    listen 80;$|    listen 127.0.0.1:8081;|' -e '/^    listen \[::\]:80;$/d')
    fi
    sed "${listen[@]}" -e "s|__SERVER_NAME__|$name|" -e "s|__ROOT__|$NATIVE_ROOT/current/public|" >/etc/nginx/sites-available/chatvia <<'EOF'
#@include templates/nginx-site.conf
EOF
}

# Caddy from its own repository, signed by the key with this fingerprint.
# Masked while it installs: the package starts it at once on port 80, where
# nginx answers.
native_install_caddy() {
    local key=65760C51EDEA2017CEA2CA15155B6D79CA56EA34 keyring=/usr/share/keyrings/caddy-stable-archive-keyring.gpg
    command -v caddy >/dev/null 2>&1 && return 0
    apt_get install gnupg || return
    curl -fsSL --max-time 30 https://dl.cloudsmith.io/public/caddy/stable/gpg.key -o "$keyring.asc" || return
    if ! gpg --show-keys --with-colons "$keyring.asc" | grep -q "^fpr:*:$key:"; then
        rm -f "$keyring.asc"
        echo "The key from dl.cloudsmith.io is not Caddy's signing key ($key)."
        return 1
    fi
    gpg --dearmor --yes -o "$keyring" "$keyring.asc" || return
    rm -f "$keyring.asc"
    printf 'deb [signed-by=%s] https://dl.cloudsmith.io/public/caddy/stable/deb/debian any-version main\n' "$keyring" \
        >/etc/apt/sources.list.d/caddy-stable.list
    apt_get update || return
    systemctl mask caddy
    apt_get install caddy
}

# HTTPS through Caddy. It takes ports 80 and 443, gets the certificate for
# DOMAIN (and later, on demand, for agents' custom domains) and passes every
# request to nginx on 127.0.0.1:8081. When no certificate comes, nginx gets
# its site back as it was (on port 80, or Certbot's), and Caddy stops.
native_get_certificate() {
    local site=/etc/nginx/sites-available/chatvia
    native_install_caddy || return
    install -d /etc/caddy
    sed -e "s|__DOMAIN__|$DOMAIN|" -e "s|__EMAIL__|$ADMIN_EMAIL|" >/etc/caddy/Caddyfile <<'EOF'
#@include templates/Caddyfile
EOF
    caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile || return
    cp "$site" "$site.before-caddy"
    native_write_nginx_site behind
    if nginx -t && systemctl reload nginx && systemctl unmask caddy && systemctl enable caddy &&
        systemctl restart caddy && wait_until 120 native_https_answers; then
        rm -f "$site.before-caddy"
        return 0
    fi
    echo "Caddy got no certificate for $DOMAIN: does $DOMAIN point at this server, and are ports 80 and 443 open?"
    systemctl disable --now caddy || true
    mv "$site.before-caddy" "$site"
    nginx -t && systemctl reload nginx
    return 1
}

# Whether DOMAIN answers over HTTPS on this server with a valid certificate.
# Any answer counts: before the first deploy there is no app yet.
native_https_answers() {
    curl -sS -o /dev/null --max-time 10 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/"
}

# The .env lines for Caddy in front: believe what it says about each visitor
# (the address, and that they came over HTTPS), and turn custom domains on.
native_caddy_env_text() {
    cat <<'EOF'

# Caddy, on this server, holds ports 80 and 443 and gets the HTTPS
# certificates, also for agents' custom domains.
TRUSTED_PROXIES=127.0.0.1
CHATVIA_ON_DEMAND_TLS=true
EOF
}

# The settings of this install. Secrets from an earlier attempt are kept:
# the database and Redis already use them.
native_write_env() {
    local file="$INSTALL_DIR/.env" app_key db_password redis_password reverb_id reverb_key reverb_secret mcp_secret
    local db_port secure=false proxy_env=''
    app_key=$(env_value APP_KEY)
    db_password=$(env_value DB_PASSWORD)
    redis_password=$(env_value REDIS_PASSWORD)
    reverb_id=$(env_value REVERB_APP_ID)
    reverb_key=$(env_value REVERB_APP_KEY)
    reverb_secret=$(env_value REVERB_APP_SECRET)
    mcp_secret=$(env_value BUILTIN_MCP_SECRET)
    app_key=${app_key:-base64:$(head -c 32 /dev/urandom | base64)}
    db_password=${db_password:-$(random_hex 24)}
    redis_password=${redis_password:-$(random_hex 24)}
    reverb_id=${reverb_id:-$(random_hex 8)}
    reverb_key=${reverb_key:-$(random_hex 16)}
    reverb_secret=${reverb_secret:-$(random_hex 32)}
    mcp_secret=${mcp_secret:-$(random_hex 32)}
    if [ "${HTTPS_SERVER:-}" = caddy ]; then
        proxy_env=$(native_caddy_env_text)
    fi
    if [ "$DATABASE" = existing ]; then
        db_port=$DB_PORT
    else
        db_port=$(pg_conftool -s "$NATIVE_PG" main show port 2>/dev/null) || db_port=5432
    fi
    case $APP_URL in https://*) secure=true ;; esac

    (
        umask 077
        cat >"$file.new" <<EOF
# Chatvia settings, written by the installer on $(date '+%Y-%m-%d').
# Only what Chatvia needs to start. Email, API keys, the AI services and the
# other options are set in the admin panel, under Settings. Every release
# reads this file. After a change, run as chatvia (sudo -iu chatvia):
#   cd $NATIVE_ROOT/current && php artisan optimize && php artisan reload
# More: $NATIVE_DOCS

APP_NAME=Chatvia
APP_ENV=production
# How this server upgrades (run the installer again), for the admin
# panel's update notice.
CHATVIA_INSTALL=complete
APP_DEBUG=false
# Encrypts the secrets in the database. Keep a copy somewhere safe, and
# never change it: without it, they cannot be read.
APP_KEY=$app_key
APP_URL=$APP_URL
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# How the database stores embeddings. Chosen at install: they can NEVER
# change afterwards, or Chatvia stops with an error.
EMBEDDING_VECTOR_TYPE=$EMBEDDING_TYPE
EMBEDDING_DIMENSIONS=$EMBEDDING_DIMENSIONS

# The search index: binary (smaller, faster) or full (the most exact).
# Unlike the two above, it can be changed later.
EMBEDDING_INDEX=$EMBEDDING_INDEX

# A log file per day in storage/logs, which the admin panel's Logs page
# reads. Only the newest 14 are kept.
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=pgsql
DB_HOST=$DB_HOST
DB_PORT=$db_port
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$db_password
DB_ANALYTICS_DATABASE=$DB_ANALYTICS
DB_PULSE_DATABASE=$DB_PULSE
DB_SPECTRA_DATABASE=$DB_SPECTRA

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=$redis_password
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
# Sends the session cookie only over HTTPS.
SESSION_SECURE_COOKIE=$secure

# "log" writes every e-mail to the log. Set up email in the admin panel
# (Settings -> Email) before other people use the app: sign-up, invitations
# and password resets are sent by e-mail.
MAIL_MAILER=log
MAIL_FROM_ADDRESS=hello@example.com
MAIL_FROM_NAME="\${APP_NAME}"

# Live updates. The app reaches Reverb on this server; browsers and the chat
# widget connect to APP_URL, and nginx passes /app/ to Reverb.
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=$reverb_id
REVERB_APP_KEY=$reverb_key
REVERB_APP_SECRET=$reverb_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY=
VITE_REVERB_HOST=
VITE_REVERB_PORT=
VITE_REVERB_SCHEME=

# Signs the calls the app makes to its own built-in tools.
BUILTIN_MCP_SECRET=$mcp_secret
$proxy_env
EOF
    )
    chown "$NATIVE_USER:$NATIVE_USER" "$file.new"
    mv "$file.new" "$file"
}

# The database user and the databases of the app, analytics, Pulse and
# Spectra. The password goes through stdin, never the command line.
native_setup_database() {
    {
        printf "\\set password '%s'\n" "$(env_value DB_PASSWORD)"
        cat <<'SQL'
SELECT 'CREATE ROLE chatvia LOGIN' WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'chatvia')\gexec
ALTER ROLE chatvia WITH LOGIN PASSWORD :'password';
SELECT 'CREATE DATABASE chatvia OWNER chatvia' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'chatvia')\gexec
SELECT 'CREATE DATABASE analytics OWNER chatvia' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'analytics')\gexec
SELECT 'CREATE DATABASE pulse OWNER chatvia' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'pulse')\gexec
SELECT 'CREATE DATABASE spectra OWNER chatvia' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'spectra')\gexec
SQL
    } | psql_admin
}

# Redis keeps the queues, the cache and the sessions: a password, and every
# change on disk, so queued jobs survive a restart.
native_configure_redis() {
    local conf=/etc/redis/redis.conf
    sed -i -e '/^# Chatvia:/d' -e '/^requirepass /d' -e '/^appendonly /d' "$conf"
    printf '# Chatvia: a password, and every change kept on disk.\nrequirepass %s\nappendonly yes\n' \
        "$(env_value REDIS_PASSWORD)" >>"$conf"
    systemctl enable redis-server
    systemctl restart redis-server
    wait_until 30 native_redis_ready
}

native_redis_ready() {
    [ "$(REDISCLI_AUTH=$(env_value REDIS_PASSWORD) redis-cli ping 2>/dev/null)" = PONG ]
}

# Deployer, for the chatvia user, in ~/deploy. deploy.php there is yours
# once written: an upgrade replaces it only while it is unchanged.
native_install_deployer() {
    install -d -o "$NATIVE_USER" -g "$NATIVE_USER" -m 755 "$NATIVE_DEPLOY_DIR"
    native_write_deploy_file
    if [ ! -x "$NATIVE_DEPLOY_DIR/vendor/bin/dep" ]; then
        as_chatvia "$NATIVE_DEPLOY_DIR" composer require --no-interaction --no-progress "deployer/deployer:$DEPLOYER_VERSION"
    fi
    native_dep --version
}

native_write_deploy_file() {
    native_deploy_file >"$NATIVE_DEPLOY_DIR/deploy.php.new"
    chown "$NATIVE_USER:$NATIVE_USER" "$NATIVE_DEPLOY_DIR/deploy.php.new"
    mv "$NATIVE_DEPLOY_DIR/deploy.php.new" "$NATIVE_DEPLOY_DIR/deploy.php"
    conf_set DEPLOY_FILE_SUM "$(cksum <"$NATIVE_DEPLOY_DIR/deploy.php")"
}

native_deploy_file() {
    sed -e "s|__REPOSITORY__|${CHATVIA_REPO//|/\\|}|" -e "s|__DEPLOY_PATH__|$NATIVE_ROOT|g" -e "s|__DOCS__|$NATIVE_DOCS|" <<'EOF'
#@include templates/deploy.php
EOF
}

# The first deploy. Its first migration installs the PostgreSQL extensions,
# which needs a superuser: the database user is one for this deploy only. On
# an existing server, the extensions are there already (the checks saw to
# it), and its users are not the installer's to change.
native_first_deploy() {
    local status=0
    native_unlock_stale
    if [ "$DATABASE" = existing ]; then
        native_dep deploy "$(native_target)"
        return
    fi
    psql_admin -c "ALTER ROLE $NATIVE_USER SUPERUSER"
    native_dep deploy "$(native_target)" || status=$?
    psql_admin -c "ALTER ROLE $NATIVE_USER NOSUPERUSER" || status=1
    return "$status"
}

# An upgrade deploys without the superuser right. A release whose migration
# adds a PostgreSQL extension needs it, like the first deploy: then it gets
# it for one more try.
native_upgrade_deploy() {
    local output
    native_unlock_stale
    native_dep deploy "$(native_target)" && return 0
    output=$(tail -n 300 "$LOG")
    [[ $output == *'permission denied to create extension'* ]] || return 1
    if [ "$DATABASE" = existing ]; then
        echo "A migration installs a PostgreSQL extension, which $DB_USER may not do. A superuser" \
            "creates it (CREATE EXTENSION, the name is above) in $DB_NAME; then run the installer again." >&2
        return 1
    fi
    echo 'A migration installs a PostgreSQL extension: deploying again, as a superuser this time.'
    native_first_deploy
}

# A deploy that was killed (Ctrl-C, or out of memory) leaves Deployer's lock
# behind, and the next one would stop at it. Removed while no deploy runs.
native_unlock_stale() {
    if [ -f "$NATIVE_ROOT/.dep/deploy.lock" ] && ! pgrep -u "$NATIVE_USER" -f 'vendor/bin/dep' >/dev/null; then
        echo 'Removing the lock of a deploy that did not finish.'
        native_dep deploy:unlock
    fi
}

# Supervisor keeps Horizon (the queues), Reverb (live updates) and Pulse's
# server stats running, and starts them again when they stop. They run the
# release that `current` points at; a deploy restarts them.
native_configure_supervisor() {
    sed -e "s|__CURRENT__|$NATIVE_ROOT/current|g" -e "s|__HOME__|$NATIVE_HOME|g" \
        >/etc/supervisor/conf.d/chatvia.conf <<'EOF'
#@include templates/supervisor.conf
EOF
    systemctl enable supervisor
    systemctl start supervisor
    supervisorctl reread
    supervisorctl update
    wait_until 60 native_programs_running
    supervisorctl status
}

native_programs_running() {
    local status
    status=$(supervisorctl status chatvia-horizon chatvia-reverb chatvia-pulse 2>/dev/null) || true
    [ "$(printf '%s\n' "$status" | grep -c ' RUNNING ')" = 3 ]
}

# The scheduler cleans up old data, analyses conversations, collects
# statistics and backs up the database. cron starts it every minute.
native_configure_cron() {
    printf '# Chatvia. Written by the Chatvia installer.\n* * * * * %s cd %s && php artisan schedule:run >> /dev/null 2>&1\n' \
        "$NATIVE_USER" "$NATIVE_ROOT/current" >/etc/cron.d/chatvia
    chmod 644 /etc/cron.d/chatvia
    systemctl enable cron
    systemctl start cron
}

# Automatic updates: chatvia-auto-update, and its nightly cron entry while
# they are on. The script is the installer's: every upgrade replaces it, and
# install.conf's AUTO_UPDATE says whether the cron entry stays.
native_configure_auto_update() {
    native_auto_update_file >"$NATIVE_AUTO_UPDATE.new"
    chmod 755 "$NATIVE_AUTO_UPDATE.new"
    # A new file, not a rewrite: the update that runs this keeps reading
    # the old one.
    mv "$NATIVE_AUTO_UPDATE.new" "$NATIVE_AUTO_UPDATE"
    if [ "$AUTO_UPDATE" = yes ]; then
        "$NATIVE_AUTO_UPDATE" on
    else
        "$NATIVE_AUTO_UPDATE" off
    fi
}

native_auto_update_step() {
    if [ "$AUTO_UPDATE" = yes ]; then
        printf 'Turning on automatic updates (once a night)'
    else
        printf 'Installing chatvia-auto-update (automatic updates stay off)'
    fi
}

native_auto_update_file() {
    sed -e "s|__CONF__|$NATIVE_CONF|" -e "s|__CRON__|$NATIVE_AUTO_UPDATE_CRON|" \
        -e "s|__LOG__|$NATIVE_AUTO_UPDATE_LOG|" -e "s|__INSTALL_LOG__|$NATIVE_LOG|" \
        -e "s|__INSTALLER_URL__|${CHATVIA_INSTALLER_URL:-}|" \
        -e "s|__SELF__|$NATIVE_AUTO_UPDATE|g" -e "s|__DOCS__|$NATIVE_DOCS|" <<'EOF'
#@include templates/chatvia-auto-update.sh
EOF
}

# The queue workers start sized to this server's memory and processors
# (Settings → Platform changes them later). Horizon reads the numbers when
# it starts, and Supervisor starts it in the next step.
native_tune_workers() {
    as_chatvia "$NATIVE_ROOT/current" php artisan chatvia:workers:tune --no-interaction
}

# The password goes through stdin, never the command line.
native_create_admin() {
    printf '%s' "$ADMIN_PASS" | as_chatvia "$NATIVE_ROOT/current" \
        php artisan chatvia:admin:create-user "$ADMIN_EMAIL" Admin --stdin --if-no-admin --if-missing --no-interaction
}

# Whether the step above made the account, or it was there already (made by
# an earlier attempt).
native_read_admin_result() {
    local output
    ADMIN_CREATED=1
    [ "$DRY_RUN" = 1 ] && return 0
    output=$(tail -n 5 "$LOG")
    if [[ $output == *'Nothing to do'* ]]; then ADMIN_CREATED=0; fi
}

native_configure_firewall() {
    local port
    for port in $(ssh_ports); do
        ufw allow "$port/tcp" comment 'SSH'
    done
    ufw allow 80/tcp comment 'HTTP'
    ufw allow 443/tcp comment 'HTTPS'
    ufw allow 443/udp comment 'HTTPS (HTTP/3)'
    ufw --force enable
    ufw status verbose
}

# fail2ban bans an address after repeated failed SSH sign-ins. Ubuntu logs
# SSH to the systemd journal, not auth.log, so the jail reads the journal.
native_configure_fail2ban() {
    install -d /etc/fail2ban/jail.d
    cat >/etc/fail2ban/jail.d/chatvia.conf <<'EOF'
# Written by the Chatvia installer.
[sshd]
enabled = true
backend = systemd
EOF
    chmod 644 /etc/fail2ban/jail.d/chatvia.conf
    systemctl enable fail2ban
    systemctl restart fail2ban
}

# native_curl ARGS...: curl to the address people open, through nginx on
# this server, as visitors would.
native_curl() {
    local host=${APP_URL#*://} port=80
    case $APP_URL in https://*) port=443 ;; esac
    curl -sS --resolve "$host:$port:127.0.0.1" "$@"
}

native_app_is_up() {
    native_curl -f -o /dev/null --max-time 5 "$APP_URL/up"
}

native_horizon_is_up() {
    as_chatvia "$NATIVE_ROOT/current" php artisan horizon:status
}

# Every part of Chatvia, checked once more at the end: the services, the
# queue workers, live updates through nginx, Pulse and the scheduler.
native_check_parts() {
    local service code
    local services=(redis-server "php$NATIVE_PHP-fpm" nginx supervisor cron)
    if [ "${HTTPS_SERVER:-}" = caddy ]; then services+=(caddy); fi
    # An existing PostgreSQL may run elsewhere, or under another name.
    if [ "$DATABASE" = local ]; then services=(postgresql "${services[@]}"); fi
    for service in "${services[@]}"; do
        if ! systemctl is-active --quiet "$service"; then
            echo "$service is not running." >&2
            return 1
        fi
    done
    if ! native_programs_running; then
        supervisorctl status >&2
        return 1
    fi
    wait_until 60 native_horizon_is_up || {
        echo 'Horizon does not run.' >&2
        return 1
    }
    # Reverb answers a WebSocket handshake with 101.
    code=$(native_curl -o /dev/null -w '%{http_code}' --max-time 3 \
        -H 'Connection: Upgrade' -H 'Upgrade: websocket' -H 'Sec-WebSocket-Version: 13' \
        -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' \
        "$APP_URL/app/$(env_value REVERB_APP_KEY)?protocol=7" 2>/dev/null) || true
    if [ "$code" != 101 ]; then
        echo "Reverb does not answer through nginx at /app/ (HTTP ${code:-no answer})." >&2
        return 1
    fi
    if [ ! -f /etc/cron.d/chatvia ]; then
        echo 'The scheduler is not in cron.' >&2
        return 1
    fi
    as_chatvia "$NATIVE_ROOT/current" php artisan schedule:list
    echo 'Every part runs.'
}

