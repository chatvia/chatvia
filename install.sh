#!/usr/bin/env bash
#
# Chatvia installer: runs Chatvia on this machine.
#
#   curl -fsSL https://chatvia.ai/install.sh | bash
#
# It installs Chatvia in one of two ways (on Linux it asks which):
#
# - With Docker (the default, and the only way on macOS). It checks Docker
#   (and offers to install it), asks a few questions, writes compose.yaml,
#   .env and the `manage` tool into the install folder, saves PostgreSQL's
#   settings to postgres/postgresql.conf there, starts Chatvia and prints
#   where to sign in. Run it again in the same folder (or ./manage upgrade)
#   to upgrade. .env always names one exact version, like 1.4.2: the
#   installer looks up which release "latest" (or 1.4) is and saves that.
#
# - Complete installation: everything directly on a fresh Ubuntu 26.04
#   server, without Docker. It installs and sets up PostgreSQL with the
#   search extensions, Redis, PHP-FPM, nginx, a Let's Encrypt certificate,
#   Supervisor, cron and the firewall, creates the `chatvia` user, and
#   deploys the newest release with Deployer into /var/www/chatvia. It runs
#   as root (it starts itself again with sudo). Run it again to upgrade, or
#   to finish an install that stopped.
#
# Options (each also works as an environment variable):
#   --docker            install with Docker, without asking  CHATVIA_INSTALL=docker
#   --complete          complete installation, without asking CHATVIA_INSTALL=complete
#   --email EMAIL       first admin's e-mail         CHATVIA_ADMIN_EMAIL
#   --version VERSION   latest, 1.4 or 1.4.2         CHATVIA_VERSION (latest: the newest)
#   --yes               no questions: use the options and the defaults
#   --upgrade           upgrade an existing install without asking
#   --dry-run           show what would happen and change nothing
#   -h, --help          show this help
#
# Only with Docker:
#   --dir PATH          install folder               CHATVIA_DIR (~/chatvia)
#   --port PORT         port on this machine         CHATVIA_PORT (8899, or the next free one)
#   --url URL           address people open          CHATVIA_URL (http://localhost:PORT)
#   --image NAME        image name, without version  CHATVIA_IMAGE (ghcr.io/chatvia/chatvia)
#   --no-pull           use the image already on this machine (a local build)
#   --no-start          upgrade: get the version ready, but start nothing
#
# Only with the complete installation:
#   --domain NAME       domain name or IP address people open   CHATVIA_DOMAIN
#   --no-https          no Let's Encrypt certificate (HTTP only) CHATVIA_HTTPS=no
#   --no-firewall       leave the firewall (ufw) as it is        CHATVIA_FIREWALL=no
#   --force             install on a server that is not fresh    CHATVIA_FORCE=1
#   --version also takes a branch, like main, to install code that is not
#   a release yet.
#
# Only as environment variables:
#   CHATVIA_ADMIN_PASSWORD        the first admin's password (else a random one)
#   CHATVIA_EMBEDDING_TYPE        halfvec (default) or vector
#   CHATVIA_EMBEDDING_DIMENSIONS  1024 (default)
#   CHATVIA_COMPOSE_URL           where to download compose.yaml from
#   CHATVIA_MANAGE_URL            where to download the manage tool from
#   CHATVIA_REPO                  complete: the Git repository to deploy
#   CHATVIA_SWAP                  complete: yes or no to add swap (else: when memory is low)
#   CHATVIA_INSTALLER_URL         complete: where to download this installer to run it with sudo
#   NO_COLOR                      plain output
#
# Everything runs inside main(), called on the last line, so a download that
# stops half way runs nothing.

set -euo pipefail
# For *([0-9;]) and +([ /]): stripping color codes, deleting a word.
shopt -s extglob

readonly CHATVIA_REPO_RAW="https://raw.githubusercontent.com/chatvia/chatvia"
readonly CHATVIA_DOCS="https://chatvia.ai/docs/guide/self-hosting/docker"
readonly COMPOSE_PROJECT="chatvia"
readonly MIN_COMPOSE_VERSION="2.24.0"
readonly DEFAULT_PORT=8899
readonly DEFAULT_IMAGE="ghcr.io/chatvia/chatvia"
readonly MIN_PASSWORD=12
readonly INSTALLER_URL="https://chatvia.ai/install.sh"

# The complete installation. The extension versions are the ones in the
# tensorchord/vchord-suite image that the Docker install uses.
readonly NATIVE_DOCS="https://chatvia.ai/docs/guide/self-hosting/ubuntu"
readonly NATIVE_UBUNTU="26.04"
readonly NATIVE_REPO="https://github.com/chatvia/chatvia.git"
readonly NATIVE_USER="chatvia"
readonly NATIVE_HOME="/home/chatvia"
readonly NATIVE_ROOT="/var/www/chatvia"
readonly NATIVE_DEPLOY_DIR="/home/chatvia/deploy"
readonly NATIVE_CONF="/etc/chatvia/install.conf"
readonly NATIVE_LOG="/var/log/chatvia-install.log"
readonly NATIVE_BACKUPS="/var/backups/chatvia"
readonly NATIVE_PHP="8.5"
readonly NATIVE_PG="18"
readonly VCHORD_VERSION="1.1.1"
readonly VCHORD_BM25_VERSION="0.3.0"
readonly PG_TOKENIZER_VERSION="0.1.1"
readonly DEPLOYER_VERSION="^8.0"
readonly MIN_NODE_VERSION="22.18.0"
# Less memory than this, and no swap: the frontend build may run out.
readonly SWAP_BELOW_KB=3500000

#------------------------------------------------------------------------------
# Output
#
# One line runs down the left and joins every question and step: ◆ is the
# open question, ◇ a finished question or task, ▲ a warning, ■ a failure.
# Everything sits two columns in, under the logo.
#------------------------------------------------------------------------------

setup_output() {
    FANCY=0
    if [ -t 1 ] && [ "${TERM:-dumb}" != dumb ]; then FANCY=1; fi

    if [ "$FANCY" = 1 ] && [ -z "${NO_COLOR:-}" ]; then
        C_RESET=$'\033[0m' C_BOLD=$'\033[1m' C_DIM=$'\033[2m'
        C_ACCENT=$'\033[38;5;99m' C_GRAY=$'\033[90m' C_GREEN=$'\033[32m' C_RED=$'\033[31m' C_YELLOW=$'\033[33m'
        C_BADGE=$'\033[1;38;5;231;48;5;99m'
    else
        C_RESET='' C_BOLD='' C_DIM='' C_ACCENT='' C_GRAY='' C_GREEN='' C_RED='' C_YELLOW='' C_BADGE=''
    fi
    # Questions hide the real cursor and draw their own, even without color.
    if [ "$FANCY" = 1 ]; then
        HIDE_CURSOR=$'\033[?25l' SHOW_CURSOR=$'\033[?25h' C_INVERSE=$'\033[7m' C_NO_INVERSE=$'\033[27m'
    else
        HIDE_CURSOR='' SHOW_CURSOR='' C_INVERSE='' C_NO_INVERSE=''
    fi

    case "${LC_ALL:-${LC_CTYPE:-${LANG:-}}}" in
        *UTF-8* | *utf-8* | *UTF8* | *utf8*)
            S_BAR='│' S_START='┌' S_END='└' S_LINE='─' S_JOIN='├' S_TOP_RIGHT='╮' S_BOTTOM_RIGHT='╯'
            S_ACTIVE='◆' S_DONE='◇' S_WARN='▲' S_FAIL='■' S_INFO='●'
            S_ON='●' S_OFF='○' S_MASK='▪' S_OK='✓' S_ARROW='→' S_UP='↑'
            S_UP_DOWN='↑/↓' S_LEFT_RIGHT='←/→'
            SPINNER=('◒' '◐' '◓' '◑') UNICODE=1
            ;;
        *)
            S_BAR='|' S_START='+' S_END='+' S_LINE='-' S_JOIN='+' S_TOP_RIGHT='+' S_BOTTOM_RIGHT='+'
            S_ACTIVE='*' S_DONE='o' S_WARN='!' S_FAIL='x' S_INFO='*'
            S_ON='>' S_OFF=' ' S_MASK='*' S_OK='+' S_ARROW='->' S_UP='^'
            S_UP_DOWN='up/down' S_LEFT_RIGHT='left/right'
            SPINNER=('|' '/' '-' '\') UNICODE=0
            ;;
    esac

    BAR=$C_GRAY$S_BAR$C_RESET
    START=$C_GRAY$S_START$C_RESET
    END=$C_GRAY$S_END$C_RESET
    ACTIVE=$C_ACCENT$S_ACTIVE$C_RESET
    ACTIVE_BAR=$C_ACCENT$S_BAR$C_RESET
    ACTIVE_END=$C_ACCENT$S_END$C_RESET
    DONE=$C_GREEN$S_DONE$C_RESET
    SKIP=$C_GRAY$S_DONE$C_RESET
    WARN=$C_YELLOW$S_WARN$C_RESET
    WARN_BAR=$C_YELLOW$S_BAR$C_RESET
    WARN_END=$C_YELLOW$S_END$C_RESET
    FAIL=$C_RED$S_FAIL$C_RESET
    INFO=$C_ACCENT$S_INFO$C_RESET
    LAST_BLOCK=other FRAME_ROWS=0 COLS=80
}

bold() { printf '%s%s%s' "$C_BOLD" "$*" "$C_RESET"; }
dim() { printf '%s%s%s' "$C_DIM" "$*" "$C_RESET"; }
accent() { printf '%s%s%s' "$C_ACCENT" "$*" "$C_RESET"; }

# Blocks are parted by an empty stretch of the line; finished tasks in a row
# stack without one.
block() {
    if [ "$1" != task ] || [ "$LAST_BLOCK" != task ]; then
        printf '  %s\n' "$BAR"
    fi
    LAST_BLOCK=$1
}

say() { printf '%s\n' "$*"; }
info() {
    printf '  %s  %s\n' "$BAR" "$*"
    LAST_BLOCK=text
}
ok() {
    block task
    printf '  %s  %s\n' "$DONE" "$*"
}
warn() {
    block warn
    printf '  %s  %s\n' "$WARN" "$*"
}
fail() {
    block fail >&2
    printf '  %s  %s\n' "$FAIL" "$*" >&2
}
# section "Title" [note]: a heading on the line, for the lines under it.
section() {
    block section
    printf '  %s  %s\n' "$INFO" "$C_BOLD$1$C_RESET${2:+  $C_DIM$2$C_RESET}"
}

die() {
    fail "$1"
    shift
    local line
    for line in "$@"; do
        printf '  %s  %s\n' "$BAR" "$line" >&2
    done
    printf '  %s\n\n' "$END" >&2
    exit 1
}

intro() {
    printf '\n  %s  %s\n' "$START" "$C_BADGE $1 $C_RESET"
    LAST_BLOCK=other
}

outro() {
    printf '  %s\n  %s  %s\n\n' "$BAR" "$END" "$*"
}

# Prints stdin on the line, dimmed: log lines.
quote() {
    local line
    while IFS= read -r line; do
        printf '  %s  %s\n' "$BAR" "$C_DIM$line$C_RESET"
    done
}

# repeat_to VAR TEXT COUNT
repeat_to() {
    local out='' i
    for ((i = 0; i < $3; i++)); do out=$out$2; done
    printf -v "$1" '%s' "$out"
}

# duration_to VAR SECONDS: 42s, or 3m 05s.
duration_to() {
    if [ "$2" -lt 60 ]; then
        printf -v "$1" '%ds' "$2"
    else
        printf -v "$1" '%dm %02ds' $(($2 / 60)) $(($2 % 60))
    fi
}

# kv_to VAR LABEL VALUE: a "label  value" row for a box.
kv_to() {
    printf -v "$1" '%s%-16s%s%s' "$C_DIM" "$2" "$C_RESET" "$3"
}

# COLS: the terminal's width, to know when a line wraps.
terminal_width() {
    local size
    size=$(stty size 2>/dev/null </dev/tty) || size=''
    COLS=${size#* }
    case $COLS in '' | *[!0-9]* | 0) COLS=80 ;; esac
}

# note "Title" LINE...: a box on the line, for the settings and the result.
# Too wide for the terminal, it prints the same lines without the box.
note() {
    local title=$1
    shift
    local width=0 line plain fill
    for line in "$@"; do
        plain=${line//$'\033'\[*([0-9;])m/}
        if [ "${#plain}" -gt "$width" ]; then width=${#plain}; fi
    done
    plain=${title//$'\033'\[*([0-9;])m/}
    if [ "$((${#plain} + 2))" -gt "$width" ]; then width=$((${#plain} + 2)); fi
    width=$((width + 2))

    terminal_width
    block note
    if [ "$((width + 6))" -gt "$COLS" ]; then
        printf '  %s  %s\n' "$DONE" "$title"
        for line in "$@"; do printf '  %s  %s\n' "$BAR" "$line"; done
        return
    fi
    repeat_to fill "$S_LINE" $((width - ${#plain} - 1))
    printf '  %s  %s %s\n' "$DONE" "$title" "$C_GRAY$fill$S_TOP_RIGHT$C_RESET"
    for line in '' "$@" ''; do
        plain=${line//$'\033'\[*([0-9;])m/}
        repeat_to fill ' ' $((width - ${#plain}))
        printf '  %s  %s%s%s\n' "$BAR" "$line" "$fill" "$BAR"
    done
    repeat_to fill "$S_LINE" $((width + 2))
    printf '  %s\n' "$C_GRAY$S_JOIN$fill$S_BOTTOM_RIGHT$C_RESET"
}

# The app's logo (resources/js/components/AppLogoIcon.vue) in half blocks,
# two square pixels per character, with the name beside the dot. It keeps the
# terminal's text color, as the logo follows the theme in the app.
banner() {
    local name tagline
    name=$(bold 'Chatvia')
    tagline=$(dim 'AI agents on your own server')
    say ''
    if [ "$UNICODE" = 1 ]; then
        printf '  %s\n' '    ▄▄██████▄▄' '  ▄████████████' ' ▄███▀      ▀██'
        printf '  %s    %s\n' ' ████           ▄██▄' "$name"
        printf '  %s    %s\n' ' ████           ▀██▀' "$tagline"
        printf '  %s\n' ' ▀███▄      ▄██' '  █████████████' ' ██████████▀▀▀' '██▀▀'
    else
        printf '  %s\n' "$name" "$tagline"
    fi
}

#------------------------------------------------------------------------------
# Questions (read from the terminal: stdin is this script when piped)
#
# An open question is drawn again after every key, over its last version.
# Once answered it shrinks to the question and the answer.
#------------------------------------------------------------------------------

# While a question is open, keys arrive one by one and are not echoed.
keys_on() {
    stty -echo -icanon min 1 time 0 2>/dev/null </dev/tty || true
    printf '%s' "$HIDE_CURSOR"
}

keys_off() {
    if [ -n "${TTY_STATE:-}" ]; then stty "$TTY_STATE" 2>/dev/null </dev/tty || true; fi
    printf '%s' "${SHOW_CURSOR:-}"
}

# The terminal closed (for example Ctrl-D): stop instead of asking forever.
no_input() {
    keys_off
    printf '\n'
    die 'Stopped: no answer.' 'Nothing was changed.'
}

# Reads one key into KEY: enter, up, down, left, right, tab, backspace,
# clear (Ctrl-U), word (Ctrl-W), eof (Ctrl-D), other, or char with the
# character in CHAR.
read_key() {
    local key rest=''
    CHAR=''
    IFS= read -rsn1 key </dev/tty || no_input
    case $key in
        '') KEY=enter ;;
        $'\033')
            IFS= read -rsn2 -t 1 rest </dev/tty || true
            case $rest in
                '[A' | OA) KEY=up ;;
                '[B' | OB) KEY=down ;;
                '[C' | OC) KEY=right ;;
                '[D' | OD) KEY=left ;;
                *) KEY=other ;;
            esac
            ;;
        $'\177' | $'\010') KEY=backspace ;;
        $'\t') KEY=tab ;;
        $'\025') KEY=clear ;;
        $'\027') KEY=word ;;
        $'\004') KEY=eof ;;
        *)
            if [[ $key == [[:print:]] ]]; then KEY=char CHAR=$key; else KEY=other; fi
            ;;
    esac
}

# draw LINE...: shows the open question over its last version. A line wider
# than the terminal wraps, so it counts as more rows.
draw() {
    local line plain rows=0
    if [ "$FRAME_ROWS" -gt 0 ]; then printf '\033[%dA' "$FRAME_ROWS"; fi
    printf '\r\033[J'
    for line in "$@"; do
        printf '%s\n' "$line"
        plain=${line//$'\033'\[*([0-9;])m/}
        rows=$((rows + 1 + (${#plain} > 0 ? (${#plain} - 1) / COLS : 0)))
    done
    FRAME_ROWS=$rows
}

# answer_drawn "Question" "Answer": the closed question.
answer_drawn() {
    draw "  $DONE  $1" "  $BAR  $C_DIM$2$C_RESET"
    FRAME_ROWS=0
    LAST_BLOCK=answer
    keys_off
}

# ask VAR "Question" [--default TEXT] [--placeholder TEXT] [--hint TEXT]
#     [--check FN] [--status FN] [--complete FN]
#   --default      the answer when Enter is pressed on an empty line
#   --placeholder  an example, shown greyed out; not an answer
#   --check FN     runs on Enter; returns 1 with the reason in INVALID
#   --status FN    runs after every key; puts a short note in STATUS
#   --complete FN  runs on Tab; puts the result in COMPLETED, the other
#                  matches in CHOICES
ask() {
    local var=$1 label=$2
    shift 2
    local default='' placeholder='' hint='' check='' status='' complete=''
    while [ $# -ge 2 ]; do
        case $1 in
            --default) default=$2 ;;
            --placeholder) placeholder=$2 ;;
            --hint) hint=$2 ;;
            --check) check=$2 ;;
            --status) status=$2 ;;
            --complete) complete=$2 ;;
        esac
        shift 2
    done

    local value='' answer='' error='' shown input help sym rail foot
    local lines
    block question

    if [ "$FANCY" = 0 ]; then
        printf '  %s  %s\n' "$ACTIVE" "$label"
        if [ -n "$hint" ]; then printf '  %s  %s\n' "$BAR" "$hint"; fi
        while true; do
            printf '  %s  %s' "$BAR" "${default:+($default) }"
            IFS= read -r answer </dev/tty || no_input
            answer=${answer:-$default}
            INVALID='Type an answer first.'
            if [ -n "$answer" ] && { [ -z "$check" ] || "$check" "$answer"; }; then break; fi
            printf '  %s  %s\n' "$WARN" "$INVALID"
        done
        printf -v "$var" '%s' "$answer"
        return
    fi

    terminal_width
    keys_on
    CHOICES=''
    while true; do
        STATUS=''
        if [ -n "$status" ]; then "$status" "${value:-$default}" || true; fi

        shown=${default:-$placeholder}
        if [ -n "$value" ]; then
            input="$value$C_INVERSE $C_NO_INVERSE"
        elif [ -n "$shown" ]; then
            input="$C_INVERSE${shown:0:1}$C_NO_INVERSE$C_DIM${shown:1}$C_RESET"
        else
            input="$C_INVERSE $C_NO_INVERSE"
        fi
        if [ -n "$STATUS" ]; then input="$input  $STATUS"; fi

        if [ -n "$error" ]; then
            sym=$WARN rail=$WARN_BAR foot="$WARN_END  $C_YELLOW$error$C_RESET"
        else
            help=''
            if [ -z "$value" ] && [ -n "$default" ]; then
                help='Enter to use it, Tab to change it'
            elif [ -n "$value" ] && [ -n "$complete" ]; then
                help='Tab to complete'
            fi
            sym=$ACTIVE rail=$ACTIVE_BAR foot="$ACTIVE_END${help:+  $C_DIM$help$C_RESET}"
        fi

        lines=("  $sym  $C_BOLD$label$C_RESET")
        if [ -n "$hint" ]; then lines+=("  $rail  $C_DIM$hint$C_RESET"); fi
        lines+=("  $rail  $input")
        if [ -n "$CHOICES" ]; then lines+=("  $rail  $C_DIM$CHOICES$C_RESET"); fi
        lines+=("  $foot")
        draw "${lines[@]}"

        read_key
        CHOICES=''
        case $KEY in
            enter)
                answer=${value:-$default}
                INVALID=''
                if [ -z "$answer" ]; then
                    error='Type an answer first.'
                elif [ -z "$check" ] || "$check" "$answer"; then
                    break
                else
                    error=$INVALID
                fi
                ;;
            char) value=$value$CHAR error='' ;;
            backspace) value=${value%?} error='' ;;
            clear) value='' error='' ;;
            word)
                value=${value%%+([ /])}
                value=${value%"${value##*[ /]}"}
                error=''
                ;;
            tab)
                if [ -z "$value" ] && [ -n "$default" ]; then
                    value=$default
                elif [ -n "$complete" ]; then
                    "$complete" "$value" || true
                    value=$COMPLETED
                fi
                error=''
                ;;
            eof) if [ -z "$value" ]; then no_input; fi ;;
        esac
    done

    answer_drawn "$label" "$answer"
    printf -v "$var" '%s' "$answer"
}

# ask_secret VAR "Question": a hidden password, typed twice.
ask_secret() {
    local var=$1 label=$2 first='' second='' again=0 error='' sym rail foot mask count
    local lines
    block question

    if [ "$FANCY" = 0 ]; then
        printf '  %s  %s\n' "$ACTIVE" "$label"
        while true; do
            printf '  %s  At least %d characters (hidden): ' "$BAR" "$MIN_PASSWORD"
            IFS= read -rs first </dev/tty || no_input
            printf '\n'
            if [ "${#first}" -lt "$MIN_PASSWORD" ]; then
                printf '  %s  Use at least %d characters.\n' "$WARN" "$MIN_PASSWORD"
                continue
            fi
            printf '  %s  Type it again: ' "$BAR"
            IFS= read -rs second </dev/tty || no_input
            printf '\n'
            if [ "$first" = "$second" ]; then break; fi
            printf '  %s  %s\n' "$WARN" 'The two passwords are different. Try again.'
        done
        printf -v "$var" '%s' "$first"
        return
    fi

    terminal_width
    keys_on
    while true; do
        if [ -n "$error" ]; then
            sym=$WARN rail=$WARN_BAR foot="$WARN_END  $C_YELLOW$error$C_RESET"
        else
            sym=$ACTIVE rail=$ACTIVE_BAR foot="$ACTIVE_END  ${C_DIM}Enter to continue$C_RESET"
        fi
        if [ "${#first}" -ge "$MIN_PASSWORD" ]; then
            count="$C_GREEN$S_OK$C_RESET"
        else
            count="$C_DIM${#first}/$MIN_PASSWORD$C_RESET"
        fi

        lines=("  $sym  $C_BOLD$label$C_RESET" "  $rail  ${C_DIM}At least $MIN_PASSWORD characters, typed twice.$C_RESET")
        repeat_to mask "$S_MASK" "${#first}"
        if [ "$again" = 0 ]; then
            lines+=("  $rail  $mask$C_INVERSE $C_NO_INVERSE  $count")
        else
            lines+=("  $rail  $mask  $count")
            repeat_to mask "$S_MASK" "${#second}"
            lines+=("  $rail  $mask$C_INVERSE $C_NO_INVERSE  ${C_DIM}again$C_RESET")
        fi
        lines+=("  $foot")
        draw "${lines[@]}"

        read_key
        case $KEY in
            char)
                if [ "$again" = 0 ]; then first=$first$CHAR; else second=$second$CHAR; fi
                error=''
                ;;
            backspace)
                if [ "$again" = 0 ]; then
                    first=${first%?}
                elif [ -z "$second" ]; then
                    again=0
                else
                    second=${second%?}
                fi
                error=''
                ;;
            clear) if [ "$again" = 0 ]; then first=''; else second=''; fi ;;
            enter)
                if [ "$again" = 0 ]; then
                    if [ "${#first}" -lt "$MIN_PASSWORD" ]; then
                        error="Use at least $MIN_PASSWORD characters."
                    else
                        again=1 error=''
                    fi
                elif [ "$first" = "$second" ]; then
                    break
                else
                    error='The two passwords are different. Type them again.'
                    first='' second='' again=0
                fi
                ;;
            eof) no_input ;;
        esac
    done

    answer_drawn "$label" 'set (hidden)'
    printf -v "$var" '%s' "$first"
}

# choose "Question" "Option  — note" ...: arrow keys (or j/k, or a number)
# and Enter. Sets CHOICE to the index of the picked option. The note is
# shown next to the selected option only.
choose() {
    local label=$1
    shift
    local count=$# selected=0 i name note lines
    local options=("$@")
    block question

    if [ "$FANCY" = 0 ]; then
        printf '  %s  %s\n' "$ACTIVE" "$label"
        for ((i = 0; i < count; i++)); do printf '  %s  %d) %s\n' "$BAR" "$((i + 1))" "${options[i]}"; done
        while true; do
            printf '  %s  Number (1-%d, Enter for 1): ' "$BAR" "$count"
            IFS= read -r name </dev/tty || no_input
            name=${name:-1}
            if [[ $name =~ ^[0-9]+$ ]] && [ "$name" -ge 1 ] && [ "$name" -le "$count" ]; then
                CHOICE=$((name - 1))
                return
            fi
        done
    fi

    terminal_width
    keys_on
    while true; do
        lines=("  $ACTIVE  $C_BOLD$label$C_RESET")
        for ((i = 0; i < count; i++)); do
            name=${options[i]%%  —*}
            note=''
            if [[ ${options[i]} == *'  — '* ]]; then note=${options[i]#*  — }; fi
            if [ "$i" -eq "$selected" ]; then
                lines+=("  $ACTIVE_BAR  $C_GREEN$S_ON$C_RESET $name${note:+  $C_DIM$note$C_RESET}")
            else
                lines+=("  $ACTIVE_BAR  $C_DIM$S_OFF $name$C_RESET")
            fi
        done
        lines+=("  $ACTIVE_END  ${C_DIM}$S_UP_DOWN to move, Enter to choose$C_RESET")
        draw "${lines[@]}"

        read_key
        case $KEY in
            up | left) selected=$(((selected + count - 1) % count)) ;;
            down | right | tab) selected=$(((selected + 1) % count)) ;;
            enter) break ;;
            char)
                case $CHAR in
                    k) selected=$(((selected + count - 1) % count)) ;;
                    j) selected=$(((selected + 1) % count)) ;;
                    [1-9]) if [ "$CHAR" -le "$count" ]; then selected=$((CHAR - 1)); fi ;;
                esac
                ;;
            eof) no_input ;;
        esac
    done

    answer_drawn "$label" "${options[selected]%%  —*}"
    CHOICE=$selected
}

# confirm "Question" [default y|n]: returns 0 for yes. Arrow keys and Enter,
# or y / n.
confirm() {
    local label=$1 yes=1 key options
    if [ "${2:-y}" = n ]; then yes=0; fi
    block question

    if [ "$FANCY" = 0 ]; then
        if [ "$yes" = 1 ]; then options='Y/n'; else options='y/N'; fi
        printf '  %s  %s (%s) ' "$ACTIVE" "$label" "$options"
        IFS= read -r key </dev/tty || no_input
        case $key in
            y | Y | yes | YES | Yes) return 0 ;;
            n | N | no | NO | No) return 1 ;;
            *) [ "$yes" = 1 ] ;;
        esac
        return
    fi

    terminal_width
    keys_on
    while true; do
        if [ "$yes" = 1 ]; then
            options="$C_GREEN$S_ON$C_RESET Yes $C_DIM/ $S_OFF No$C_RESET"
        else
            options="$C_DIM$S_OFF Yes /$C_RESET $C_GREEN$S_ON$C_RESET No"
        fi
        draw "  $ACTIVE  $C_BOLD$label$C_RESET" "  $ACTIVE_BAR  $options" \
            "  $ACTIVE_END  ${C_DIM}$S_LEFT_RIGHT or y/n, Enter to choose$C_RESET"

        read_key
        case $KEY in
            left | right | up | down | tab) yes=$((1 - yes)) ;;
            enter) break ;;
            char)
                case $CHAR in
                    y | Y) yes=1 && break ;;
                    n | N) yes=0 && break ;;
                    h) yes=1 ;;
                    l) yes=0 ;;
                esac
                ;;
            eof) no_input ;;
        esac
    done

    if [ "$yes" = 1 ]; then answer_drawn "$label" 'Yes'; else answer_drawn "$label" 'No'; fi
    [ "$yes" = 1 ]
}

#------------------------------------------------------------------------------
# Answer checks: return 1 with the reason in INVALID
#------------------------------------------------------------------------------

valid_email() {
    if [[ $1 =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; then return 0; fi
    INVALID='That does not look like an e-mail address.'
    return 1
}

valid_port() {
    if ! [[ $1 =~ ^[0-9]{1,5}$ ]] || [ "$1" -lt 1 ] || [ "$1" -gt 65535 ]; then
        INVALID='Use a number from 1 to 65535.'
        return 1
    fi
    if port_in_use "$1"; then
        INVALID="Port $1 is already in use on this machine. Choose another one."
        return 1
    fi
    return 0
}

valid_url() {
    if [[ $1 =~ ^https?://[^/[:space:]]+$ ]]; then return 0; fi
    INVALID='Use a full address without a path, like https://chat.example.com'
    return 1
}

# What a folder holds, in STATE: new, empty, used (other files), installed
# (Chatvia, by this installer or by hand), unfinished (an install that
# stopped half way), foreign (another project's compose.yaml or .env), file.
folder_state() {
    local dir=$1
    if [ -e "$dir" ] && [ ! -d "$dir" ]; then
        STATE=file
    elif [ -f "$dir/.env" ] && grep -q '^# Chatvia settings, written by the installer' "$dir/.env" 2>/dev/null; then
        # This installer marks .env when it finishes.
        if grep -q '^# Install finished on' "$dir/.env"; then STATE=installed; else STATE=unfinished; fi
    elif [ -f "$dir/compose.yaml" ] && grep -q 'chatvia/chatvia' "$dir/compose.yaml" 2>/dev/null; then
        STATE=installed
    elif [ -f "$dir/compose.yaml" ] || [ -f "$dir/.env" ]; then
        STATE=foreign
    elif [ -d "$dir" ] && [ -n "$(ls -A "$dir" 2>/dev/null)" ]; then
        STATE=used
    elif [ -d "$dir" ]; then
        STATE=empty
    else
        STATE=new
    fi
}

check_folder() {
    local dir probe
    dir=$(resolve_dir "$1")
    folder_state "$dir"
    case $STATE in
        file)
            INVALID='That is a file. Choose a folder.'
            return 1
            ;;
        foreign)
            INVALID="Another project's compose.yaml or .env is in this folder. Choose another one."
            return 1
            ;;
    esac
    probe=$dir
    while [ ! -e "$probe" ]; do probe=$(dirname "$probe"); done
    if [ ! -w "$probe" ]; then
        INVALID="You cannot write to $(pretty_path "$probe"). Choose another folder."
        return 1
    fi
    return 0
}

# The note next to the folder while it is typed.
folder_status() {
    folder_state "$(resolve_dir "$1")"
    case $STATE in
        new) STATUS="${C_DIM}new folder$C_RESET" ;;
        empty) STATUS="${C_DIM}empty folder$C_RESET" ;;
        used) STATUS="${C_DIM}has other files$C_RESET" ;;
        installed) STATUS="$C_ACCENT$S_ARROW Chatvia is here: upgrade it$C_RESET" ;;
        unfinished) STATUS="$C_ACCENT$S_ARROW unfinished install: finish it$C_RESET" ;;
        foreign) STATUS="${C_YELLOW}another project is here$C_RESET" ;;
        file) STATUS="${C_YELLOW}this is a file$C_RESET" ;;
    esac
}

# Tab in the folder question: completes the folder name, like a shell.
complete_folder() {
    local typed=$1 base m common name count
    local matches=()
    COMPLETED=$typed
    base=$(expand_home "$typed")
    for m in "$base"*/; do
        if [ -d "$m" ]; then matches+=("$m"); fi
    done
    count=${#matches[@]}
    [ "$count" -gt 0 ] || return 0

    common=${matches[0]}
    for m in "${matches[@]}"; do
        while [ -n "$common" ] && [ "${m#"$common"}" = "$m" ]; do common=${common%?}; done
    done
    if [ "$count" -gt 1 ]; then
        for m in "${matches[@]:0:6}"; do
            name=${m%/}
            CHOICES="$CHOICES${name##*/}/  "
        done
        if [ "$count" -gt 6 ]; then CHOICES="$CHOICES+$((count - 6)) more"; fi
    fi
    case $typed in
        "~" | "~/"*) COMPLETED="~${common#"$HOME"}" ;;
        *) COMPLETED=$common ;;
    esac
}

# The note next to the port while it is typed. Only the quick check: the
# full one runs on Enter.
port_status() {
    if [[ $1 =~ ^[0-9]{1,5}$ ]] && [ "$1" -ge 1 ] && [ "$1" -le 65535 ]; then
        if (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; then
            STATUS="${C_YELLOW}in use$C_RESET"
        else
            STATUS="$C_GREEN$S_OK free$C_RESET"
        fi
    fi
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

# A typed folder as a full path: ~ expanded, relative to here, no trailing /.
resolve_dir() {
    local dir
    dir=$(expand_home "$1")
    case $dir in
        /*) ;;
        . | ./) dir=$PWD ;;
        *) dir="$PWD/${dir#./}" ;;
    esac
    while [ "${#dir}" -gt 1 ] && [ "${dir%/}" != "$dir" ]; do dir=${dir%/}; done
    printf '%s' "$dir"
}

# A release like 1.4.2 (or 1.5.0-rc.1), not a moving tag like latest or 1.4.
is_exact_version() {
    [[ $1 =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]]
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

# With compose.override.yaml (the PostgreSQL settings file, and the owner's
# own changes) when it is there, like a plain `docker compose` in the folder.
compose() {
    local files=(-f "$INSTALL_DIR/compose.yaml")
    if [ -f "$INSTALL_DIR/compose.override.yaml" ]; then files+=(-f "$INSTALL_DIR/compose.override.yaml"); fi
    docker_cmd compose --project-directory "$INSTALL_DIR" "${files[@]}" "$@"
}

# run_step "Message" command...: runs it with a spinner; its output goes to
# the log, and the end of the log shows when it fails.
run_step() {
    local message=$1
    shift
    block task
    if [ "$DRY_RUN" = 1 ]; then
        printf '  %s  %s %s\n' "$SKIP" "$message" "$(dim '(dry run)')"
        return 0
    fi

    local start=$SECONDS frame=0 status=0 pid took
    printf '\n== %s\n' "$message" >>"$LOG"
    "$@" >>"$LOG" 2>&1 &
    pid=$!

    if [ "$FANCY" = 1 ]; then
        printf '%s' "$HIDE_CURSOR"
        while kill -0 "$pid" 2>/dev/null; do
            duration_to took $((SECONDS - start))
            printf '\r\033[2K  %s  %s  %s' "$C_ACCENT${SPINNER[frame % ${#SPINNER[@]}]}$C_RESET" "$message" "$C_DIM$took$C_RESET"
            frame=$((frame + 1))
            sleep 0.1
        done
        printf '\r\033[2K%s' "$SHOW_CURSOR"
    else
        printf '  %s  %s\n' "$ACTIVE" "$message"
    fi

    wait "$pid" || status=$?
    duration_to took $((SECONDS - start))
    if [ "$status" -eq 0 ]; then
        printf '  %s  %s  %s\n' "$DONE" "$message" "$C_DIM$took$C_RESET"
        return 0
    fi

    printf '  %s  %s\n' "$FAIL" "$message" >&2
    printf '  %s\n' "$BAR" >&2
    tail -n 25 "$LOG" | quote >&2
    printf '  %s\n' "$BAR" >&2
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
    local ref
    ref=$(image_ref)
    if ! docker_cmd image inspect "$ref" >/dev/null 2>&1; then
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
    run_step "Downloading $what of Chatvia" docker_cmd pull "$ref" ||
        die "Could not download $ref." 'Check the version and the internet connection.'
    version=$(image_version "$ref") ||
        die "The image $ref does not say which version it is." 'Name the version instead, like --version 1.4.2.'
    CHATVIA_VERSION=$version
    APP_PULLED=1
    ok "Chatvia $(bold "$version")"
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
        die 'Chatvia data already exists on this computer, probably from an install in another folder.' \
            'Run this installer from that folder (--dir) to upgrade it.' \
            'To start over, delete that data first (this removes all Chatvia data):' \
            "docker volume rm \$(docker volume ls -q --filter label=com.docker.compose.project=$COMPOSE_PROJECT)"
    fi
}

do_install() {
    check_docker
    if command -v docker >/dev/null 2>&1; then check_existing_data; fi
    require_local_image

    collect_settings

    show_summary
    if [ "$INTERACTIVE" = 1 ] && ! confirm 'Install Chatvia with these settings?' y; then
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
        get_image 'Downloading Chatvia (a few minutes the first time)'
    fi
    start_and_finish
}

# The part of an install after the settings are written; also used to
# finish an install that stopped half way.
start_and_finish() {
    export_postgres_settings
    if ! run_step 'Setting up the database and the admin account, then starting' start_chatvia; then
        setup_failed 'Chatvia did not start.'
    fi
    read_setup_result
    remove_setup_container
    if ! run_step 'Waiting for the web app' wait_until 180 app_is_up; then
        info "The admin account is ready: $(bold "$ADMIN_EMAIL"), password $(admin_password_text)"
        die 'The web app did not answer within 3 minutes.' \
            "See why: cd $(pretty_path "$INSTALL_DIR") && ./manage status && ./manage logs app" \
            'Then run this installer again to finish.'
    fi
    mark_finished

    show_finish
}

do_resume() {
    warn "An earlier install in $(bold "$(pretty_path "$INSTALL_DIR")") did not finish."
    if [ "$INTERACTIVE" = 1 ]; then
        choose 'What do you want to do?' \
            'Finish it  — start Chatvia again with the saved settings' \
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
    get_image 'Downloading Chatvia'
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
        case $EMBEDDING_TYPE in halfvec | vector) ;; *) die 'CHATVIA_EMBEDDING_TYPE must be halfvec or vector.' ;; esac
        [[ $EMBEDDING_DIMENSIONS =~ ^[0-9]+$ ]] || die 'CHATVIA_EMBEDDING_DIMENSIONS must be a number.'
        ok "Admin $(accent "$ADMIN_EMAIL"), port $(accent "$APP_PORT"), storage $(accent "$EMBEDDING_TYPE($EMBEDDING_DIMENSIONS)")"
        return
    fi

    ask_admin

    ask APP_PORT 'Port on this computer' --default "$default_port" \
        --check valid_port --status port_status

    choose 'Where will people open Chatvia?' \
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
    choose 'Setup' \
        'Recommended settings  — best for almost everyone' \
        'Custom  — choose how search data is stored, and the version'
    if [ "$CHOICE" = 0 ]; then
        EMBEDDING_TYPE=halfvec
        EMBEDDING_DIMENSIONS=1024
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
        '512  — smaller and faster, a little less precise (Qwen3, OpenAI, Gemini, Cohere, Jina)' \
        '768  — Qwen3, OpenAI, Gemini, Jina' \
        '1536  — more detail, more space (Qwen3, OpenAI, Gemini, Cohere)' \
        '2048  — the most detail, the most space (Qwen3, Gemini, OpenAI large)'
    case $CHOICE in
        0) EMBEDDING_DIMENSIONS=1024 ;;
        1) EMBEDDING_DIMENSIONS=512 ;;
        2) EMBEDDING_DIMENSIONS=768 ;;
        3) EMBEDDING_DIMENSIONS=1536 ;;
        *) EMBEDDING_DIMENSIONS=2048 ;;
    esac

    local hint='latest, or a version number like 1.2.0'
    if [ "$MODE" = complete ]; then hint="$hint, or a branch like main (not a release)"; fi
    ask CHATVIA_VERSION 'Chatvia version' --default "$CHATVIA_VERSION" --hint "$hint"
}

show_summary() {
    local password='the one you typed' image folder email address port storage image_row
    if [ "$PASSWORD_GENERATED" = 1 ]; then password='a new random one, shown at the end'; fi
    image=$(image_ref)
    if [ "$NO_PULL" = 1 ]; then image="$image $(dim '(on this machine)')"; fi

    kv_to folder 'Folder' "$(pretty_path "$INSTALL_DIR")"
    kv_to email 'Admin e-mail' "$ADMIN_EMAIL"
    kv_to password 'Admin password' "$password"
    kv_to address 'Address' "$APP_URL"
    kv_to port 'Port' "$APP_PORT"
    kv_to storage 'Search storage' "$EMBEDDING_TYPE, $EMBEDDING_DIMENSIONS dimensions $(dim '(permanent)')"
    kv_to image_row 'Image' "$image"
    note 'Check your settings' "$folder" "$email" "$password" "$address" "$port" "$storage" "$image_row"
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

show_finish() {
    local folder title open admin email password saved='' n=1
    folder=$(pretty_path "$INSTALL_DIR")
    if [ "$DRY_RUN" = 1 ]; then
        title='Dry run done. A real install ends like this:'
    else
        title=$(bold 'Chatvia is running')
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

    offer_browser
    outro "$(dim "Guide: $CHATVIA_DOCS")"
}

#------------------------------------------------------------------------------
# Existing install
#------------------------------------------------------------------------------

env_value() {
    sed -n "s/^$1=//p" "$INSTALL_DIR/.env" 2>/dev/null | tail -n 1
}

# env_with KEY VALUE: .env with the line KEY= changed (or added), on stdout.
env_with() {
    file_with "$INSTALL_DIR/.env" "$1" "$2"
}

# file_with FILE KEY VALUE: FILE with the line KEY= changed (or added), on
# stdout.
file_with() {
    awk -v key="$2" -v value="$3" '
        index($0, key "=") == 1 { if (!done) print key "=" value; done = 1; next }
        { print }
        END { if (!done) print key "=" value }
    ' "$1"
}

# set_env_value KEY VALUE: changes the line in .env, or adds it.
set_env_value() {
    local tmp
    tmp=$(mktemp "$INSTALL_DIR/.env.XXXXXX") || return
    env_with "$1" "$2" >"$tmp" && chmod 600 "$tmp" && mv "$tmp" "$INSTALL_DIR/.env"
}

# The images of the new compose.yaml (still in the work folder) for the new
# version (a copy of .env with it), so a failed download changes nothing.
pull_new_images() {
    local files=(-f "$WORK_DIR/compose.yaml")
    if [ -f "$INSTALL_DIR/compose.override.yaml" ]; then files+=(-f "$INSTALL_DIR/compose.override.yaml"); fi
    (umask 077 && env_with CHATVIA_VERSION "$CHATVIA_VERSION" >"$WORK_DIR/.env") || return
    docker_cmd compose --project-directory "$INSTALL_DIR" --env-file "$WORK_DIR/.env" "${files[@]}" pull
}

# Everything is downloaded: the new files go in place, and .env names the
# new version.
switch_version() {
    place_files && set_env_value CHATVIA_VERSION "$CHATVIA_VERSION"
}

do_upgrade() {
    ok "Chatvia is installed in $(bold "$(pretty_path "$INSTALL_DIR")")"
    info "$(dim 'The first-install questions only run once. Your data and settings are kept.')"

    if [ "$UPGRADE" = 0 ]; then
        if [ "$INTERACTIVE" = 0 ]; then
            die 'Pass --upgrade to upgrade it without questions.'
        fi
        choose 'What do you want to do?' \
            'Upgrade to the newest version  — keeps your data' \
            'Nothing, exit'
        if [ "$CHOICE" != 0 ]; then
            outro 'Nothing was changed.'
            exit 0
        fi
    fi

    check_docker
    APP_PORT=$(env_value APP_PORT)
    APP_PORT=${APP_PORT:-8000}
    APP_URL=$(env_value APP_URL)
    APP_URL=${APP_URL:-http://localhost:$APP_PORT}
    local installed
    installed=$(env_value CHATVIA_VERSION)
    # Without --version: the newest release. A local build (--no-pull) keeps
    # its tag, as there is nothing to download.
    if [ "$VERSION_GIVEN" = 0 ]; then
        CHATVIA_VERSION=latest
        if [ "$NO_PULL" = 1 ]; then CHATVIA_VERSION=${installed:-latest}; fi
    fi
    CHATVIA_IMAGE=${CHATVIA_IMAGE:-$(env_value CHATVIA_IMAGE)}

    if [ "$DRY_RUN" = 0 ]; then
        LOG="$INSTALL_DIR/install.log"
        printf '\n== Upgrade %s\n' "$(date)" >>"$LOG"
    fi
    resolve_version
    # An older version would run on a database that newer migrations have
    # changed. ./manage rollback goes back: it gets the older version ready
    # with --no-start, then restores the backup from before the upgrade.
    if [ "$NO_START" = 0 ] && is_exact_version "$installed" && is_exact_version "$CHATVIA_VERSION" &&
        ! version_at_least "$CHATVIA_VERSION" "$installed"; then
        die "Version $CHATVIA_VERSION is older than the one installed here ($installed)." \
            'Going back needs the data from before the upgrade: ./manage rollback'
    fi
    if [ -n "$installed" ] && [ "$installed" != "$CHATVIA_VERSION" ]; then
        info "Version $installed $S_ARROW $(bold "$CHATVIA_VERSION")"
    fi

    run_step 'Downloading compose.yaml and the manage tool' fetch_files ||
        die 'The upgrade stopped. Nothing was changed.'
    if [ "$NO_PULL" = 0 ]; then
        run_step 'Downloading the new images' pull_new_images ||
            die 'The upgrade stopped. Nothing was changed.'
    else
        ok "Using the images on this machine $(dim "($(image_ref))")"
    fi
    run_step "Switching to version $CHATVIA_VERSION" switch_version ||
        die 'Could not save the new files in the install folder.' "See why: $LOG"
    export_postgres_settings

    if [ "$NO_START" = 1 ]; then
        outro "Version $CHATVIA_VERSION is ready. Nothing was restarted."
        return
    fi
    if ! run_step 'Updating the database, then restarting' compose up -d --remove-orphans; then
        setup_failed 'The upgrade did not finish.'
    fi
    remove_setup_container
    run_step 'Waiting for the web app' wait_until 180 app_is_up ||
        die 'The web app did not answer within 3 minutes.' "See why: cd $(pretty_path "$INSTALL_DIR") && ./manage logs app"

    ok "$(bold "Chatvia $CHATVIA_VERSION is running.")"
    outro "Open $(accent "$APP_URL")"
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
            --version) CHATVIA_VERSION=${2:?--version needs a version} VERSION_GIVEN=1; shift ;;
            --image) CHATVIA_IMAGE=${2:?--image needs a name}; shift ;;
            --no-pull) NO_PULL=1 ;;
            --yes | -y) YES=1 ;;
            --upgrade) UPGRADE=1 ;;
            --no-start) NO_START=1 ;;
            --dry-run) DRY_RUN=1 ;;
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
    EMBEDDING_TYPE=halfvec EMBEDDING_DIMENSIONS=1024
    VERSION_GIVEN=0
    if [ -n "${CHATVIA_VERSION:-}" ]; then VERSION_GIVEN=1; fi
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

    banner
    intro 'Setup'
    if [ "$DRY_RUN" = 1 ]; then info "$(accent 'Dry run: nothing will be changed.')"; fi

    INSTALL_DIR=${CHATVIA_DIR:-$HOME/chatvia}
    if [ "$INTERACTIVE" = 1 ] && [ -z "${CHATVIA_DIR:-}" ]; then
        ask INSTALL_DIR 'Install folder' --default "$(pretty_path "$INSTALL_DIR")" \
            --hint 'Holds compose.yaml, .env and the manage tool. Docker keeps your data.' \
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
