#------------------------------------------------------------------------------
# Which install
#------------------------------------------------------------------------------

# MODE: docker, or complete (everything directly on this server). An option
# that only means something for Docker (--dir, --port, ...) picks Docker, so
# ./manage upgrade, which passes --dir, never asks. A server with a complete
# installation goes on with it: to upgrade it, or to finish it.
choose_mode() {
    if [ -n "$MODE" ]; then return; fi
    if [ "$OS" = macos ] || [ "$DOCKER_OPTIONS" = 1 ]; then
        MODE=docker
    elif [ -f "$NATIVE_CONF" ] || [ -n "${CHATVIA_DB_HOST:-}" ]; then
        MODE=complete
    elif [ "$INTERACTIVE" = 0 ]; then
        MODE=docker
    else
        choose 'How do you want to install ChatVia.ai?' \
            'With Docker  — everything in containers, easy to move and remove' \
            "Complete installation  — everything directly on this server (Ubuntu $NATIVE_UBUNTU)"
        if [ "$CHOICE" = 0 ]; then MODE=docker; else MODE=complete; fi
    fi
}

#------------------------------------------------------------------------------
# Main
#------------------------------------------------------------------------------

usage() {
    awk 'NR >= 3 && /^# Everything runs/ { exit } NR >= 3 { sub(/^# ?/, ""); print }' "$0" 2>/dev/null || true
    if [ ! -f "$0" ]; then
        say 'Usage: curl -fsSL https://chatvia.ai/install.sh | bash -s -- [--yes] [--dir PATH] [--email EMAIL] ...'
    fi
}

parse_args() {
    while [ $# -gt 0 ]; do
        case $1 in
            --docker) MODE=docker ;;
            --complete) MODE=complete ;;
            --dir) CHATVIA_DIR=${2:?--dir needs a folder}; shift ;;
            --email) CHATVIA_ADMIN_EMAIL=${2:?--email needs an address}; shift ;;
            --port) CHATVIA_PORT=${2:?--port needs a number}; shift ;;
            --url) CHATVIA_URL=${2:?--url needs an address}; shift ;;
            --version) CHATVIA_VERSION=${2:?--version needs a version} VERSION_GIVEN=1; shift ;;
            --image) CHATVIA_IMAGE=${2:?--image needs a name}; shift ;;
            --no-pull) NO_PULL=1 ;;
            --domain) CHATVIA_DOMAIN=${2:?--domain needs a name}; shift ;;
            --no-https) CHATVIA_HTTPS=no ;;
            --no-firewall) CHATVIA_FIREWALL=no ;;
            --force) FORCE=1 ;;
            --no-auto-update) CHATVIA_AUTO_UPDATE=no ;;
            --db-host) CHATVIA_DB_HOST=${2:?--db-host needs a host}; shift ;;
            --db-port) CHATVIA_DB_PORT=${2:?--db-port needs a number}; shift ;;
            --db-name) CHATVIA_DB_DATABASE=${2:?--db-name needs a name}; shift ;;
            --db-user) CHATVIA_DB_USERNAME=${2:?--db-user needs a name}; shift ;;
            --yes | -y) YES=1 ;;
            --upgrade) UPGRADE=1 ;;
            --no-start) NO_START=1 ;;
            --dry-run) DRY_RUN=1 ;;
            --count-install) CHATVIA_COUNT_INSTALL=yes ;;
            --no-count-install) CHATVIA_COUNT_INSTALL=no ;;
            --news-email) CHATVIA_NEWS_EMAIL=${2:?--news-email needs an address}; shift ;;
            --no-news-email) CHATVIA_NEWS_EMAIL=no ;;
            -h | --help) usage; exit 0 ;;
            *) die "Unknown option: $1" 'See --help.' ;;
        esac
        shift
    done
}

cleanup() {
    keys_off
    if [ -n "${WORK_DIR:-}" ]; then rm -rf "$WORK_DIR"; fi
}

interrupted() {
    keys_off
    printf '\n  %s  %s\n\n' "$END" 'Stopped.'
    exit 130
}

main() {
    YES=0 UPGRADE=0 NO_START=0 DRY_RUN=0 PASSWORD_GENERATED=0 ADMIN_CREATED=1 USE_SUDO=0 APP_PULLED=0
    ADMIN_EMAIL='' ADMIN_PASS='' APP_PORT='' APP_URL='' TTY_STATE=''
    EMBEDDING_TYPE=halfvec EMBEDDING_DIMENSIONS=1024 EMBEDDING_INDEX=binary
    VERSION_GIVEN=0
    if [ -n "${CHATVIA_VERSION:-}" ]; then VERSION_GIVEN=1; fi
    CHATVIA_VERSION=${CHATVIA_VERSION:-latest}
    CHATVIA_IMAGE=${CHATVIA_IMAGE:-}
    NO_PULL=${CHATVIA_NO_PULL:-0}
    MODE=${CHATVIA_INSTALL:-} FORCE=${CHATVIA_FORCE:-0} ARGS=("$@")
    CHATVIA_REPO=${CHATVIA_REPO:-$NATIVE_REPO} CHATVIA_RELEASE='' CHATVIA_BRANCH=''
    DOMAIN='' SERVER_IP='' HTTPS=no FIREWALL=no SWAP=no AUTO_UPDATE=no DNS_READY=1
    DATABASE=local DB_HOST=127.0.0.1 DB_PORT=5432 DB_NAME=chatvia DB_USER=chatvia DB_PASS=''
    DB_ANALYTICS=analytics DB_PULSE=pulse DB_SPECTRA=spectra DB_MAJOR=$NATIVE_PG

    setup_output
    parse_args "$@"
    case $MODE in
        '' | docker | complete) ;;
        *) die "CHATVIA_INSTALL must be docker or complete, not $MODE." ;;
    esac
    # Options that only the Docker install has.
    DOCKER_OPTIONS=0
    if [ -n "${CHATVIA_DIR:-}${CHATVIA_PORT:-}${CHATVIA_URL:-}$CHATVIA_IMAGE" ] ||
        [ "$NO_PULL" = 1 ] || [ "$NO_START" = 1 ]; then
        DOCKER_OPTIONS=1
    fi

    trap cleanup EXIT
    trap interrupted INT TERM
    WORK_DIR=$(mktemp -d 2>/dev/null || mktemp -d -t chatvia)
    LOG="$WORK_DIR/install.log"

    INTERACTIVE=1
    if [ "$YES" = 1 ]; then
        INTERACTIVE=0
    elif ! { : </dev/tty; } 2>/dev/null; then
        die 'This installer asks a few questions, but there is no terminal to ask them in.' \
            'Run it in a terminal, or pass --yes with the settings (see --help).'
    fi
    if [ "$INTERACTIVE" = 1 ]; then
        # Restored on exit, even after Ctrl-C in the middle of a question.
        TTY_STATE=$(stty -g 2>/dev/null </dev/tty) || TTY_STATE=''
    fi

    case "$(uname -s)" in
        Darwin) OS=macos ;;
        Linux) OS=linux ;;
        *) die "This installer supports Linux and macOS, not $(uname -s)." \
            'On Windows, run it inside WSL 2 (Ubuntu) with Docker Desktop.' ;;
    esac

    # A complete installation started by another user runs again as root,
    # and goes on below the questions it already asked.
    if [ -z "${CHATVIA_RESTARTED:-}" ]; then
        banner
        intro 'Setup'
        if [ "$DRY_RUN" = 1 ]; then info "$(accent 'Dry run: nothing will be changed.')"; fi
    fi

    choose_mode
    if [ "$MODE" = complete ]; then
        native_main
        return
    fi
    if [ -n "${CHATVIA_DB_HOST:-}${CHATVIA_DB_PORT:-}${CHATVIA_DB_DATABASE:-}${CHATVIA_DB_USERNAME:-}" ]; then
        die '--db-host, --db-port, --db-name and --db-user are for the complete installation.' \
            'The Docker install runs a PostgreSQL of its own. Leave them out, or pass --complete.'
    fi

    INSTALL_DIR=${CHATVIA_DIR:-$HOME/chatvia}
    if [ "$INTERACTIVE" = 1 ] && [ -z "${CHATVIA_DIR:-}" ]; then
        ask INSTALL_DIR 'Install folder' --default "$(pretty_path "$INSTALL_DIR")" \
            --hint 'Your ChatVia.ai settings and backups are kept here.' \
            --check check_folder --status folder_status --complete complete_folder
    fi
    INSTALL_DIR=$(resolve_dir "$INSTALL_DIR")
    check_folder "$INSTALL_DIR" || die "$INVALID"

    case $STATE in
        unfinished) do_resume ;;
        installed) do_upgrade ;;
        *) do_install ;;
    esac
}

main "$@"
