#------------------------------------------------------------------------------
# Complete installation: everything directly on this server, without Docker
#
# Ubuntu 26.04 LTS on a fresh server. It does what the guides "Install on
# Ubuntu" and "Deploy with Deployer" describe: PostgreSQL 18 with the search
# extensions, Redis, PHP-FPM behind nginx, a Let's Encrypt certificate,
# Horizon, Reverb and Pulse under Supervisor, the scheduler in cron, and the
# firewall. One system user, chatvia, owns the code and runs every part of
# the app. Deployer, installed for that user, deploys each release into its
# own folder under /var/www/chatvia and switches over in one step, so an
# upgrade has no downtime, and a failed one changes nothing that runs.
#
# The answers are saved to /etc/chatvia/install.conf before anything else
# changes, and the secrets to /var/www/chatvia/shared/.env. Every step is
# safe to repeat: running the installer again finishes an install that
# stopped, and upgrades a finished one.
#------------------------------------------------------------------------------

# conf_value KEY: an answer saved in install.conf.
conf_value() {
    sed -n "s/^$1=//p" "$NATIVE_CONF" 2>/dev/null | tail -n 1
}

# conf_set KEY VALUE: changes the answer in install.conf, or adds it.
conf_set() {
    local tmp
    tmp=$(mktemp "$NATIVE_CONF.XXXXXX") || return
    file_with "$NATIVE_CONF" "$1" "$2" >"$tmp" && chmod 600 "$tmp" && mv "$tmp" "$NATIVE_CONF"
}

# native_set_env KEY VALUE: changes the line in the shared .env, which stays
# the chatvia user's.
native_set_env() {
    set_env_value "$1" "$2" && chown "$NATIVE_USER:$NATIVE_USER" "$INSTALL_DIR/.env"
}

package_installed() {
    [ "$(dpkg-query -W -f='${Status}' "$1" 2>/dev/null)" = 'install ok installed' ]
}

# apt-get without questions, keeping changed config files. It waits for the
# package lock: on a new server, the automatic updates may hold it for a few
# minutes.
apt_get() {
    DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a apt-get -y -q \
        -o DPkg::Lock::Timeout=600 \
        -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold "$@"
}

# psql as the postgres superuser, on Chatvia's cluster. From /, which the
# postgres user can read.
psql_admin() {
    (cd / && runuser -u postgres -- psql --cluster "$NATIVE_PG/main" -X -q -v ON_ERROR_STOP=1 "$@")
}

# as_chatvia DIR COMMAND...: runs it as the chatvia user, in DIR.
as_chatvia() {
    local dir=$1
    shift
    (cd "$dir" && runuser -u "$NATIVE_USER" -- env HOME="$NATIVE_HOME" USER="$NATIVE_USER" LOGNAME="$NATIVE_USER" "$@")
}

# native_dep ARGS...: Deployer, as chatvia, in its folder. -v puts every
# command it runs in the log.
native_dep() {
    as_chatvia "$NATIVE_DEPLOY_DIR" vendor/bin/dep --no-interaction -v "$@"
}

random_hex() {
    od -An -tx1 -N"$1" /dev/urandom | tr -d ' \n'
}

is_ip() {
    [[ $1 =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]]
}

valid_host() {
    if is_ip "$1" || [[ $1 =~ ^([A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$ ]]; then
        return 0
    fi
    INVALID='Type a domain name like chat.example.com, or an IP address: no https:// and no path.'
    return 1
}

# This server's public IPv4 address, for the DNS hint. Asked from outside,
# as a server behind NAT does not know it; else its own first address.
server_ip() {
    local ip
    ip=$(curl -4 -fsS --max-time 5 https://api.ipify.org 2>/dev/null) || ip=''
    if ! is_ip "$ip"; then
        ip=$(hostname -I 2>/dev/null | awk '{ print $1 }') || ip=''
    fi
    printf '%s' "$ip"
}

# The addresses of this server: the public one and its own.
server_addresses() {
    {
        if [ -n "$SERVER_IP" ]; then printf '%s\n' "$SERVER_IP"; fi
        hostname -I 2>/dev/null | tr ' ' '\n'
    } | sed '/^$/d' | sort -u
    return 0
}

# The IPv4 addresses NAME points at in DNS (its A records), one per line.
dns_addresses() {
    getent ahostsv4 "$1" 2>/dev/null | awk '{ print $1 }' | sort -u
    return 0
}

# Forget cached DNS answers, so a record added a moment ago shows up.
flush_dns() {
    resolvectl flush-caches >/dev/null 2>&1 || true
}

meminfo_kb() {
    local kb
    kb=$(awk -v key="$1:" '$1 == key { print $2 }' /proc/meminfo 2>/dev/null) || kb=''
    printf '%s' "${kb:-0}"
}

# Little memory and no swap: the frontend build may run out of memory.
needs_swap() {
    local memory
    memory=$(meminfo_kb MemTotal)
    [ "$memory" -gt 0 ] && [ "$memory" -lt "$SWAP_BELOW_KB" ] && [ "$(meminfo_kb SwapTotal)" -eq 0 ]
}

# The SSH ports, so the firewall never locks you out: the ones sshd uses,
# and the one of this very connection.
ssh_ports() {
    {
        sshd -T 2>/dev/null | awk '$1 == "port" { print $2 }' ||
            cat /etc/ssh/sshd_config /etc/ssh/sshd_config.d/*.conf 2>/dev/null | awk 'tolower($1) == "port" { print $2 }'
        if [ -n "${SSH_CONNECTION:-}" ]; then printf '%s\n' "${SSH_CONNECTION##* }"; fi
        printf '22\n'
    } | grep -E '^[0-9]+$' | sort -un
    return 0
}

# release_tags: the releases (vX.Y.Z tags) of the repository, oldest first.
# With git, or on a server without git yet, with curl through Git's HTTP
# protocol.
release_tags() {
    local refs
    if command -v git >/dev/null 2>&1; then
        refs=$(GIT_TERMINAL_PROMPT=0 git ls-remote --tags --refs "$CHATVIA_REPO" 2>/dev/null) || return 1
    else
        refs=$(curl -fsS --max-time 30 "${CHATVIA_REPO%.git}.git/info/refs?service=git-upload-pack" 2>/dev/null) || return 1
    fi
    printf '%s\n' "$refs" | grep -oE 'refs/tags/v[0-9]+\.[0-9]+\.[0-9]+$' | sed 's#^refs/tags/##' | sort -V || true
}

# Turns CHATVIA_VERSION (latest, 1, 1.4, 1.4.2 or a branch) into the release
# tag to deploy (CHATVIA_RELEASE), or the branch (CHATVIA_BRANCH).
native_resolve_version() {
    local spec=${CHATVIA_VERSION#v} tags tag=''
    CHATVIA_RELEASE='' CHATVIA_BRANCH=''
    if [ "$spec" != latest ] && ! [[ $spec =~ ^[0-9]+(\.[0-9]+){0,2}$ ]]; then
        CHATVIA_BRANCH=$CHATVIA_VERSION
        if command -v git >/dev/null 2>&1 &&
            [ -z "$(GIT_TERMINAL_PROMPT=0 git ls-remote --heads "$CHATVIA_REPO" "$CHATVIA_BRANCH" 2>/dev/null)" ]; then
            die "$CHATVIA_REPO has no release or branch called $CHATVIA_VERSION." \
                'Use latest, a version number like 1.2.0, or a branch like main.'
        fi
        warn "$(bold "$CHATVIA_BRANCH") is a branch, not a release. Use it only to test."
        return
    fi

    tags=$(release_tags) || die "Could not read the releases of $CHATVIA_REPO." 'Check the internet connection.'
    if [ "$spec" = latest ]; then
        tag=$(printf '%s\n' "$tags" | tail -n 1)
    else
        tag=$(printf '%s\n' "$tags" | grep -E "^v${spec//./\\.}(\\.|\$)" | tail -n 1) || tag=''
    fi
    if [ -z "$tag" ]; then
        if [ -z "$tags" ]; then
            die "$CHATVIA_REPO has no release yet (no vX.Y.Z tag)." \
                'To install code that is not released yet, name a branch: --version main'
        fi
        die "There is no release $CHATVIA_VERSION." "The newest is $(printf '%s\n' "$tags" | tail -n 1)."
    fi
    CHATVIA_RELEASE=$tag
}

native_version_text() {
    if [ -n "$CHATVIA_RELEASE" ]; then
        printf '%s' "$CHATVIA_RELEASE"
    else
        printf 'branch %s' "$CHATVIA_BRANCH"
    fi
}

# Deployer's option for what to deploy.
native_target() {
    if [ -n "$CHATVIA_RELEASE" ]; then
        printf -- '--tag=%s' "$CHATVIA_RELEASE"
    else
        printf -- '--branch=%s' "$CHATVIA_BRANCH"
    fi
}

native_check_system() {
    local id='' version='' name='' codename='' arch
    if [ -r /etc/os-release ]; then
        # shellcheck source=/dev/null
        id=$(. /etc/os-release && printf '%s' "${ID:-}")
        # shellcheck source=/dev/null
        version=$(. /etc/os-release && printf '%s' "${VERSION_ID:-}")
        # shellcheck source=/dev/null
        name=$(. /etc/os-release && printf '%s' "${PRETTY_NAME:-}")
        # shellcheck source=/dev/null
        codename=$(. /etc/os-release && printf '%s' "${VERSION_CODENAME:-}")
    fi
    name=${name:-$(uname -s)}
    if [ "$OS" != linux ] || [ "$id" != ubuntu ] || [ "$version" != "$NATIVE_UBUNTU" ]; then
        if [ "$DRY_RUN" = 1 ]; then
            warn "This is $name, not Ubuntu $NATIVE_UBUNTU: a real install stops here. $(dim '(dry run)')"
            return
        fi
        die "The complete installation needs Ubuntu $NATIVE_UBUNTU LTS. This is $name." \
            "Use a fresh Ubuntu $NATIVE_UBUNTU server, or install with Docker (--docker)."
    fi
    arch=$(dpkg --print-architecture)
    case $arch in
        amd64 | arm64) ;;
        *) die "The complete installation needs an amd64 or arm64 server, not $arch." ;;
    esac
    if [ ! -d /run/systemd/system ]; then
        if [ "$DRY_RUN" = 1 ]; then
            warn "systemd does not run here: a real install stops here. $(dim '(dry run)')"
            return
        fi
        die 'systemd does not run here (is this a container?).' \
            'The complete installation needs a whole server or VPS. Install with Docker instead (--docker).'
    fi
    if ! php_ppa_carries "$codename"; then
        if [ "$DRY_RUN" = 1 ]; then
            warn "ppa:ondrej/php has no packages for Ubuntu $NATIVE_UBUNTU yet: a real install stops here. $(dim '(dry run)')"
            return
        fi
        die "ChatVia.ai needs PHP $NATIVE_MIN_PHP or newer, from ppa:ondrej/php. It has no packages for Ubuntu $NATIVE_UBUNTU ($codename) yet, or this server cannot reach it." \
            "Install with Docker (--docker) for now, or run this installer again once the PPA carries Ubuntu $NATIVE_UBUNTU."
    fi
    ok "$name $(dim "($arch)")"
}

php_ppa_carries() {
    [ -n "$1" ] && curl -fsI --max-time 10 "$PHP_PPA_URL/dists/$1/Release" >/dev/null 2>&1
}

# The complete installation changes the whole server, so it runs as root.
# Started by another user, it runs itself again with sudo: from its own
# file, or when it came through a pipe (curl | bash), from a new download.
native_become_root() {
    [ "$(id -u)" = 0 ] && return 0
    if [ "$DRY_RUN" = 1 ]; then
        info "$(dim 'A real install runs as root: it starts itself again with sudo. (dry run)')"
        return 0
    fi
    command -v sudo >/dev/null 2>&1 || die 'The complete installation needs root.' 'Run it as root.'

    local script=${BASH_SOURCE[0]:-} name status=0
    local env=(CHATVIA_RESTARTED=1)
    if [ -z "$script" ] || [ ! -f "$script" ]; then
        script="$WORK_DIR/install.sh"
        curl -fsSL "${CHATVIA_INSTALLER_URL:-$INSTALLER_URL}" -o "$script" ||
            die 'Could not download the installer to run it as root.' 'Run it as root instead.'
    fi
    # The settings that came as environment variables, and how to draw.
    for name in $(compgen -e); do
        case $name in
            CHATVIA_RESTARTED) ;;
            CHATVIA_* | SHAKOMAKO_* | NO_COLOR | TERM | LANG | LC_* | SSH_CONNECTION) env+=("$name=${!name}") ;;
        esac
    done

    info 'The complete installation runs as root, so it starts again with sudo.'
    keys_off
    # shellcheck disable=SC2024 # sudo asks for the password on the terminal.
    sudo -v </dev/tty || die 'sudo did not work.' 'Run the installer as root instead.'
    # The new run says "Stopped." itself.
    trap 'keys_off; exit 130' INT TERM
    sudo env "${env[@]}" bash "$script" --complete ${ARGS[@]+"${ARGS[@]}"} || status=$?
    exit "$status"
}

native_main() {
    if [ "$DOCKER_OPTIONS" = 1 ]; then
        die '--dir, --port, --url, --image, --no-pull and --no-start are for the Docker install.' \
            'Leave them out for the complete installation.'
    fi
    native_check_system
    native_become_root

    # The shared .env is the one the env_value and set_env_value helpers use.
    INSTALL_DIR="$NATIVE_ROOT/shared"
    if [ "$DRY_RUN" = 0 ]; then
        LOG=$NATIVE_LOG
        (umask 077 && printf '\n== Chatvia installer, %s\n' "$(date)" >>"$LOG")
    fi

    if [ -f "$NATIVE_CONF" ]; then
        if grep -q '^# Install finished on' "$NATIVE_CONF"; then native_upgrade; else native_resume; fi
        return
    fi
    native_install
}

native_install() {
    native_detect_database
    native_warning
    native_check_fresh
    native_collect_settings
    native_show_summary
    if [ "$INTERACTIVE" = 1 ] && ! confirm 'Install ChatVia.ai on this server with these settings?' y; then
        outro 'Nothing was changed.'
        exit 0
    fi
    run_step "Saving your answers to $NATIVE_CONF" native_save_settings || native_failed
    native_run_steps
}

native_warning() {
    local question='Is this a fresh server that ChatVia.ai can have to itself?'
    warn "$(bold 'The complete installation sets up this whole server for ChatVia.ai.')"
    if [ "$DATABASE" = existing ]; then
        info 'It installs Redis, PHP, nginx, Node.js, Supervisor (for Horizon, Reverb'
        info 'and Pulse), cron (for the scheduler), Certbot and Deployer, changes their'
        info 'settings for ChatVia.ai, takes over ports 80 and 443, and turns on the'
        info 'firewall. Other sites and programs on this server may stop working, and'
        info 'none of it is easy to undo. It uses your PostgreSQL server as it is.'
        info "$(bold 'We strongly recommend a server with nothing else on it but PostgreSQL.')"
        question='Can ChatVia.ai have this server to itself, apart from PostgreSQL?'
    else
        info 'It installs PostgreSQL, Redis, PHP, nginx, Node.js, Supervisor (for'
        info 'Horizon, Reverb and Pulse), cron (for the scheduler), Certbot and'
        info 'Deployer, changes their settings for ChatVia.ai, takes over ports 80 and'
        info '443, and turns on the firewall. Other sites, databases and programs on'
        info 'this server may stop working, and none of it is easy to undo.'
        info "$(bold 'We strongly recommend a fresh server or VPS with nothing else on it.')"
    fi
    if [ "$INTERACTIVE" = 1 ] && ! confirm "$question" n; then
        outro 'Nothing was changed. On a server that runs other things, install with Docker.'
        exit 0
    fi
}

# What is on this server already. A fresh server has none of it.
native_check_fresh() {
    local found=() pkg site item
    if command -v dpkg-query >/dev/null 2>&1; then
        for pkg in apache2 nginx caddy mysql-server mariadb-server redis-server docker-ce docker.io; do
            if package_installed "$pkg"; then found+=("$pkg is installed"); fi
        done
    fi
    # An existing PostgreSQL is kept as it is, so it does not count.
    if [ "$DATABASE" = local ] && [ -n "$(ls -A /etc/postgresql 2>/dev/null)" ]; then
        found+=('PostgreSQL is installed')
    fi
    for site in /etc/nginx/sites-enabled/*; do
        [ -e "$site" ] || continue
        case ${site##*/} in default | chatvia) continue ;; esac
        found+=("nginx serves another site: ${site##*/}")
    done
    for site in /var/www/*; do
        [ -e "$site" ] || continue
        case ${site##*/} in html | chatvia) continue ;; esac
        found+=('/var/www holds other sites')
        break
    done
    if command -v ss >/dev/null 2>&1 && [ -n "$(ss -Hltn '( sport = :80 or sport = :443 )' 2>/dev/null)" ]; then
        found+=('a program listens on port 80 or 443')
    fi

    if [ "${#found[@]}" -eq 0 ]; then
        ok 'This server looks fresh'
        return
    fi
    warn "$(bold 'This server does not look fresh:')"
    for item in "${found[@]}"; do info "- $item"; done
    info 'The installer changes their settings for ChatVia.ai.'
    if [ "$FORCE" = 1 ]; then return; fi
    if [ "$INTERACTIVE" = 0 ]; then
        die 'Use a fresh server, or pass --force to install anyway.'
    fi
    if ! confirm 'Install on this server anyway?' n; then
        outro 'Nothing was changed.'
        exit 0
    fi
}

native_collect_settings() {
    SERVER_IP=$(server_ip)
    if [ "$DATABASE" = existing ]; then native_database_settings; fi
    if [ "$INTERACTIVE" = 0 ]; then
        ADMIN_EMAIL=${CHATVIA_ADMIN_EMAIL:-}
        [ -n "$ADMIN_EMAIL" ] || die 'Set the admin e-mail: --email you@example.com (or CHATVIA_ADMIN_EMAIL).'
        valid_email "$ADMIN_EMAIL" || die "$INVALID"
        ADMIN_PASS=${CHATVIA_ADMIN_PASSWORD:-}
        if [ -z "$ADMIN_PASS" ]; then
            ADMIN_PASS=$(random_password)
            PASSWORD_GENERATED=1
        fi
        DOMAIN=$(printf '%s' "${CHATVIA_DOMAIN:-$SERVER_IP}" | tr '[:upper:]' '[:lower:]')
        [ -n "$DOMAIN" ] || die 'Set the address: --domain chat.example.com (or CHATVIA_DOMAIN).'
        valid_host "$DOMAIN" || die "$INVALID"
        case ${CHATVIA_HTTPS:-yes} in no | false | 0) HTTPS=no ;; *) HTTPS=yes ;; esac
        if is_ip "$DOMAIN"; then HTTPS=no; fi
        if ! native_check_dns && [ "$HTTPS" = yes ]; then
            info 'Without it, the HTTPS certificate fails, and ChatVia.ai runs on HTTP until you'
            info 'get one (run this installer again then).'
        fi
        case ${CHATVIA_FIREWALL:-yes} in no | false | 0) FIREWALL=no ;; *) FIREWALL=yes ;; esac
        case ${CHATVIA_AUTO_UPDATE:-yes} in no | false | 0) AUTO_UPDATE=no ;; *) AUTO_UPDATE=yes ;; esac
        case ${CHATVIA_SWAP:-auto} in
            yes | true | 1) SWAP=yes ;;
            no | false | 0) SWAP=no ;;
            *) if needs_swap; then SWAP=yes; else SWAP=no; fi ;;
        esac
        EMBEDDING_TYPE=${CHATVIA_EMBEDDING_TYPE:-halfvec}
        EMBEDDING_DIMENSIONS=${CHATVIA_EMBEDDING_DIMENSIONS:-1024}
        EMBEDDING_INDEX=${CHATVIA_EMBEDDING_INDEX:-binary}
        case $EMBEDDING_TYPE in halfvec | vector) ;; *) die 'CHATVIA_EMBEDDING_TYPE must be halfvec or vector.' ;; esac
        [[ $EMBEDDING_DIMENSIONS =~ ^[0-9]+$ ]] || die 'CHATVIA_EMBEDDING_DIMENSIONS must be a number.'
        case $EMBEDDING_INDEX in binary | full) ;; *) die 'CHATVIA_EMBEDDING_INDEX must be binary or full.' ;; esac
    else
        native_domain_notice
        ask_admin
        native_ask_domain
        HTTPS=no
        if ! is_ip "$DOMAIN"; then
            choose 'HTTPS' \
                "Get a free certificate from Let's Encrypt  — renews by itself (recommended)" \
                'No HTTPS for now  — you can get a certificate later'
            if [ "$CHOICE" = 0 ]; then HTTPS=yes; fi
            if [ "$HTTPS" = yes ] && [ "$DNS_READY" = 0 ]; then
                info 'The certificate needs the A record. If it does not work yet when the'
                info 'installer gets there, ChatVia.ai runs on HTTP until you run the installer again.'
            fi
        fi
        FIREWALL=no
        if confirm "Turn on the firewall? Only SSH, HTTP and HTTPS can come in." y; then FIREWALL=yes; fi
        SWAP=no
        if needs_swap; then
            info "This server has $(awk -v kb="$(meminfo_kb MemTotal)" 'BEGIN { printf "%.1f GB", kb / 1048576 }') of memory and no swap."
            if confirm 'Add 4 GB of swap, so building ChatVia.ai does not run out of memory?' y; then SWAP=yes; fi
        fi
        ask_setup
    fi

    if is_ip "$DOMAIN"; then HTTPS=no; fi
    if [ "$HTTPS" = yes ]; then APP_URL="https://$DOMAIN"; else APP_URL="http://$DOMAIN"; fi
    native_resolve_version
    # Automatic updates follow releases: a branch has none.
    if [ -n "$CHATVIA_BRANCH" ]; then
        AUTO_UPDATE=no
    elif [ "$INTERACTIVE" = 1 ]; then
        native_ask_auto_update
    fi
}

native_ask_auto_update() {
    block text
    info 'Automatic updates: once a night, the server looks for a newer release of the'
    info 'major version it runs (1.4 goes to 1.5, never to 2.0). When there is one, it'
    info 'backs up the database and deploys it. A deploy that fails changes nothing.'
    AUTO_UPDATE=no
    if confirm 'Update ChatVia.ai automatically?' y; then AUTO_UPDATE=yes; fi
}

# Shown before the questions: a new DNS record can take a while to work, so
# it can be added now, while the other questions are answered.
native_domain_notice() {
    section 'Before you start: point your domain at this server'
    info 'At your DNS provider, add an A record for the domain people will open'
    if [ -n "$SERVER_IP" ]; then
        info "(like chat.example.com) with the value $(bold "$SERVER_IP"), the IP address of this server."
    else
        info '(like chat.example.com) with the public IP address of this server as its value.'
    fi
    info 'The installer checks it in a moment. No domain yet? Use the IP address for now.'
}

# Asks for the domain and checks its A record. When it does not point here,
# the person can check again, use another address, go on anyway (a proxy or
# CDN in front, or a record that needs more time) or stop.
native_ask_domain() {
    DNS_READY=1
    while true; do
        ask DOMAIN 'Domain name or IP address' --default "${DOMAIN:-${CHATVIA_DOMAIN:-}}" --placeholder 'chat.example.com' \
            --hint "Where people open ChatVia.ai. Its A record must point at this server${SERVER_IP:+ ($SERVER_IP)}." \
            --check valid_host
        DOMAIN=$(printf '%s' "$DOMAIN" | tr '[:upper:]' '[:lower:]')
        while ! native_check_dns; do
            choose "$DOMAIN does not point at this server yet" \
                'Check again  — after you add or change the A record' \
                'Continue anyway  — a proxy or CDN is in front, or the record needs more time' \
                'Use another domain or the IP address' \
                'Stop for now  — run the installer again once the record works'
            case $CHOICE in
                0) flush_dns ;;
                1)
                    DNS_READY=0
                    return
                    ;;
                2) continue 2 ;;
                *)
                    outro 'Nothing was changed.'
                    exit 0
                    ;;
            esac
        done
        return
    done
}

# Let's Encrypt checks that the domain points at this server. Behind a proxy
# or CDN it may point elsewhere and still work, so this only warns. Returns 1
# when it does not point here.
native_check_dns() {
    if is_ip "$DOMAIN"; then return 0; fi
    local addresses
    addresses=$(dns_addresses "$DOMAIN")
    if [ -n "$addresses" ] && grep -qFxf <(server_addresses) <<<"$addresses"; then
        ok "$DOMAIN points at this server"
        return 0
    fi
    if [ -z "$addresses" ]; then
        warn "$DOMAIN has no A record yet."
    else
        warn "$DOMAIN points at $(printf '%s' "$addresses" | paste -sd ' ' - | sed 's/ /, /g'), not at this server${SERVER_IP:+ ($SERVER_IP)}."
    fi
    info "Add an A record for it that points at ${SERVER_IP:-this server}, unless a proxy or CDN is in front."
    return 1
}

native_show_summary() {
    local password='the one you typed' address version storage index firewall swap code email kind updates database
    local rows=()
    if [ "$PASSWORD_GENERATED" = 1 ]; then password='a new random one, shown at the end'; fi
    kv_to kind 'Install' 'Complete, directly on this server'
    if [ "$HTTPS" = yes ]; then
        kv_to address 'Address' "$APP_URL  $(dim '(HTTPS certificate from Let'\''s Encrypt)')"
    else
        kv_to address 'Address' "$APP_URL"
    fi
    kv_to email 'Admin e-mail' "$ADMIN_EMAIL"
    kv_to password 'Admin password' "$password"
    kv_to version 'Version' "$(native_version_text)"
    kv_to storage 'Search storage' "$EMBEDDING_TYPE, $EMBEDDING_DIMENSIONS dimensions $(dim '(permanent)')"
    kv_to index 'Search index' "$EMBEDDING_INDEX $(dim '(can be changed later)')"
    kv_to code 'Code' "$NATIVE_ROOT, run by the user $NATIVE_USER"
    if [ "$DATABASE" = existing ]; then
        kv_to database 'Database' "your PostgreSQL at $DB_HOST:$DB_PORT, user $DB_USER $(dim "($DB_NAME, $DB_ANALYTICS, $DB_PULSE, $DB_SPECTRA)")"
    else
        kv_to database 'Database' "PostgreSQL $NATIVE_PG, installed on this server"
    fi
    rows=("$kind" "$address" "$email" "$password" "$version" "$storage" "$index" "$code" "$database")
    if [ "$FIREWALL" = yes ]; then
        kv_to firewall 'Firewall' "on: SSH (port $(ssh_ports | paste -sd, - | sed 's/,/, /g')), HTTP and HTTPS"
    else
        kv_to firewall 'Firewall' 'left as it is'
    fi
    rows+=("$firewall")
    if [ "$AUTO_UPDATE" = yes ]; then
        kv_to updates 'Updates' "automatic, once a night $(dim '(new releases of the same major version)')"
    else
        kv_to updates 'Updates' "by hand $(dim '(run this installer again)')"
    fi
    rows+=("$updates")
    if [ "$SWAP" = yes ]; then
        kv_to swap 'Swap' '4 GB, in /swapfile'
        rows+=("$swap")
    fi
    note 'Check your settings' "${rows[@]}"
    if [ "$HTTPS" = yes ]; then
        local notice="Let's Encrypt sends expiry notices to $ADMIN_EMAIL. Getting the certificate"
        info "$(dim "$notice")"
        info "$(dim 'means you accept its Subscriber Agreement: https://letsencrypt.org/repository/')"
    fi
}

native_save_settings() {
    install -d -m 755 "$(dirname "$NATIVE_CONF")"
    (
        umask 077
        cat >"$NATIVE_CONF" <<EOF
# The answers of the Chatvia complete installation, saved by the installer
# on $(date '+%Y-%m-%d'). It reads them when it runs again: to finish an install
# that stopped, or to upgrade. The secrets are in $NATIVE_ROOT/shared/.env.
CHATVIA_DOMAIN=$DOMAIN
APP_URL=$APP_URL
HTTPS=$HTTPS
ADMIN_EMAIL=$ADMIN_EMAIL
EMBEDDING_VECTOR_TYPE=$EMBEDDING_TYPE
EMBEDDING_DIMENSIONS=$EMBEDDING_DIMENSIONS
EMBEDDING_INDEX=$EMBEDDING_INDEX
CHATVIA_REPO=$CHATVIA_REPO
CHATVIA_RELEASE=$CHATVIA_RELEASE
CHATVIA_BRANCH=$CHATVIA_BRANCH
FIREWALL=$FIREWALL
SWAP=$SWAP
AUTO_UPDATE=$AUTO_UPDATE
DATABASE=$DATABASE
DB_HOST=$DB_HOST
DB_PORT=$DB_PORT
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_ANALYTICS_DATABASE=$DB_ANALYTICS
DB_PULSE_DATABASE=$DB_PULSE
DB_SPECTRA_DATABASE=$DB_SPECTRA
DB_SERVER_MAJOR=$DB_MAJOR
EOF
    )
    # The existing user's password is a secret, so it waits in the shared
    # .env, where the step that writes the rest of it keeps it.
    if [ "$DATABASE" = existing ]; then
        install -d -m 755 "$NATIVE_ROOT" "$NATIVE_ROOT/shared"
        (umask 077 && printf "DB_PASSWORD='%s'\n" "$DB_PASS" >"$NATIVE_ROOT/shared/.env")
    fi
}

native_load_settings() {
    DOMAIN=$(conf_value CHATVIA_DOMAIN)
    APP_URL=$(conf_value APP_URL)
    HTTPS=$(conf_value HTTPS)
    ADMIN_EMAIL=$(conf_value ADMIN_EMAIL)
    EMBEDDING_TYPE=$(conf_value EMBEDDING_VECTOR_TYPE)
    EMBEDDING_DIMENSIONS=$(conf_value EMBEDDING_DIMENSIONS)
    EMBEDDING_INDEX=$(conf_value EMBEDDING_INDEX)
    EMBEDDING_INDEX=${EMBEDDING_INDEX:-binary}
    CHATVIA_REPO=$(conf_value CHATVIA_REPO)
    CHATVIA_RELEASE=$(conf_value CHATVIA_RELEASE)
    CHATVIA_BRANCH=$(conf_value CHATVIA_BRANCH)
    FIREWALL=$(conf_value FIREWALL)
    SWAP=$(conf_value SWAP)
    AUTO_UPDATE=$(conf_value AUTO_UPDATE)
    AUTO_UPDATE=${AUTO_UPDATE:-no}
    CHATVIA_REPO=${CHATVIA_REPO:-$NATIVE_REPO}
    # Installs from before the existing-PostgreSQL choice made their own.
    DATABASE=$(conf_value DATABASE)
    DATABASE=${DATABASE:-local}
    if [ "$DATABASE" = existing ]; then
        DB_HOST=$(conf_value DB_HOST)
        DB_PORT=$(conf_value DB_PORT)
        DB_NAME=$(conf_value DB_DATABASE)
        DB_USER=$(conf_value DB_USERNAME)
        DB_ANALYTICS=$(conf_value DB_ANALYTICS_DATABASE)
        DB_PULSE=$(conf_value DB_PULSE_DATABASE)
        DB_SPECTRA=$(conf_value DB_SPECTRA_DATABASE)
        DB_MAJOR=$(conf_value DB_SERVER_MAJOR)
        DB_PASS=$(native_db_password)
    fi
}

native_failed() {
    die 'The installation stopped.' "See why: $LOG" \
        'Fix the cause, then run the installer again: it goes on where it stopped.'
}

# Every step of the install, in order. Each one is safe to repeat.
native_run_steps() {
    if [ "$SWAP" = yes ]; then
        run_step 'Adding 4 GB of swap' native_add_swap || native_failed
    fi
    run_step 'Updating the system packages' native_update_system || native_failed
    run_step 'Looking for a newer Redis at packages.redis.io' native_add_redis_repo || native_failed
    run_step 'Adding ppa:ondrej/php for the newest PHP' native_add_php_repo || native_failed
    if [ "$DATABASE" = existing ]; then
        run_step 'Installing PHP, Redis, nginx and the other packages' native_install_packages || native_failed
        run_step 'Installing Node.js 22' native_install_node || native_failed
    else
        run_step 'Installing PHP, PostgreSQL, Redis, nginx and the other packages' native_install_packages || native_failed
        run_step 'Installing Node.js 22' native_install_node || native_failed
        run_step 'Installing the search extensions for PostgreSQL' native_install_extensions || native_failed
        if [ "$DRY_RUN" = 1 ]; then
            section 'PostgreSQL settings' "/etc/postgresql/$NATIVE_PG/main/conf.d/chatvia.conf"
            native_postgres_conf | quote
        fi
        run_step 'Setting up PostgreSQL' native_configure_postgres || native_failed
    fi
    run_step "Creating the $NATIVE_USER user" native_create_user || native_failed
    run_step 'Setting up PHP-FPM' native_configure_php || native_failed
    run_step 'Setting up nginx' native_configure_nginx || native_failed
    if [ "$HTTPS" != no ]; then native_https; fi
    run_step "Saving the settings to $NATIVE_ROOT/shared/.env" native_write_env || native_failed
    if [ "$DATABASE" = existing ]; then
        run_step "Preparing the databases on $DB_HOST" native_prepare_database || native_failed
    else
        run_step 'Creating the database' native_setup_database || native_failed
    fi
    run_step 'Setting up Redis' native_configure_redis || native_failed
    run_step 'Installing Deployer' native_install_deployer || native_failed
    run_step "Deploying ChatVia.ai $(native_version_text) (5 to 15 minutes)" native_first_deploy || native_failed
    run_step 'Sizing the queue workers to this server' native_tune_workers || native_failed
    run_step 'Starting Horizon, Reverb and Pulse' native_configure_supervisor || native_failed
    run_step 'Starting the scheduler' native_configure_cron || native_failed
    run_step "$(native_auto_update_step)" native_configure_auto_update || native_failed
    run_step 'Creating the admin account' native_create_admin || native_failed
    native_read_admin_result
    if [ "$FIREWALL" = yes ]; then
        run_step 'Turning on the firewall' native_configure_firewall || native_failed
    fi
    run_step 'Banning addresses that keep failing SSH (fail2ban)' native_configure_fail2ban || native_failed
    if ! run_step 'Waiting for the web app' wait_until 120 native_app_is_up; then
        info "The admin account is ready: $(bold "$ADMIN_EMAIL"), password $(admin_password_text)"
        die 'The web app did not answer within 2 minutes.' \
            "See why: sudo tail -n 50 $NATIVE_ROOT/shared/storage/logs/laravel-$(date +%F).log" \
            'Then run this installer again to finish.'
    fi
    run_step 'Checking that every part runs' native_check_parts || native_failed
    if [ "$DRY_RUN" = 0 ]; then
        printf '\n# Install finished on %s.\n' "$(date '+%Y-%m-%d')" >>"$NATIVE_CONF"
    fi
    native_show_finish
}

