#!/usr/bin/env bash
#
# Chatvia installer: runs Chatvia on this machine with Docker.
#
#   curl -fsSL https://chatvia.ai/install.sh | bash
#
# It checks Docker (and offers to install it), asks a few questions, writes
# compose.yaml and .env into the install folder, starts Chatvia and prints
# where to sign in. Run it again in the same folder to upgrade.
#
# Options (each also works as an environment variable):
#   --dir PATH          install folder               CHATVIA_DIR (~/chatvia)
#   --email EMAIL       first admin's e-mail         CHATVIA_ADMIN_EMAIL
#   --port PORT         port on this machine         CHATVIA_PORT (8899, or the next free one)
#   --url URL           address people open          CHATVIA_URL (http://localhost:PORT)
#   --version VERSION   image version                CHATVIA_VERSION (latest)
#   --image NAME        image name, without version  CHATVIA_IMAGE (ghcr.io/chatvia/chatvia)
#   --no-pull           use the image already on this machine (a local build)
#   --yes               no questions: use the options and the defaults
#   --upgrade           upgrade an existing install without asking
#   --dry-run           show what would happen and change nothing
#   -h, --help          show this help
#
# Only as environment variables:
#   CHATVIA_ADMIN_PASSWORD        the first admin's password (else a random one)
#   CHATVIA_EMBEDDING_TYPE        halfvec (default) or vector
#   CHATVIA_EMBEDDING_DIMENSIONS  1024 (default)
#   CHATVIA_COMPOSE_URL           where to download compose.yaml from
#   NO_COLOR                      plain output
#
# Everything runs inside main(), called on the last line, so a download that
# stops half way runs nothing.

set -euo pipefail

readonly CHATVIA_REPO_RAW="https://raw.githubusercontent.com/chatvia/chatvia"
readonly CHATVIA_DOCS="https://docs.chatvia.ai/guide/self-hosting/docker"
readonly COMPOSE_PROJECT="chatvia-stack"
readonly MIN_COMPOSE_VERSION="2.24.0"
readonly DEFAULT_PORT=8899
readonly DEFAULT_IMAGE="ghcr.io/chatvia/chatvia"

#------------------------------------------------------------------------------
# Output
#------------------------------------------------------------------------------

setup_output() {
    if [ -t 1 ] && [ -z "${NO_COLOR:-}" ] && [ "${TERM:-dumb}" != dumb ]; then
        C_RESET=$'\033[0m' C_BOLD=$'\033[1m' C_DIM=$'\033[2m'
        C_ACCENT=$'\033[38;5;99m' C_GREEN=$'\033[32m' C_RED=$'\033[31m' C_YELLOW=$'\033[33m'
        HIDE_CURSOR=$'\033[?25l' SHOW_CURSOR=$'\033[?25h' CLEAR_LINE=$'\r\033[2K'
        FANCY=1
    else
        C_RESET='' C_BOLD='' C_DIM='' C_ACCENT='' C_GREEN='' C_RED='' C_YELLOW=''
        HIDE_CURSOR='' SHOW_CURSOR='' CLEAR_LINE=''
        FANCY=0
    fi

    case "${LC_ALL:-${LC_CTYPE:-${LANG:-}}}" in
        *UTF-8* | *utf-8* | *UTF8* | *utf8*)
            SYM_OK='✓' SYM_FAIL='✗' SYM_WARN='!' SYM_ARROW='›' SYM_DOT='•'
            SPINNER=('⠋' '⠙' '⠹' '⠸' '⠼' '⠴' '⠦' '⠧' '⠇' '⠏') RULE='─' UNICODE=1
            ;;
        *)
            SYM_OK='+' SYM_FAIL='x' SYM_WARN='!' SYM_ARROW='>' SYM_DOT='*'
            SPINNER=('|' '/' '-' '\') RULE='-' UNICODE=0
            ;;
    esac
}

bold() { printf '%s%s%s' "$C_BOLD" "$*" "$C_RESET"; }
dim() { printf '%s%s%s' "$C_DIM" "$*" "$C_RESET"; }
accent() { printf '%s%s%s' "$C_ACCENT" "$*" "$C_RESET"; }

say() { printf '%s\n' "$*"; }
info() { printf '  %s\n' "$*"; }
ok() { printf '  %s%s%s %s\n' "$C_GREEN" "$SYM_OK" "$C_RESET" "$*"; }
warn() { printf '  %s%s%s %s\n' "$C_YELLOW" "$SYM_WARN" "$C_RESET" "$*"; }
fail() { printf '  %s%s%s %s\n' "$C_RED" "$SYM_FAIL" "$C_RESET" "$*" >&2; }

die() {
    fail "$1"
    shift
    local line
    for line in "$@"; do
        printf '    %s\n' "$line" >&2
    done
    exit 1
}

rule() {
    local width=58 line='' i
    for ((i = 0; i < width; i++)); do line="$line$RULE"; done
    printf '  %s\n' "$(dim "$line")"
}

step() {
    printf '\n  %s %s\n' "$(accent "[$1/$STEPS]")" "$(bold "$2")"
}

banner() {
    say ''
    if [ "$UNICODE" = 1 ]; then
        printf '  %s\n' "$(accent '┌─┐┬ ┬┌─┐┌┬┐┬  ┬┬┌─┐')"
        printf '  %s\n' "$(accent '│  ├─┤├─┤ │ └┐┌┘│├─┤')"
        printf '  %s\n' "$(accent '└─┘┴ ┴┴ ┴ ┴  └┘ ┴┴ ┴')"
    else
        printf '  %s\n' "$(accent 'C H A T V I A')"
    fi
    printf '  %s\n' "$(dim 'AI agents on your own server')"
    if [ "$DRY_RUN" = 1 ]; then
        printf '  %s\n' "$(accent 'Dry run: nothing will be changed.')"
    fi
}

#------------------------------------------------------------------------------
# Questions (read from the terminal: stdin is this script when piped)
#------------------------------------------------------------------------------

# The terminal closed (for example Ctrl-D): stop instead of asking forever.
no_input() {
    printf '%s\n' "${SHOW_CURSOR:-}"
    die 'Stopped: no answer.' 'Nothing was changed.'
}

# Rewrites the question line(s) above as one answered line.
answered() {
    local lines=$1 label=$2 value=$3 i
    if [ "$FANCY" = 1 ]; then
        for ((i = 0; i < lines; i++)); do printf '\033[1A\033[2K'; done
    fi
    printf '  %s%s%s %s %s\n' "$C_GREEN" "$SYM_OK" "$C_RESET" "$label" "$(accent "$value")"
}

# ask VAR "Question" "default" [validator]: a line of text.
ask() {
    local var=$1 label=$2 default=${3:-} validator=${4:-} answer hint=''
    [ -n "$default" ] && hint=" $(dim "($default)")"
    while true; do
        printf '  %s %s%s %s ' "$(accent "$SYM_ARROW")" "$(bold "$label")" "$hint" "$(dim ':')"
        IFS= read -r answer </dev/tty || no_input
        answer=${answer:-$default}
        if [ -n "$validator" ] && ! "$validator" "$answer"; then
            continue
        fi
        break
    done
    answered 1 "$label" "$answer"
    printf -v "$var" '%s' "$answer"
}

# ask_secret VAR "Question": a hidden password, typed twice.
ask_secret() {
    local var=$1 label=$2 first second
    while true; do
        printf '  %s %s %s ' "$(accent "$SYM_ARROW")" "$(bold "$label")" "$(dim '(hidden, at least 12 characters):')"
        IFS= read -rs first </dev/tty || no_input
        printf '\n'
        if [ "${#first}" -lt 12 ]; then
            warn 'Use at least 12 characters.'
            continue
        fi
        printf '  %s %s %s ' "$(accent "$SYM_ARROW")" "$(bold 'Type it again')" "$(dim ':')"
        IFS= read -rs second </dev/tty || no_input
        printf '\n'
        if [ "$first" != "$second" ]; then
            warn 'The two passwords are different. Try again.'
            continue
        fi
        break
    done
    answered 2 "$label" "$(dim '(hidden)')"
    printf -v "$var" '%s' "$first"
}

# choose "Question" "Option" ...: arrow keys (or j/k, or a number) and Enter.
# Sets CHOICE to the index of the picked option.
choose() {
    local label=$1
    shift
    local count=$# selected=0 key rest i
    local options=("$@")

    if [ "$FANCY" = 0 ]; then
        printf '  %s\n' "$(bold "$label")"
        for ((i = 0; i < count; i++)); do printf '    %d) %s\n' "$((i + 1))" "${options[i]}"; done
        while true; do
            printf '  Number (1-%d, Enter for 1): ' "$count"
            IFS= read -r key </dev/tty || no_input
            key=${key:-1}
            if [[ $key =~ ^[0-9]+$ ]] && [ "$key" -ge 1 ] && [ "$key" -le "$count" ]; then
                CHOICE=$((key - 1))
                return
            fi
        done
    fi

    printf '  %s %s %s\n' "$(accent "$SYM_ARROW")" "$(bold "$label")" "$(dim '(↑/↓, Enter)')"
    printf '%s' "$HIDE_CURSOR"
    while true; do
        for ((i = 0; i < count; i++)); do
            if [ "$i" -eq "$selected" ]; then
                printf '\033[2K    %s %s\n' "$(accent "$SYM_DOT")" "$(bold "${options[i]}")"
            else
                printf '\033[2K      %s\n' "$(dim "${options[i]}")"
            fi
        done

        IFS= read -rsn1 key </dev/tty || no_input
        if [ "$key" = $'\033' ]; then
            IFS= read -rsn2 -t 1 rest </dev/tty || rest=''
            case $rest in
                '[A') selected=$(((selected + count - 1) % count)) ;;
                '[B') selected=$(((selected + 1) % count)) ;;
            esac
        elif [ -z "$key" ]; then
            break
        elif [ "$key" = k ]; then
            selected=$(((selected + count - 1) % count))
        elif [ "$key" = j ]; then
            selected=$(((selected + 1) % count))
        elif [[ $key =~ ^[1-9]$ ]] && [ "$key" -le "$count" ]; then
            selected=$((key - 1))
        fi
        printf '\033[%dA' "$count"
    done
    printf '%s' "$SHOW_CURSOR"

    for ((i = 0; i < count; i++)); do printf '\033[2K\n'; done
    printf '\033[%dA' "$count"
    answered 1 "$label" "${options[selected]%%  —*}"
    CHOICE=$selected
}

# confirm "Question" [default y|n]: returns 0 for yes.
confirm() {
    local label=$1 default=${2:-y} key hint
    if [ "$default" = y ]; then hint='Y/n'; else hint='y/N'; fi
    printf '  %s %s %s ' "$(accent "$SYM_ARROW")" "$(bold "$label")" "$(dim "($hint)")"
    IFS= read -r key </dev/tty || no_input
    key=${key:-$default}
    case $key in
        y | Y | yes | YES | Yes) return 0 ;;
        *) return 1 ;;
    esac
}

valid_email() {
    if [[ $1 =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; then return 0; fi
    warn 'That does not look like an e-mail address.'
    return 1
}

valid_port() {
    if ! [[ $1 =~ ^[0-9]+$ ]] || [ "$1" -lt 1 ] || [ "$1" -gt 65535 ]; then
        warn 'Use a number from 1 to 65535.'
        return 1
    fi
    if port_in_use "$1"; then
        warn "Port $1 is already in use on this machine. Choose another one."
        return 1
    fi
    return 0
}

valid_url() {
    if [[ $1 =~ ^https?://[^/[:space:]]+$ ]]; then return 0; fi
    warn 'Use a full address without a path, like https://chat.example.com'
    return 1
}

valid_folder() {
    if [ -z "$1" ]; then
        warn 'Enter a folder.'
        return 1
    fi
    return 0
}

#------------------------------------------------------------------------------
# Helpers
#------------------------------------------------------------------------------

port_in_use() {
    if (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; then return 0; fi
    if command -v lsof >/dev/null 2>&1 && lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1; then return 0; fi
    return 1
}

first_free_port() {
    local port=$1
    while port_in_use "$port"; do port=$((port + 1)); done
    printf '%s' "$port"
}

random_password() {
    # 24 letters and digits; head reads a fixed amount, so no broken pipe.
    head -c 64 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | cut -c1-24
}

# A path with the home folder shown as ~, for display.
pretty_path() {
    case $1 in
        "$HOME") printf '~' ;;
        "$HOME"/*) printf '~/%s' "${1#"$HOME"/}" ;;
        *) printf '%s' "$1" ;;
    esac
}

expand_home() {
    case $1 in
        "~") printf '%s' "$HOME" ;;
        "~/"*) printf '%s/%s' "$HOME" "${1#\~/}" ;;
        *) printf '%s' "$1" ;;
    esac
}

version_at_least() {
    # version_at_least HAVE NEED: compares dotted numbers.
    local have=$1 need=$2 h n i
    for i in 1 2 3; do
        h=$(printf '%s' "$have" | cut -d. -f"$i" | tr -dc '0-9')
        n=$(printf '%s' "$need" | cut -d. -f"$i" | tr -dc '0-9')
        h=${h:-0} n=${n:-0}
        if [ "$h" -gt "$n" ]; then return 0; fi
        if [ "$h" -lt "$n" ]; then return 1; fi
    done
    return 0
}

# docker / docker compose, with sudo when this user cannot reach Docker.
docker_cmd() {
    if [ "$USE_SUDO" = 1 ]; then
        sudo --preserve-env=ADMIN_PASSWORD docker "$@"
    else
        docker "$@"
    fi
}

compose() {
    docker_cmd compose --project-directory "$INSTALL_DIR" -f "$INSTALL_DIR/compose.yaml" "$@"
}

# run_step "Message" command...: runs it with a spinner, output to the log.
run_step() {
    local message=$1
    shift
    if [ "$DRY_RUN" = 1 ]; then
        printf '  %s %s %s\n' "$(dim "$SYM_DOT")" "$message" "$(dim '(dry run)')"
        return 0
    fi

    local start=$SECONDS frame=0 status=0 pid
    printf '\n== %s\n' "$message" >>"$LOG"
    "$@" >>"$LOG" 2>&1 &
    pid=$!

    if [ "$FANCY" = 1 ]; then
        printf '%s' "$HIDE_CURSOR"
        while kill -0 "$pid" 2>/dev/null; do
            printf '%s  %s %s %s' "$CLEAR_LINE" "$(accent "${SPINNER[frame % ${#SPINNER[@]}]}")" "$message" "$(dim "$((SECONDS - start))s")"
            frame=$((frame + 1))
            sleep 0.1
        done
        printf '%s' "$SHOW_CURSOR"
    fi

    wait "$pid" || status=$?
    printf '%s' "$CLEAR_LINE"
    if [ "$status" -eq 0 ]; then
        ok "$message $(dim "$((SECONDS - start))s")"
        return 0
    fi

    fail "$message"
    say ''
    tail -n 25 "$LOG" | sed 's/^/    /' >&2
    say ''
    info "Full log: $LOG"
    return "$status"
}

wait_until() {
    # wait_until SECONDS command...: retries every 2 seconds.
    local limit=$1 waited=0
    shift
    until "$@" >/dev/null 2>&1; do
        if [ "$waited" -ge "$limit" ]; then return 1; fi
        sleep 2
        waited=$((waited + 2))
    done
}

app_is_up() {
    curl -fsS -o /dev/null --max-time 5 "http://127.0.0.1:$APP_PORT/up"
}

docker_is_running() {
    docker_cmd info >/dev/null 2>&1
}

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
        die 'Chatvia needs Docker.' 'Install it from https://docs.docker.com/engine/install/ and run this again.'
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
        *) die 'Chatvia needs Docker. Install it and run this again.' ;;
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
    step 1 'Docker'

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
        die "Docker Compose $version is too old: Chatvia needs $MIN_COMPOSE_VERSION or newer." \
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
    if ! docker_cmd image inspect "$(image_ref)" >/dev/null 2>&1; then
        die "The image $(image_ref) is not on this machine." \
            'Build it first (docker build -t NAME:VERSION .), or run without --no-pull.'
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

compose_url() {
    if [ -n "${CHATVIA_COMPOSE_URL:-}" ]; then
        printf '%s' "$CHATVIA_COMPOSE_URL"
    elif [[ $CHATVIA_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        printf '%s/v%s/docker/compose.yaml' "$CHATVIA_REPO_RAW" "$CHATVIA_VERSION"
    else
        printf '%s/main/docker/compose.yaml' "$CHATVIA_REPO_RAW"
    fi
}

download_compose() {
    local target="$WORK_DIR/compose.yaml"
    curl -fsSL "$(compose_url)" -o "$target"
    if ! grep -q '^services:' "$target"; then
        echo "The downloaded file is not a compose file: $(compose_url)" >&2
        return 1
    fi
    if [ -f "$INSTALL_DIR/compose.yaml" ]; then
        cp "$INSTALL_DIR/compose.yaml" "$INSTALL_DIR/compose.yaml.bak"
    fi
    mv "$target" "$INSTALL_DIR/compose.yaml"
}

write_env() {
    local secure='' image_line=''
    [ -n "$CHATVIA_IMAGE" ] && image_line="CHATVIA_IMAGE=$CHATVIA_IMAGE"$'\n'
    case $APP_URL in https://*) secure=$'SESSION_SECURE_COOKIE=true\n' ;; esac
    (
        umask 077
        cat >"$INSTALL_DIR/.env" <<EOF
# Chatvia settings, written by the installer on $(date '+%Y-%m-%d').
# Every setting of the project's .env.example works here. API keys are not
# set here: enter them in the admin panel, under Settings.
# More: $CHATVIA_DOCS#settings

# How the database stores embeddings. Chosen at install: they can NEVER
# change afterwards, or Chatvia stops with an error on the next start.
EMBEDDING_VECTOR_TYPE=$EMBEDDING_TYPE
EMBEDDING_DIMENSIONS=$EMBEDDING_DIMENSIONS

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
    say ''
    if [ "$DRY_RUN" = 0 ]; then
        info "$(bold 'Database setup log:')"
        compose logs --no-color --tail 30 migrate 2>&1 | sed 's/^/    /' >&2 || true
        say ''
    fi
    die "$1" "More: cd $INSTALL_DIR && docker compose logs migrate"
}

check_existing_data() {
    # The compose project name is fixed, so its data volumes are shared by
    # every folder on this machine: a second first install would reuse them.
    local volumes
    volumes=$(docker_cmd volume ls -q --filter "label=com.docker.compose.project=$COMPOSE_PROJECT" 2>/dev/null) || volumes=''
    if [ -n "$volumes" ]; then
        die 'Chatvia data already exists on this computer, probably from an install in another folder.' \
            'Run this installer from that folder (--dir) to upgrade it.' \
            'To start over, delete that data first (this removes all Chatvia data):' \
            "docker volume rm \$(docker volume ls -q --filter label=com.docker.compose.project=$COMPOSE_PROJECT)"
    fi
}

do_install() {
    STEPS=4
    check_docker
    if command -v docker >/dev/null 2>&1; then check_existing_data; fi
    require_local_image

    step 2 'Your Chatvia'
    collect_settings

    step 3 'Check'
    show_summary
    if [ "$INTERACTIVE" = 1 ] && ! confirm 'Install Chatvia with these settings?' y; then
        say ''
        info 'Nothing was changed.'
        exit 0
    fi

    step 4 'Install'
    if [ "$DRY_RUN" = 0 ]; then
        mkdir -p "$INSTALL_DIR"
        LOG="$INSTALL_DIR/install.log"
        : >"$LOG"
    fi
    run_step 'Downloading compose.yaml' download_compose
    if [ "$DRY_RUN" = 0 ]; then
        write_env
        ok "Settings saved $(dim "($(pretty_path "$INSTALL_DIR")/.env)")"
    else
        printf '  %s %s %s\n' "$(dim "$SYM_DOT")" "Saving the settings to $(pretty_path "$INSTALL_DIR")/.env" "$(dim '(dry run)')"
    fi
    get_image 'Downloading Chatvia (a few minutes the first time)'
    start_and_finish
}

# The part of an install after the settings are written; also used to
# finish an install that stopped half way.
start_and_finish() {
    if ! run_step 'Setting up the database and the admin account, then starting' start_chatvia; then
        setup_failed 'Chatvia did not start.'
    fi
    read_setup_result
    remove_setup_container
    if ! run_step 'Waiting for the web app' wait_until 180 app_is_up; then
        say ''
        info "The admin account is ready: $(bold "$ADMIN_EMAIL"), password $(admin_password_text)"
        say ''
        die 'The web app did not answer within 3 minutes.' \
            "See why: cd $(pretty_path "$INSTALL_DIR") && docker compose ps && docker compose logs app" \
            'Then run this installer again to finish.'
    fi
    mark_finished

    show_finish
}

do_resume() {
    STEPS=2
    say ''
    warn "An earlier install in $(bold "$(pretty_path "$INSTALL_DIR")") did not finish."
    if [ "$INTERACTIVE" = 1 ]; then
        choose 'What do you want to do?' \
            'Finish it  — start Chatvia again with the saved settings' \
            'Nothing, exit'
        [ "$CHOICE" = 0 ] || exit 0
    fi

    check_docker
    ADMIN_EMAIL=$(env_value ADMIN_EMAIL)
    APP_PORT=$(env_value APP_PORT)
    APP_URL=$(env_value APP_URL)
    CHATVIA_VERSION=$(env_value CHATVIA_VERSION)
    APP_PORT=${APP_PORT:-8000} APP_URL=${APP_URL:-http://localhost:${APP_PORT:-8000}} CHATVIA_VERSION=${CHATVIA_VERSION:-latest}
    CHATVIA_IMAGE=${CHATVIA_IMAGE:-$(env_value CHATVIA_IMAGE)}

    step 2 'Install'
    # Used only when the earlier attempt did not make the admin account yet.
    ADMIN_PASS=${CHATVIA_ADMIN_PASSWORD:-}
    if [ -z "$ADMIN_PASS" ] && [ "$INTERACTIVE" = 1 ]; then
        choose 'Admin password (used if the account does not exist yet)' \
            'Create a strong one for me  — shown once at the end' \
            'I will type my own'
        [ "$CHOICE" = 1 ] && ask_secret ADMIN_PASS 'Admin password'
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
    get_image 'Downloading Chatvia'
    start_and_finish
}

collect_settings() {
    local default_port
    default_port=${CHATVIA_PORT:-$(first_free_port "$DEFAULT_PORT")}

    if [ "$INTERACTIVE" = 0 ]; then
        ADMIN_EMAIL=${CHATVIA_ADMIN_EMAIL:-}
        [ -n "$ADMIN_EMAIL" ] || die 'Set the admin e-mail: --email you@example.com (or CHATVIA_ADMIN_EMAIL).'
        valid_email "$ADMIN_EMAIL" || exit 1
        ADMIN_PASS=${CHATVIA_ADMIN_PASSWORD:-}
        if [ -z "$ADMIN_PASS" ]; then
            ADMIN_PASS=$(random_password)
            PASSWORD_GENERATED=1
        fi
        APP_PORT=$default_port
        valid_port "$APP_PORT" || exit 1
        APP_URL=${CHATVIA_URL:-http://localhost:$APP_PORT}
        valid_url "$APP_URL" || exit 1
        EMBEDDING_TYPE=${CHATVIA_EMBEDDING_TYPE:-halfvec}
        EMBEDDING_DIMENSIONS=${CHATVIA_EMBEDDING_DIMENSIONS:-1024}
        case $EMBEDDING_TYPE in halfvec | vector) ;; *) die 'CHATVIA_EMBEDDING_TYPE must be halfvec or vector.' ;; esac
        [[ $EMBEDDING_DIMENSIONS =~ ^[0-9]+$ ]] || die 'CHATVIA_EMBEDDING_DIMENSIONS must be a number.'
        ok "Admin $(accent "$ADMIN_EMAIL"), port $(accent "$APP_PORT"), storage $(accent "$EMBEDDING_TYPE($EMBEDDING_DIMENSIONS)")"
        return
    fi

    ask ADMIN_EMAIL 'Admin e-mail' "${CHATVIA_ADMIN_EMAIL:-}" valid_email

    choose 'Admin password' \
        'Create a strong one for me  — shown once at the end' \
        'I will type my own'
    if [ "$CHOICE" = 0 ]; then
        ADMIN_PASS=$(random_password)
        PASSWORD_GENERATED=1
    else
        ask_secret ADMIN_PASS 'Admin password'
    fi

    ask APP_PORT 'Port on this computer' "$default_port" valid_port

    choose 'Where will people open Chatvia?' \
        "On this computer only  — http://localhost:$APP_PORT" \
        'On my own domain or IP address  — your proxy handles HTTPS'
    if [ "$CHOICE" = 0 ]; then
        APP_URL="http://localhost:$APP_PORT"
    else
        ask APP_URL 'Address' "${CHATVIA_URL:-https://chat.example.com}" valid_url
    fi

    choose 'Setup' \
        'Recommended settings  — best for almost everyone' \
        'Custom  — choose how search data is stored'
    if [ "$CHOICE" = 0 ]; then
        EMBEDDING_TYPE=halfvec
        EMBEDDING_DIMENSIONS=1024
        return
    fi

    say ''
    warn "$(bold 'These two settings are permanent.') They decide how the search data"
    info '  of your knowledge is stored and can never be changed after the install.'
    say ''
    choose 'Vector type' \
        'halfvec  — half the space, almost the same search quality (recommended)' \
        'vector  — full precision, twice the space'
    if [ "$CHOICE" = 0 ]; then EMBEDDING_TYPE=halfvec; else EMBEDDING_TYPE=vector; fi

    choose 'Vector size (dimensions)' \
        '1024  — works with every AI provider (recommended)' \
        '512  — smaller and faster, a little less precise (Qwen3, Cohere, Jina)' \
        '768  — (Qwen3, Jina)' \
        '1536  — more detail, more space (Qwen3, Cohere)' \
        '2048  — the most detail, the most space (Qwen3 only)'
    case $CHOICE in
        0) EMBEDDING_DIMENSIONS=1024 ;;
        1) EMBEDDING_DIMENSIONS=512 ;;
        2) EMBEDDING_DIMENSIONS=768 ;;
        3) EMBEDDING_DIMENSIONS=1536 ;;
        *) EMBEDDING_DIMENSIONS=2048 ;;
    esac

    ask CHATVIA_VERSION 'Chatvia version' "$CHATVIA_VERSION"
}

show_summary() {
    local password='the one you typed'
    [ "$PASSWORD_GENERATED" = 1 ] && password='a new random one (shown at the end)'
    rule
    printf '  %-18s %s\n' 'Folder' "$(bold "$(pretty_path "$INSTALL_DIR")")"
    printf '  %-18s %s\n' 'Admin e-mail' "$(bold "$ADMIN_EMAIL")"
    printf '  %-18s %s\n' 'Admin password' "$(bold "$password")"
    printf '  %-18s %s\n' 'Address' "$(bold "$APP_URL")"
    printf '  %-18s %s\n' 'Port' "$(bold "$APP_PORT")"
    printf '  %-18s %s %s\n' 'Search storage' "$(bold "$EMBEDDING_TYPE, $EMBEDDING_DIMENSIONS dimensions")" "$(dim '(permanent)')"
    printf '  %-18s %s\n' 'Image' "$(bold "$(image_ref)")$( [ "$NO_PULL" = 1 ] && dim ' (already on this machine)')"
    rule
}

show_finish() {
    local folder in_dir
    folder=$(pretty_path "$INSTALL_DIR")
    in_dir="cd $folder &&"
    say ''
    rule
    if [ "$DRY_RUN" = 1 ]; then
        printf '  %s %s\n' "$(accent "$SYM_DOT")" "$(bold 'Dry run done. A real install ends like this:')"
    else
        printf '  %s%s%s %s\n' "$C_GREEN" "$SYM_OK" "$C_RESET" "$(bold 'Chatvia is running')"
    fi
    rule
    say ''
    printf '  %-17s %s\n' 'Open the app' "$(accent "$APP_URL")"
    printf '  %-17s %s\n' 'Admin panel' "$(accent "$APP_URL/admin")"
    printf '  %-17s %s\n' 'Admin e-mail' "$(bold "$ADMIN_EMAIL")"
    printf '  %-17s %s\n' 'Admin password' "$(admin_password_text)"
    if [ "$APP_URL" != "http://localhost:$APP_PORT" ]; then
        say ''
        info "$(dim "Point your proxy at http://localhost:$APP_PORT and set TRUSTED_PROXIES in .env (see the docs).")"
    fi
    say ''
    printf '  %s\n' "$(bold 'Next steps')"
    info "1. Sign in to the admin panel and set up two-factor authentication."
    info "2. Settings $SYM_ARROW AI providers: enter the API key of your chat and"
    info "   embedding provider."
    info "3. Add the built-in tools (charts, forms, weather, web search):"
    info "   $(dim "$in_dir docker compose exec app php artisan app:sync-builtin-mcp")"
    say ''
    printf '  %s %s\n' "$(bold 'Everyday commands')" "$(dim "(in $folder)")"
    info "docker compose logs -f     $(dim 'follow the logs')"
    info "docker compose down        $(dim 'stop (your data is kept)')"
    info "docker compose up -d       $(dim 'start again')"
    info "Run this installer again   $(dim 'upgrade')"
    say ''
    info "$(dim "Guide: $CHATVIA_DOCS")"
    say ''
}

#------------------------------------------------------------------------------
# Existing install
#------------------------------------------------------------------------------

env_value() {
    sed -n "s/^$1=//p" "$INSTALL_DIR/.env" 2>/dev/null | tail -n 1
}

do_upgrade() {
    STEPS=2
    say ''
    ok "Chatvia is already installed in $(bold "$(pretty_path "$INSTALL_DIR")")."
    info "$(dim 'The first-install questions only run once. Your data and settings are kept.')"

    if [ "$UPGRADE" = 0 ]; then
        if [ "$INTERACTIVE" = 0 ]; then
            die 'Pass --upgrade to upgrade it without questions.'
        fi
        say ''
        choose 'What do you want to do?' \
            'Upgrade to the newest version  — keeps your data' \
            'Nothing, exit'
        [ "$CHOICE" = 0 ] || exit 0
    fi

    check_docker
    APP_PORT=$(env_value APP_PORT)
    APP_PORT=${APP_PORT:-8000}
    APP_URL=$(env_value APP_URL)
    APP_URL=${APP_URL:-http://localhost:$APP_PORT}
    CHATVIA_VERSION=$(env_value CHATVIA_VERSION)
    CHATVIA_VERSION=${CHATVIA_VERSION:-latest}
    CHATVIA_IMAGE=${CHATVIA_IMAGE:-$(env_value CHATVIA_IMAGE)}

    step 2 'Upgrade'
    if [ "$DRY_RUN" = 0 ]; then
        LOG="$INSTALL_DIR/install.log"
        printf '\n== Upgrade %s\n' "$(date)" >>"$LOG"
    fi
    if [ "$CHATVIA_VERSION" != latest ]; then
        warn "This install stays on version $CHATVIA_VERSION (CHATVIA_VERSION in .env)."
    fi
    run_step 'Updating compose.yaml' download_compose
    get_image 'Downloading the new version'
    if ! run_step 'Updating the database, then restarting' compose up -d; then
        setup_failed 'The upgrade did not finish.'
    fi
    remove_setup_container
    run_step 'Waiting for the web app' wait_until 180 app_is_up ||
        die 'The web app did not answer within 3 minutes.' "See why: cd $INSTALL_DIR && docker compose logs app"

    say ''
    ok "$(bold 'Chatvia is up to date.') Open $(accent "$APP_URL")"
    say ''
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
            --dir) CHATVIA_DIR=${2:?--dir needs a folder}; shift ;;
            --email) CHATVIA_ADMIN_EMAIL=${2:?--email needs an address}; shift ;;
            --port) CHATVIA_PORT=${2:?--port needs a number}; shift ;;
            --url) CHATVIA_URL=${2:?--url needs an address}; shift ;;
            --version) CHATVIA_VERSION=${2:?--version needs a version}; shift ;;
            --image) CHATVIA_IMAGE=${2:?--image needs a name}; shift ;;
            --no-pull) NO_PULL=1 ;;
            --yes | -y) YES=1 ;;
            --upgrade) UPGRADE=1 ;;
            --dry-run) DRY_RUN=1 ;;
            -h | --help) usage; exit 0 ;;
            *) die "Unknown option: $1" 'See --help.' ;;
        esac
        shift
    done
}

cleanup() {
    local status=$?
    printf '%s' "${SHOW_CURSOR:-}"
    [ -n "${WORK_DIR:-}" ] && rm -rf "$WORK_DIR"
    if [ "$status" -ne 0 ] && [ "$status" -ne 130 ]; then
        say ''
    fi
}

interrupted() {
    printf '%s\n\n' "${SHOW_CURSOR:-}"
    info 'Stopped.'
    exit 130
}

main() {
    YES=0 UPGRADE=0 DRY_RUN=0 PASSWORD_GENERATED=0 ADMIN_CREATED=1 USE_SUDO=0 STEPS=4
    ADMIN_EMAIL='' ADMIN_PASS='' APP_PORT='' APP_URL=''
    EMBEDDING_TYPE=halfvec EMBEDDING_DIMENSIONS=1024
    CHATVIA_VERSION=${CHATVIA_VERSION:-latest}
    CHATVIA_IMAGE=${CHATVIA_IMAGE:-}
    NO_PULL=${CHATVIA_NO_PULL:-0}

    setup_output
    parse_args "$@"

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

    case "$(uname -s)" in
        Darwin) OS=macos ;;
        Linux) OS=linux ;;
        *) die "This installer supports Linux and macOS, not $(uname -s)." \
            'On Windows, run it inside WSL 2 (Ubuntu) with Docker Desktop.' ;;
    esac

    banner

    INSTALL_DIR=$(expand_home "${CHATVIA_DIR:-$HOME/chatvia}")
    if [ "$INTERACTIVE" = 1 ] && [ -z "${CHATVIA_DIR:-}" ] && [ ! -f "$INSTALL_DIR/compose.yaml" ]; then
        say ''
        ask INSTALL_DIR 'Install folder' "$INSTALL_DIR" valid_folder
        INSTALL_DIR=$(expand_home "$INSTALL_DIR")
    fi
    case $INSTALL_DIR in /*) ;; *) INSTALL_DIR="$PWD/$INSTALL_DIR" ;; esac

    if [ -f "$INSTALL_DIR/compose.yaml" ] || [ -f "$INSTALL_DIR/.env" ]; then
        # This installer marks .env when it finishes. Its .env without the
        # mark means an attempt stopped half way; any other .env is an
        # install made by hand.
        if grep -q '^# Chatvia settings, written by the installer' "$INSTALL_DIR/.env" 2>/dev/null &&
            ! grep -q '^# Install finished on' "$INSTALL_DIR/.env"; then
            do_resume
        else
            do_upgrade
        fi
        return
    fi

    do_install
}

main "$@"
