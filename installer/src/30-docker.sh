#------------------------------------------------------------------------------
# Docker
#------------------------------------------------------------------------------

install_docker_linux() {
    warn 'Docker is not installed.'
    if [ "$INTERACTIVE" = 0 ]; then
        die 'Install Docker first: https://docs.docker.com/engine/install/'
    fi
    info "It can be installed with Docker's official script (get.docker.com)."
    info 'This needs administrator rights (sudo).'
    if ! confirm 'Install Docker now?' y; then
        die 'ChatVia.ai needs Docker.' 'Install it from https://docs.docker.com/engine/install/ and run this again.'
    fi
    [ "$DRY_RUN" = 1 ] && { info "$(dim 'Would run get.docker.com with sudo. (dry run)')"; return; }

    local script="$WORK_DIR/get-docker.sh"
    curl -fsSL https://get.docker.com -o "$script"
    sudo sh "$script"
    if command -v systemctl >/dev/null 2>&1; then
        sudo systemctl enable --now docker
    fi
    ok 'Docker is installed.'
}

install_docker_macos() {
    warn 'Docker is not installed.'
    if [ "$INTERACTIVE" = 0 ]; then
        die 'Install Docker Desktop or OrbStack first: https://docs.docker.com/desktop/setup/install/mac-install/'
    fi
    if ! command -v brew >/dev/null 2>&1; then
        die 'Install Docker Desktop (or OrbStack), start it, and run this again:' \
            'https://docs.docker.com/desktop/setup/install/mac-install/' \
            'https://orbstack.dev'
    fi
    choose 'Install Docker with Homebrew?' \
        'OrbStack  — light and fast, free for personal use' \
        'Docker Desktop  — the official app' \
        'No, I will install it myself'
    local cask
    case $CHOICE in
        0) cask=orbstack ;;
        1) cask=docker ;;
        *) die 'ChatVia.ai needs Docker. Install it and run this again.' ;;
    esac
    # In the foreground: Homebrew may ask for your password.
    info "$(dim "Running: brew install --cask $cask")"
    if [ "$DRY_RUN" = 1 ]; then
        info "$(dim '(dry run)')"
        return
    fi
    brew install --cask "$cask" </dev/tty
    ok 'Docker is installed.'
}

start_docker() {
    if [ "$OS" = macos ]; then
        local app=''
        if [ -d /Applications/OrbStack.app ]; then app=OrbStack
        elif [ -d /Applications/Docker.app ]; then app=Docker
        fi
        if [ -n "$app" ]; then
            info "Starting $app. If it asks you to accept its terms or sign in, do that."
            [ "$DRY_RUN" = 1 ] || open -a "$app"
        elif command -v colima >/dev/null 2>&1; then
            run_step 'Starting Colima' colima start
        else
            die 'Docker is installed but not running. Start it and run this again.'
        fi
    else
        if [ "$INTERACTIVE" = 1 ] && command -v systemctl >/dev/null 2>&1 && confirm 'Docker is not running. Start it now (sudo)?' y; then
            [ "$DRY_RUN" = 1 ] || sudo systemctl start docker
        else
            die 'Docker is not running. Start it (for example: sudo systemctl start docker) and run this again.'
        fi
    fi
    [ "$DRY_RUN" = 1 ] && return
    run_step 'Waiting for Docker to start' wait_until 300 docker_is_running ||
        die 'Docker did not start within 5 minutes. Start it and run this again.'
}

check_docker() {
    if ! command -v docker >/dev/null 2>&1; then
        if [ "$OS" = macos ]; then install_docker_macos; else install_docker_linux; fi
        if [ "$DRY_RUN" = 1 ] && ! command -v docker >/dev/null 2>&1; then
            info "$(dim 'Skipping the other Docker checks. (dry run)')"
            return
        fi
    fi

    USE_SUDO=0
    local output
    if ! output=$(docker info 2>&1); then
        # Captured first: with pipefail, `docker info | grep` reports docker's
        # failure even when grep matches.
        if [ "$OS" = linux ] && [[ $output == *[Pp]ermission\ denied* ]]; then
            warn 'Your user cannot reach Docker, so Docker commands will run with sudo.'
            info "$(dim "To avoid this later: sudo usermod -aG docker ${USER:-$(id -un)} (then sign in again).")"
            USE_SUDO=1
            sudo -v
        fi
        if ! docker_is_running; then
            start_docker
        fi
    fi

    local version
    version=$(docker_cmd compose version --short 2>/dev/null | sed 's/^v//') || version=''
    if [ -z "$version" ]; then
        die 'Docker Compose is missing.' 'Install the Docker Compose plugin: https://docs.docker.com/compose/install/'
    fi
    if ! version_at_least "$version" "$MIN_COMPOSE_VERSION"; then
        die "Docker Compose $version is too old: ChatVia.ai needs $MIN_COMPOSE_VERSION or newer." \
            'Update Docker (or the Compose plugin) and run this again.'
    fi

    ok "Docker is running $(dim "(Compose $version)")"
}

#------------------------------------------------------------------------------
# Install
#------------------------------------------------------------------------------

image_ref() {
    printf '%s:%s' "${CHATVIA_IMAGE:-$DEFAULT_IMAGE}" "$CHATVIA_VERSION"
}

# With --no-pull the image must already be here; checked before anything
# is written.
require_local_image() {
    [ "$NO_PULL" = 1 ] || return 0
    [ "$DRY_RUN" = 0 ] || return 0
    local ref
    ref=$(image_ref)
    if ! docker_cmd image inspect "$ref" >/dev/null 2>&1; then
        # shellcheck disable=SC2016 # the command is shown, not run
        die "The image $ref is not on this machine." \
            'Build it first (docker build --build-arg SHAKOMAKO_VERSION=$(npm run -s shakomako:version) -t NAME:VERSION .), or run without --no-pull.'
    fi
}

# Downloads the image, unless --no-pull uses the one already here.
get_image() {
    if [ "$NO_PULL" = 0 ]; then
        run_step "$1" compose pull
        return
    fi
    ok "Using the image on this machine $(dim "($(image_ref))")"
}

# image_version REF: the release a Chatvia image on this machine was built
# from, from its version label. A local build has no such label: it inherits
# the label of its base image, whose source is not Chatvia.
image_version() {
    local labels version
    labels=$(docker_cmd image inspect --format \
        '{{index .Config.Labels "org.opencontainers.image.source"}}|{{index .Config.Labels "org.opencontainers.image.version"}}' \
        "$1" 2>/dev/null) || return 1
    version=${labels#*|}
    case ${labels%%|*} in *chatvia*) ;; *) return 1 ;; esac
    is_exact_version "$version" || return 1
    printf '%s' "$version"
}

# Turns a moving tag (latest, 1, 1.4) into the release it points to now, so
# .env, compose.yaml and the manage tool all name one version. It downloads
# that image, so the pull afterwards has nothing left to do for it. A local
# build (--no-pull) keeps its tag. APP_PULLED says the image is here now.
resolve_version() {
    APP_PULLED=0
    if is_exact_version "$CHATVIA_VERSION" || [ "$NO_PULL" = 1 ] || [ "$DRY_RUN" = 1 ]; then return 0; fi
    local ref version what="version $CHATVIA_VERSION"
    ref=$(image_ref)
    if [ "$CHATVIA_VERSION" = latest ]; then what='the newest version'; fi
    run_step "Downloading $what of ChatVia.ai" docker_cmd pull "$ref" ||
        die "Could not download $ref." 'Check the version and the internet connection.'
    version=$(image_version "$ref") ||
        die "The image $ref does not say which version it is." 'Name the version instead, like --version 1.4.2.'
    CHATVIA_VERSION=$version
    APP_PULLED=1
    ok "ChatVia.ai $(bold "$version")"
}

compose_url() {
    if [ -n "${CHATVIA_COMPOSE_URL:-}" ]; then
        printf '%s' "$CHATVIA_COMPOSE_URL"
    elif is_exact_version "$CHATVIA_VERSION"; then
        printf '%s/v%s/docker/compose.yaml' "$CHATVIA_REPO_RAW" "$CHATVIA_VERSION"
    else
        printf '%s/main/docker/compose.yaml' "$CHATVIA_REPO_RAW"
    fi
}

manage_url() {
    if [ -n "${CHATVIA_MANAGE_URL:-}" ]; then
        printf '%s' "$CHATVIA_MANAGE_URL"
    else
        printf '%s' "$(compose_url | sed 's|/compose\.yaml$|/manage|')"
    fi
}

# The files are first downloaded into the work folder (fetch_*), and only
# then put in the install folder (place_*), so an upgrade that fails half
# way leaves the install as it was. Each one is a step in run_step, so it
# checks its own commands: set -e is off there.

fetch_compose() {
    local target="$WORK_DIR/compose.yaml"
    curl -fsSL "$(compose_url)" -o "$target" || return
    if ! grep -q '^services:' "$target"; then
        echo "The downloaded file is not a compose file: $(compose_url)" >&2
        return 1
    fi
}

# The management tool (start, stop, backup, restore, ...), from the same
# version as compose.yaml.
fetch_manage() {
    local target="$WORK_DIR/manage" first=''
    curl -fsSL "$(manage_url)" -o "$target" || return
    IFS= read -r first <"$target" || true
    if [ "$first" != '#!/usr/bin/env bash' ]; then
        echo "The downloaded file is not the manage tool: $(manage_url)" >&2
        return 1
    fi
    chmod 755 "$target"
}

# place_file NAME: copies it next to the old one, then renames it over it.
# The rename makes a new file, so a running ./manage (which runs this
# installer to upgrade) keeps reading its old copy.
place_file() {
    cp "$WORK_DIR/$1" "$INSTALL_DIR/.$1.new" && mv "$INSTALL_DIR/.$1.new" "$INSTALL_DIR/$1"
}

place_compose() {
    if [ -f "$INSTALL_DIR/compose.yaml" ]; then
        cp "$INSTALL_DIR/compose.yaml" "$INSTALL_DIR/compose.yaml.bak" || return
    fi
    place_file compose.yaml
}

fetch_files() {
    fetch_compose && fetch_manage
}

place_files() {
    place_compose && place_file manage
}

download_compose() {
    fetch_compose && place_compose
}

download_manage() {
    fetch_manage && place_file manage
}

download_files() {
    fetch_files && place_files
}

# postgres/postgresql.conf: PostgreSQL's settings, taken out of its
# container once so they can be changed (./manage postgres apply), with
# compose.override.yaml to mount it. Runs before the first full start.
export_postgres_settings() {
    [ -f "$INSTALL_DIR/postgres/postgresql.conf" ] && return 0
    run_step 'Saving the PostgreSQL settings to postgres/postgresql.conf' \
        "$INSTALL_DIR/manage" postgres export --if-missing --yes ||
        die 'The PostgreSQL settings were not saved.' "See why: $LOG"
}

write_env() {
    local secure='' image_line=''
    [ -n "$CHATVIA_IMAGE" ] && image_line="CHATVIA_IMAGE=$CHATVIA_IMAGE"$'\n'
    case $APP_URL in https://*) secure=$'SESSION_SECURE_COOKIE=true\n' ;; esac
    (
        umask 077
        cat >"$INSTALL_DIR/.env" <<EOF
# Chatvia settings, written by the installer on $(date '+%Y-%m-%d').
# Only what Chatvia needs to start. Email, API keys, the AI services and the
# other options are set in the admin panel, under Settings.
# More: $CHATVIA_DOCS#settings

# How the database stores embeddings. Chosen at install: they can NEVER
# change afterwards, or Chatvia stops with an error on the next start.
EMBEDDING_VECTOR_TYPE=$EMBEDDING_TYPE
EMBEDDING_DIMENSIONS=$EMBEDDING_DIMENSIONS

# The search index: binary (smaller, faster) or full (the most exact).
# Unlike the two above, it can be changed later.
EMBEDDING_INDEX=$EMBEDDING_INDEX

${image_line}CHATVIA_VERSION=$CHATVIA_VERSION
APP_PORT=$APP_PORT
APP_URL=$APP_URL
${secure}
# The first admin. Only read on the first start.
ADMIN_EMAIL=$ADMIN_EMAIL
EOF
    )
}

# `up -d` first waits for the one-time database setup (the migrate service),
# then starts the app. This runs in run_step's background shell, so the
# exported password never reaches this script's own environment.
start_chatvia() {
    export ADMIN_PASSWORD="$ADMIN_PASS"
    compose up -d
}

# The queue workers start sized to this machine's memory and processors
# (Settings → Platform in the admin panel changes them later). Horizon picks
# the numbers up through its restart command in Redis.
tune_docker_workers() {
    compose exec -T app php artisan chatvia:workers:tune --restart --no-interaction
}

# Whether this start made the admin account (the setup log says so), read
# before the setup container is removed.
read_setup_result() {
    local output
    ADMIN_CREATED=1
    [ "$DRY_RUN" = 1 ] && return 0
    # Captured first: grep -q stops reading early, and pipefail would then
    # report the writer's broken pipe instead of the match.
    output=$(compose logs --no-color migrate 2>/dev/null) || output=''
    if [[ $output == *'already exists'* ]]; then
        ADMIN_CREATED=0
    fi
}

admin_password_text() {
    if [ "$ADMIN_CREATED" = 0 ]; then
        printf '%s' 'unchanged: the account was made by an earlier attempt'
    elif [ "$PASSWORD_GENERATED" = 1 ]; then
        printf '%s  %s' "$(bold "$ADMIN_PASS")" "$(accent '← save it now, it is shown only once')"
    else
        printf '%s' 'the one you typed'
    fi
}

mark_finished() {
    [ "$DRY_RUN" = 1 ] && return 0
    printf '\n# Install finished on %s.\n' "$(date '+%Y-%m-%d')" >>"$INSTALL_DIR/.env"
}

# The stopped setup container keeps its settings (with the password) until
# it is removed. The next start makes a new one without it.
remove_setup_container() {
    [ "$DRY_RUN" = 1 ] && return 0
    compose rm -f migrate >>"$LOG" 2>&1 || true
}

setup_failed() {
    if [ "$DRY_RUN" = 0 ]; then
        section 'Database setup log'
        compose logs --no-color --tail 30 migrate 2>&1 | quote >&2 || true
    fi
    die "$1" "More: cd $(pretty_path "$INSTALL_DIR") && ./manage logs migrate"
}

check_existing_data() {
    # The compose project name is fixed, so its data volumes are shared by
    # every folder on this machine: a second first install would reuse them.
    local volumes
    volumes=$(docker_cmd volume ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT" 2>/dev/null) || volumes=''
    if [ -n "$volumes" ]; then
        die 'ChatVia.ai data already exists on this computer, probably from an install in another folder.' \
            'Run this installer from that folder (--dir) to upgrade it.' \
            'To start over, delete that data first (this removes all ChatVia.ai data):' \
            "docker volume rm \$(docker volume ls -q --filter label=com.docker.compose.project=$COMPOSE_PROJECT)"
    fi
}

do_install() {
    check_docker
    if command -v docker >/dev/null 2>&1; then check_existing_data; fi
    require_local_image

    collect_settings

    show_summary
    if [ "$INTERACTIVE" = 1 ] && ! confirm 'Install ChatVia.ai with these settings?' y; then
        outro 'Nothing was changed.'
        exit 0
    fi

    if [ "$DRY_RUN" = 0 ]; then
        mkdir -p "$INSTALL_DIR"
        LOG="$INSTALL_DIR/install.log"
        : >"$LOG"
    fi
    resolve_version
    run_step 'Downloading compose.yaml and the manage tool' download_files
    if [ "$DRY_RUN" = 0 ]; then
        write_env
        ok "Settings saved $(dim "$(pretty_path "$INSTALL_DIR")/.env")"
    else
        block task
        printf '  %s  %s %s\n' "$SKIP" "Saving the settings to $(pretty_path "$INSTALL_DIR")/.env" "$(dim '(dry run)')"
    fi
    if [ "$APP_PULLED" = 1 ]; then
        get_image 'Downloading the database and Redis'
    else
        get_image 'Downloading ChatVia.ai (a few minutes the first time)'
    fi
    start_and_finish
}

# The part of an install after the settings are written; also used to
# finish an install that stopped half way.
start_and_finish() {
    export_postgres_settings
    if ! run_step 'Setting up the database and the admin account, then starting' start_chatvia; then
        setup_failed 'ChatVia.ai did not start.'
    fi
    read_setup_result
    remove_setup_container
    if ! run_step 'Waiting for the web app' wait_until 180 app_is_up; then
        info "The admin account is ready: $(bold "$ADMIN_EMAIL"), password $(admin_password_text)"
        die 'The web app did not answer within 3 minutes.' \
            "See why: cd $(pretty_path "$INSTALL_DIR") && ./manage status && ./manage logs app" \
            'Then run this installer again to finish.'
    fi
    run_step 'Sizing the queue workers to this machine' tune_docker_workers ||
        warn "The workers kept their defaults. Size them later: $(accent './manage artisan chatvia:workers:tune --restart')"
    mark_finished

    show_finish
}

do_resume() {
    warn "An earlier install in $(bold "$(pretty_path "$INSTALL_DIR")") did not finish."
    if [ "$INTERACTIVE" = 1 ]; then
        choose 'What do you want to do?' \
            'Finish it  — start ChatVia.ai again with the saved settings' \
            'Nothing, exit'
        if [ "$CHOICE" != 0 ]; then
            outro 'Nothing was changed.'
            exit 0
        fi
    fi

    check_docker
    ADMIN_EMAIL=$(env_value ADMIN_EMAIL)
    APP_PORT=$(env_value APP_PORT)
    APP_URL=$(env_value APP_URL)
    CHATVIA_VERSION=$(env_value CHATVIA_VERSION)
    APP_PORT=${APP_PORT:-8000} APP_URL=${APP_URL:-http://localhost:${APP_PORT:-8000}} CHATVIA_VERSION=${CHATVIA_VERSION:-latest}
    CHATVIA_IMAGE=${CHATVIA_IMAGE:-$(env_value CHATVIA_IMAGE)}

    # Used only when the earlier attempt did not make the admin account yet.
    ADMIN_PASS=${CHATVIA_ADMIN_PASSWORD:-}
    if [ -z "$ADMIN_PASS" ] && [ "$INTERACTIVE" = 1 ]; then
        choose 'Admin password (used if the account does not exist yet)' \
            'Create a strong one for me  — shown once at the end' \
            'I will type my own'
        if [ "$CHOICE" = 1 ]; then ask_secret ADMIN_PASS 'Your password'; fi
    fi
    if [ -z "$ADMIN_PASS" ]; then
        ADMIN_PASS=$(random_password)
        PASSWORD_GENERATED=1
    fi
    if [ "$DRY_RUN" = 0 ]; then
        LOG="$INSTALL_DIR/install.log"
        printf '\n== Finish the install %s\n' "$(date)" >>"$LOG"
    fi
    [ -f "$INSTALL_DIR/compose.yaml" ] || run_step 'Downloading compose.yaml' download_compose
    [ -f "$INSTALL_DIR/manage" ] || run_step 'Downloading the manage tool' download_manage
    get_image 'Downloading ChatVia.ai'
    start_and_finish
}

collect_settings() {
    local default_port
    default_port=${CHATVIA_PORT:-$(first_free_port "$DEFAULT_PORT")}

    if [ "$INTERACTIVE" = 0 ]; then
        ADMIN_EMAIL=${CHATVIA_ADMIN_EMAIL:-}
        [ -n "$ADMIN_EMAIL" ] || die 'Set the admin e-mail: --email you@example.com (or CHATVIA_ADMIN_EMAIL).'
        valid_email "$ADMIN_EMAIL" || die "$INVALID"
        ADMIN_PASS=${CHATVIA_ADMIN_PASSWORD:-}
        if [ -z "$ADMIN_PASS" ]; then
            ADMIN_PASS=$(random_password)
            PASSWORD_GENERATED=1
        fi
        APP_PORT=$default_port
        valid_port "$APP_PORT" || die "$INVALID"
        APP_URL=${CHATVIA_URL:-http://localhost:$APP_PORT}
        valid_url "$APP_URL" || die "$INVALID"
        EMBEDDING_TYPE=${CHATVIA_EMBEDDING_TYPE:-halfvec}
        EMBEDDING_DIMENSIONS=${CHATVIA_EMBEDDING_DIMENSIONS:-1024}
        EMBEDDING_INDEX=${CHATVIA_EMBEDDING_INDEX:-binary}
        case $EMBEDDING_TYPE in halfvec | vector) ;; *) die 'CHATVIA_EMBEDDING_TYPE must be halfvec or vector.' ;; esac
        [[ $EMBEDDING_DIMENSIONS =~ ^[0-9]+$ ]] || die 'CHATVIA_EMBEDDING_DIMENSIONS must be a number.'
        case $EMBEDDING_INDEX in binary | full) ;; *) die 'CHATVIA_EMBEDDING_INDEX must be binary or full.' ;; esac
        ok "Admin $(accent "$ADMIN_EMAIL"), port $(accent "$APP_PORT"), storage $(accent "$EMBEDDING_TYPE($EMBEDDING_DIMENSIONS), $EMBEDDING_INDEX index")"
        return
    fi

    ask_admin

    ask APP_PORT 'Port on this computer' --default "$default_port" \
        --check valid_port --status port_status

    choose 'Where will people open ChatVia.ai?' \
        "On this computer only  — http://localhost:$APP_PORT" \
        'On my own domain or IP address  — your proxy handles HTTPS'
    if [ "$CHOICE" = 0 ]; then
        APP_URL="http://localhost:$APP_PORT"
    else
        ask APP_URL 'Address' --default "${CHATVIA_URL:-}" --placeholder 'https://chat.example.com' \
            --hint 'The full address people open, without a path.' --check valid_url
    fi

    ask_setup
}

# The first admin's e-mail and password. Shared by both kinds of install.
ask_admin() {
    ask ADMIN_EMAIL 'Admin e-mail' --default "${CHATVIA_ADMIN_EMAIL:-}" \
        --placeholder 'you@example.com' --hint 'The first admin account signs in with it.' \
        --check valid_email

    choose 'Admin password' \
        'Create a strong one for me  — shown once at the end' \
        'I will type my own'
    if [ "$CHOICE" = 0 ]; then
        ADMIN_PASS=$(random_password)
        PASSWORD_GENERATED=1
    else
        ask_secret ADMIN_PASS 'Your password'
    fi
}

# Recommended settings, or the search storage and the version. Shared by
# both kinds of install.
ask_setup() {
    block text
    info "What each setting means: $(accent "$CHATVIA_DOCS#setup")"
    choose 'Setup' \
        'Recommended settings  — best for almost everyone' \
        'Custom  — choose how search data is stored and indexed, and the version'
    if [ "$CHOICE" = 0 ]; then
        EMBEDDING_TYPE=halfvec
        EMBEDDING_DIMENSIONS=1024
        EMBEDDING_INDEX=binary
        return
    fi

    warn "$(bold 'These two settings are permanent.')"
    info 'They decide how the search data of your knowledge is stored,'
    info 'and can never be changed after the install.'
    choose 'Vector type' \
        'halfvec  — half the space, almost the same search quality (recommended)' \
        'vector  — full precision, twice the space'
    if [ "$CHOICE" = 0 ]; then EMBEDDING_TYPE=halfvec; else EMBEDDING_TYPE=vector; fi

    choose 'Vector size (dimensions)' \
        '1024  — works with every AI provider (recommended)' \
        '512  — smaller and faster, a little less precise (Qwen3, OpenAI, Gemini, Cohere, Jina, Voyage AI)' \
        '768  — Qwen3, OpenAI, Gemini, Jina' \
        '1536  — more detail, more space (Qwen3, OpenAI, Gemini, Cohere)' \
        '2048  — the most detail, the most space (Qwen3, Gemini, Voyage AI, OpenAI large)'
    case $CHOICE in
        0) EMBEDDING_DIMENSIONS=1024 ;;
        1) EMBEDDING_DIMENSIONS=512 ;;
        2) EMBEDDING_DIMENSIONS=768 ;;
        3) EMBEDDING_DIMENSIONS=1536 ;;
        *) EMBEDDING_DIMENSIONS=2048 ;;
    esac

    block text
    info 'Binary works best at 1024 dimensions or more. Unlike the two settings'
    info 'above, the search index can be changed later.'
    choose 'Search index' \
        'binary  — about 6× smaller search index, faster, almost the same results (recommended)' \
        'full  — the largest index, the most exact'
    if [ "$CHOICE" = 0 ]; then EMBEDDING_INDEX=binary; else EMBEDDING_INDEX=full; fi

    local hint='latest, or a version number like 1.2.0'
    if [ "$MODE" = complete ]; then hint="$hint, or a branch like main (not a release)"; fi
    ask CHATVIA_VERSION 'ChatVia.ai version' --default "$CHATVIA_VERSION" --hint "$hint"
}

show_summary() {
    local password='the one you typed' image folder email address port storage index image_row
    if [ "$PASSWORD_GENERATED" = 1 ]; then password='a new random one, shown at the end'; fi
    image=$(image_ref)
    if [ "$NO_PULL" = 1 ]; then image="$image $(dim '(on this machine)')"; fi

    kv_to folder 'Folder' "$(pretty_path "$INSTALL_DIR")"
    kv_to email 'Admin e-mail' "$ADMIN_EMAIL"
    kv_to password 'Admin password' "$password"
    kv_to address 'Address' "$APP_URL"
    kv_to port 'Port' "$APP_PORT"
    kv_to storage 'Search storage' "$EMBEDDING_TYPE, $EMBEDDING_DIMENSIONS dimensions $(dim '(permanent)')"
    kv_to index 'Search index' "$EMBEDDING_INDEX $(dim '(can be changed later)')"
    kv_to image_row 'Image' "$image"
    note 'Check your settings' "$folder" "$email" "$password" "$address" "$port" "$storage" "$index" "$image_row"
}

# Opens the admin panel in the browser, when this machine has one: its
# setup asks for AI, models, documents, storage and email.
offer_browser() {
    [ "$INTERACTIVE" = 1 ] && [ "$DRY_RUN" = 0 ] || return 0
    local opener=''
    if [ "$OS" = macos ]; then
        opener=open
    elif command -v xdg-open >/dev/null 2>&1 && [ -n "${DISPLAY:-}${WAYLAND_DISPLAY:-}" ]; then
        opener=xdg-open
    fi
    [ -n "$opener" ] || return 0
    if confirm 'Open the admin panel in your browser to finish the setup?' y; then
        "$opener" "$APP_URL/admin" >/dev/null 2>&1 || true
    fi
}

# install_os: macos, or the distribution's ID from /etc/os-release (ubuntu,
# debian…), or linux when it has none.
install_os() {
    if [ "$OS" = macos ]; then
        printf macos
        return
    fi
    local id
    id=$(sed -n 's/^ID=//p' /etc/os-release 2>/dev/null | tr -d '"' | head -n 1) || id=''
    case $id in
        '' | *[!a-z0-9._-]*) printf linux ;;
        *) printf '%s' "$id" ;;
    esac
}

# One anonymous count for chatvia.ai, so the project knows how many people
# install it: the install type and the operating system, nothing else (no
# ID, no address, no version). Only after a first install finishes, never on
# an upgrade, and only with a yes: --yes alone never sends it. A failed send
# is ignored.
count_install() {
    [ "$DRY_RUN" = 0 ] || return 0
    case ${CHATVIA_COUNT_INSTALL:-} in
        yes | true | 1) ;;
        no | false | 0) return 0 ;;
        *)
            [ "$INTERACTIVE" = 1 ] || return 0
            block text
            info "$(dim 'Help us count installs: this sends only how you installed (Docker or')"
            info "$(dim "complete) and the operating system ($(install_os)). No address or ID.")"
            confirm 'Send an anonymous install count to chatvia.ai?' n || return 0
            ;;
    esac
    curl -fsS -o /dev/null --max-time 5 -X POST \
        -H 'Content-Type: application/json' -H 'Accept: application/json' \
        --data "{\"mode\":\"$MODE\",\"os\":\"$(install_os)\"}" \
        "${CHATVIA_COUNT_URL:-$INSTALL_COUNT_URL}" >/dev/null 2>&1 || true
}

# An e-mail address for news about ChatVia.ai (new versions and other
# updates), sent to chatvia.ai on its own, apart from the install count.
# Only after a first install finishes, and only an address the person typed
# or gave with --news-email: --yes alone never sends one. A failed send is
# ignored.
send_news_email() {
    [ "$DRY_RUN" = 0 ] || return 0
    local email=${CHATVIA_NEWS_EMAIL:-}
    case $email in
        no | false | 0) return 0 ;;
        '')
            [ "$INTERACTIVE" = 1 ] || return 0
            ask email 'Your e-mail for ChatVia.ai news (optional)' \
                --placeholder 'you@example.com' --optional 'Skipped' \
                --hint 'New versions and other updates. Kept apart from the install count.' \
                --check valid_email
            [ -n "$email" ] || return 0
            ;;
        *) valid_email "$email" || return 0 ;;
    esac
    email=${email//\\/\\\\}
    email=${email//\"/\\\"}
    curl -fsS -o /dev/null --max-time 5 -X POST \
        -H 'Content-Type: application/json' -H 'Accept: application/json' \
        --data "{\"email\":\"$email\"}" \
        "${CHATVIA_NEWS_URL:-$NEWS_EMAIL_URL}" >/dev/null 2>&1 || true
}

show_finish() {
    local folder title open admin email password saved='' n=1
    folder=$(pretty_path "$INSTALL_DIR")
    if [ "$DRY_RUN" = 1 ]; then
        title='Dry run done. A real install ends like this:'
    else
        title=$(bold 'ChatVia.ai is running')
    fi

    kv_to open 'Open the app' "$(accent "$APP_URL")"
    kv_to admin 'Admin panel' "$(accent "$APP_URL/admin")"
    kv_to email 'Admin e-mail' "$ADMIN_EMAIL"
    if [ "$ADMIN_CREATED" = 0 ]; then
        kv_to password 'Admin password' 'unchanged: made by an earlier attempt'
    elif [ "$PASSWORD_GENERATED" = 1 ]; then
        kv_to password 'Admin password' "$(bold "$ADMIN_PASS")"
        kv_to saved '' "$(accent "$S_UP save it now, it is shown only once")"
    else
        kv_to password 'Admin password' 'the one you typed'
    fi
    note "$title" "$open" "$admin" "$email" "$password" ${saved:+"$saved"}

    section 'Next steps'
    if [ "$APP_URL" != "http://localhost:$APP_PORT" ]; then
        info "$n. Point your proxy at http://localhost:$APP_PORT and set TRUSTED_PROXIES"
        info '   in .env (see the guide).'
        n=$((n + 1))
    fi
    info "$n. Sign in to the admin panel and set up two-factor authentication."
    info "$((n + 1)). Follow the short setup there: your AI provider and its API key,"
    info "   the models, and (optional) documents, storage and email."

    section 'Everyday commands' "in $folder"
    info "./manage status          $(dim 'is everything running?')"
    info "./manage logs            $(dim 'follow the logs')"
    info "./manage stop            $(dim 'stop (your data is kept)')"
    info "./manage start           $(dim 'start again')"
    info "./manage backup          $(dim 'back up the database and the app key')"
    info "./manage upgrade         $(dim 'upgrade to the newest version')"
    info "./manage postgres edit   $(dim 'tune PostgreSQL (postgres/postgresql.conf)')"
    info "./manage                 $(dim 'every command')"

    count_install
    send_news_email
    offer_browser
    outro "$(dim "Guide: $CHATVIA_DOCS")"
}

