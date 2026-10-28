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
# - Complete installation: everything directly on a fresh Ubuntu server
#   (24.04 LTS or newer), without Docker. It installs and sets up
#   PostgreSQL with the search extensions, Redis, PHP-FPM, nginx, a Let's
#   Encrypt certificate, Supervisor, cron and the firewall, creates the
#   `chatvia` user, and
#   deploys the newest release with Deployer into /var/www/chatvia. Unless
#   you turn it off, chatvia-auto-update then deploys each newer release of
#   that major version by itself, once a night, after a database backup. It
#   runs as root (it starts itself again with sudo). Run it again to upgrade,
#   or to finish an install that stopped.
#
#   On a server that already runs PostgreSQL (or with --db-host), it uses
#   that PostgreSQL instead: it asks for the connection details, checks that
#   the server has pgvector, pg_textsearch and pg_partman, and stops when
#   one is missing. It never installs PostgreSQL or changes its settings.
#
# Options (each also works as an environment variable):
#   --docker            install with Docker, without asking  CHATVIA_INSTALL=docker
#   --complete          complete installation, without asking CHATVIA_INSTALL=complete
#   --email EMAIL       first admin's e-mail         CHATVIA_ADMIN_EMAIL
#   --version VERSION   latest, 1.4 or 1.4.2         CHATVIA_VERSION (latest: the newest)
#   --yes               no questions: use the options and the defaults
#   --upgrade           upgrade an existing install without asking
#   --dry-run           show what would happen and change nothing
#   --count-install     send the anonymous install count  CHATVIA_COUNT_INSTALL=yes
#   --no-count-install  do not send it, without asking    CHATVIA_COUNT_INSTALL=no
#   --news-email EMAIL  get news about ChatVia.ai there   CHATVIA_NEWS_EMAIL
#   --no-news-email     do not ask for one                CHATVIA_NEWS_EMAIL=no
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
#   --no-auto-update    no automatic updates (once a night)      CHATVIA_AUTO_UPDATE=no
#   --db-host HOST      use this PostgreSQL server, not a new one CHATVIA_DB_HOST
#   --db-port PORT      its port                                 CHATVIA_DB_PORT (5432)
#   --db-name NAME      the database                             CHATVIA_DB_DATABASE (chatvia)
#   --db-user NAME      the database user                        CHATVIA_DB_USERNAME (chatvia)
#   --version also takes a branch, like main, to install code that is not
#   a release yet.
#
# Only as environment variables:
#   CHATVIA_ADMIN_PASSWORD        the first admin's password (else a random one)
#   CHATVIA_DB_PASSWORD           complete: the password of the existing database user
#   CHATVIA_EMBEDDING_TYPE        halfvec (default) or vector
#   CHATVIA_EMBEDDING_DIMENSIONS  1024 (default)
#   CHATVIA_EMBEDDING_INDEX       binary (default) or full
#   CHATVIA_COMPOSE_URL           where to download compose.yaml from
#   CHATVIA_MANAGE_URL            where to download the manage tool from
#   CHATVIA_REPO                  complete: the Git repository to deploy
#   CHATVIA_SWAP                  complete: yes or no to add swap (else: when memory is low)
#   CHATVIA_INSTALLER_URL         complete: where to download this installer to run it with sudo
#   CHATVIA_COUNT_URL             where to send the install count
#   CHATVIA_NEWS_URL              where to send the e-mail for news
#   NO_COLOR                      plain output
#
# Everything runs inside main(), called on the last line, so a download that
# stops half way runs nothing.
#
# install.sh is built from the files in installer/src by installer/build:
# change those, then run installer/build. It stays one file to download.

set -euo pipefail
# For *([0-9;]) and +([ /]): stripping color codes, deleting a word.
shopt -s extglob

readonly CHATVIA_REPO_RAW="https://raw.githubusercontent.com/chatvia/chatvia"
readonly CHATVIA_DOCS="https://chatvia.ai/docs/guide/installation/docker"
readonly COMPOSE_PROJECT="chatvia"
readonly MIN_COMPOSE_VERSION="2.24.0"
readonly DEFAULT_PORT=8899
readonly DEFAULT_IMAGE="ghcr.io/chatvia/chatvia"
readonly MIN_PASSWORD=12
readonly INSTALLER_URL="https://chatvia.ai/install.sh"
readonly INSTALL_COUNT_URL="https://chatvia.ai/api/installs"
readonly NEWS_EMAIL_URL="https://chatvia.ai/api/subscribers"

# The complete installation.
readonly NATIVE_DOCS="https://chatvia.ai/docs/guide/installation/ubuntu"
# The oldest Ubuntu release the complete installation runs on.
readonly NATIVE_UBUNTU="24.04"
readonly NATIVE_REPO="https://github.com/chatvia/chatvia.git"
readonly NATIVE_USER="chatvia"
readonly NATIVE_HOME="/home/chatvia"
readonly NATIVE_ROOT="/var/www/chatvia"
readonly NATIVE_DEPLOY_DIR="/home/chatvia/deploy"
readonly NATIVE_CONF="/etc/chatvia/install.conf"
readonly NATIVE_LOG="/var/log/chatvia-install.log"
readonly NATIVE_BACKUPS="/var/backups/chatvia"
readonly NATIVE_AUTO_UPDATE="/usr/local/bin/chatvia-auto-update"
readonly NATIVE_AUTO_UPDATE_CRON="/etc/cron.d/chatvia-auto-update"
readonly NATIVE_AUTO_UPDATE_LOG="/var/log/chatvia-auto-update.log"
readonly NATIVE_PHP="8.5"
# PHP comes from Ondřej Surý's PPA (ppa:ondrej/php), which carries each PHP
# release soon after it is out; Ubuntu's own php8.5 keeps the version the
# Ubuntu release shipped with. NATIVE_MIN_PHP matches composer.json.
readonly NATIVE_MIN_PHP="8.5.12"
readonly PHP_PPA_URL="https://ppa.launchpadcontent.net/ondrej/php/ubuntu"
readonly PHP_PPA_KEY="B8DC7E53946656EFBCE4C1DD71DAEAAB4AD4CAB6"
readonly NATIVE_PG="18"
# Ubuntu releases older than 26.04 have no PostgreSQL 18 of their own: it
# comes from the PostgreSQL archive there.
readonly PGDG_URL="https://apt.postgresql.org/pub/repos/apt"
readonly PGDG_KEY_URL="https://www.postgresql.org/media/keys/ACCC4CF8.asc"
# An existing PostgreSQL server must be this version or newer: the one the
# complete installation sets up, and the oldest pg_textsearch builds for.
readonly NATIVE_MIN_PG="18"
# The extensions Chatvia's migrations create in the app's database, and the
# one in the analytics database (in its own schema, partman).
readonly APP_EXTENSIONS="pg_stat_statements pg_trgm btree_gin btree_gist vector pg_textsearch"
readonly PG_TEXTSEARCH_RELEASES="https://github.com/timescale/pg_textsearch/releases"
readonly DEPLOYER_VERSION="^8.0"
# Ubuntu's Composer package when it is this version or newer; otherwise
# (24.04 has 2.7, older than PHP 8.4) the current one from getcomposer.org.
readonly MIN_COMPOSER_VERSION="2.8.0"
readonly MIN_NODE_VERSION="22.18.0"
# Less memory than this (a 4 GB server), and no swap: the frontend build
# (up to 3 GB) may run out next to the running app.
readonly SWAP_BELOW_KB=6000000

# --- installer/src/10-ui.sh ---
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
            S_TOP_LEFT='╭' S_BOTTOM_LEFT='╰'
            S_ACTIVE='◆' S_DONE='◇' S_WARN='▲' S_FAIL='■' S_INFO='●'
            S_ON='●' S_OFF='○' S_MASK='▪' S_OK='✓' S_ARROW='→' S_UP='↑' S_MORE='…'
            S_UP_DOWN='↑/↓' S_LEFT_RIGHT='←/→'
            SPINNER=('◒' '◐' '◓' '◑') UNICODE=1
            ;;
        *)
            S_BAR='|' S_START='+' S_END='+' S_LINE='-' S_JOIN='+' S_TOP_RIGHT='+' S_BOTTOM_RIGHT='+'
            S_TOP_LEFT='+' S_BOTTOM_LEFT='+'
            S_ACTIVE='*' S_DONE='o' S_WARN='!' S_FAIL='x' S_INFO='*'
            S_ON='>' S_OFF=' ' S_MASK='*' S_OK='+' S_ARROW='->' S_UP='^' S_MORE='...'
            S_UP_DOWN='up/down' S_LEFT_RIGHT='left/right'
            # shellcheck disable=SC1003 # the last spinner frame is a backslash
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
#
# The website, the license and the author sit beside the logo's foot.
banner() {
    local name tagline site author
    name=$(bold 'ChatVia.ai')
    tagline=$(dim 'AI agents on your own server')
    author=$(dim 'Created and maintained by Ahmad Mayahi')
    say ''
    if [ "$UNICODE" = 1 ]; then
        site="$(accent 'https://chatvia.ai')$(dim ' · ChatVia.ai License')"
        printf '  %s\n' '    ▄▄██████▄▄' '  ▄████████████' ' ▄███▀      ▀██'
        printf '  %s    %s\n' ' ████           ▄██▄' "$name"
        printf '  %s    %s\n' ' ████           ▀██▀' "$tagline"
        printf '  %s\n' ' ▀███▄      ▄██'
        printf '  %s    %s\n' '  █████████████     ' "$site"
        printf '  %s    %s\n' ' ██████████▀▀▀      ' "$author"
        printf '  %s    %s\n' '██▀▀                ' "$(dim 'https://mayahi.net')"
    else
        site="$(accent 'https://chatvia.ai')$(dim ' - ChatVia.ai License')"
        printf '  %s\n' "$name" "$tagline" "$site" "$author $(dim '(https://mayahi.net)')"
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

# input_room NOTE: how many columns the text in an input box may take, next
# to NOTE, before the box grows. The box is 48 columns wide inside, or less
# in a narrow terminal. Sets ROOM.
input_room() {
    local plain=${1//$'\033'\[*([0-9;])m/}
    ROOM=$((COLS - 9))
    if [ "$ROOM" -gt 48 ]; then ROOM=48; fi
    if [ -n "$plain" ]; then ROOM=$((ROOM - 2 - ${#plain})); fi
}

# input_box TEXT NOTE COLOR: the field you type in, as three lines in BOX: a
# box with rounded corners in COLOR, TEXT (with the cursor) in it, and NOTE
# at its right end.
input_box() {
    local text=$1 note=$2 color=$3 plain extra=0 fill line
    input_room "$note"
    plain=${text//$'\033'\[*([0-9;])m/}
    if [ "${#plain}" -gt "$ROOM" ]; then ROOM=${#plain}; fi
    repeat_to fill ' ' $((ROOM - ${#plain}))
    plain=${note//$'\033'\[*([0-9;])m/}
    if [ -n "$plain" ]; then extra=$((2 + ${#plain})); fi
    repeat_to line "$S_LINE" $((ROOM + extra + 2))
    BOX=(
        "$color$S_TOP_LEFT$line$S_TOP_RIGHT$C_RESET"
        "$color$S_BAR$C_RESET $text$fill${note:+  $note} $color$S_BAR$C_RESET"
        "$color$S_BOTTOM_LEFT$line$S_BOTTOM_RIGHT$C_RESET"
    )
}

# ask VAR "Question" [--default TEXT] [--placeholder TEXT] [--hint TEXT]
#     [--check FN] [--status FN] [--complete FN] [--optional LABEL]
#   --default      the answer when Enter is pressed on an empty line
#   --placeholder  an example, shown greyed out; not an answer
#   --check FN     runs on Enter; returns 1 with the reason in INVALID
#   --status FN    runs after every key; puts a short note in STATUS
#   --complete FN  runs on Tab; puts the result in COMPLETED, the other
#                  matches in CHOICES
#   --optional     Enter on an empty line skips the question: VAR is empty
#                  and LABEL is shown as the answer
ask() {
    local var=$1 label=$2
    shift 2
    local default='' placeholder='' hint='' check='' status='' complete='' optional=''
    while [ $# -ge 2 ]; do
        case $1 in
            --default) default=$2 ;;
            --placeholder) placeholder=$2 ;;
            --hint) hint=$2 ;;
            --check) check=$2 ;;
            --status) status=$2 ;;
            --complete) complete=$2 ;;
            --optional) optional=$2 ;;
        esac
        shift 2
    done

    local value='' answer='' error='' input help sym rail border foot
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
            if [ -z "$answer" ] && [ -n "$optional" ]; then break; fi
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

        # The default is a real answer, so it is shown like typed text; a
        # placeholder is only an example, so it is greyed out. A long answer
        # shows its end, where you type.
        input_room "$STATUS"
        if [ -n "$value" ] && [ "${#value}" -ge "$ROOM" ] && [ "$ROOM" -gt 10 ]; then
            input="$C_DIM$S_MORE$C_RESET${value:$((${#value} - ROOM + 1 + ${#S_MORE}))}$C_INVERSE $C_NO_INVERSE"
        elif [ -n "$value" ]; then
            input="$value$C_INVERSE $C_NO_INVERSE"
        elif [ -n "$default" ]; then
            input="$C_INVERSE${default:0:1}$C_NO_INVERSE${default:1}"
        elif [ -n "$placeholder" ]; then
            input="$C_INVERSE${placeholder:0:1}$C_NO_INVERSE$C_DIM${placeholder:1}$C_RESET"
        else
            input="$C_INVERSE $C_NO_INVERSE"
        fi

        if [ -n "$error" ]; then
            sym=$WARN rail=$WARN_BAR border=$C_YELLOW foot="$WARN_END  $C_YELLOW$error$C_RESET"
        else
            help=''
            if [ -z "$value" ] && [ -n "$default" ]; then
                help='Enter to use it, Tab to change it'
            elif [ -n "$value" ] && [ -n "$complete" ]; then
                help='Tab to complete'
            elif [ -z "$value" ] && [ -n "$optional" ]; then
                help='Enter to skip'
            fi
            sym=$ACTIVE rail=$ACTIVE_BAR border=$C_ACCENT foot="$ACTIVE_END${help:+  $C_DIM$help$C_RESET}"
        fi

        lines=("  $sym  $C_BOLD$label$C_RESET")
        if [ -n "$hint" ]; then lines+=("  $rail  $C_DIM$hint$C_RESET"); fi
        input_box "$input" "$STATUS" "$border"
        lines+=("  $rail  ${BOX[0]}" "  $rail  ${BOX[1]}" "  $rail  ${BOX[2]}")
        if [ -n "$CHOICES" ]; then lines+=("  $rail  $C_DIM$CHOICES$C_RESET"); fi
        lines+=("  $foot")
        draw "${lines[@]}"

        read_key
        CHOICES=''
        case $KEY in
            enter)
                answer=${value:-$default}
                INVALID=''
                if [ -z "$answer" ] && [ -n "$optional" ]; then
                    break
                elif [ -z "$answer" ]; then
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

    answer_drawn "$label" "${answer:-$optional}"
    printf -v "$var" '%s' "$answer"
}

# ask_secret VAR "Question" [once]: a new hidden password, typed twice. With
# "once": a password that exists already (a database user's), typed once,
# of any length.
ask_secret() {
    local var=$1 label=$2 once=${3:-} first='' second='' again=0 error='' sym rail border foot mask count again_note
    local lines min=$MIN_PASSWORD help="At least $MIN_PASSWORD characters, typed twice."
    local short="Use at least $MIN_PASSWORD characters."
    if [ "$once" = once ]; then min=1 help='Hidden as you type.' short='Type the password first.'; fi
    block question

    if [ "$FANCY" = 0 ]; then
        printf '  %s  %s\n' "$ACTIVE" "$label"
        while true; do
            if [ "$once" = once ]; then
                printf '  %s  (hidden): ' "$BAR"
            else
                printf '  %s  At least %d characters (hidden): ' "$BAR" "$min"
            fi
            IFS= read -rs first </dev/tty || no_input
            printf '\n'
            if [ "${#first}" -lt "$min" ]; then
                printf '  %s  %s\n' "$WARN" "$short"
                continue
            fi
            if [ "$once" = once ]; then break; fi
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
            sym=$WARN rail=$WARN_BAR border=$C_YELLOW foot="$WARN_END  $C_YELLOW$error$C_RESET"
        else
            sym=$ACTIVE rail=$ACTIVE_BAR border=$C_ACCENT foot="$ACTIVE_END  ${C_DIM}Enter to continue$C_RESET"
        fi
        if [ "$once" = once ]; then
            count=''
        elif [ "${#first}" -ge "$min" ]; then
            count="$C_GREEN$S_OK$C_RESET"
        else
            count="$C_DIM${#first}/$min$C_RESET"
        fi

        # One box per password; the first turns grey once it is typed. A long
        # password fills its box, and no more.
        lines=("  $sym  $C_BOLD$label$C_RESET" "  $rail  $C_DIM$help$C_RESET")
        input_room "$count"
        repeat_to mask "$S_MASK" $((${#first} < ROOM ? ${#first} : ROOM - 1))
        if [ "$again" = 0 ]; then
            input_box "$mask$C_INVERSE $C_NO_INVERSE" "$count" "$border"
        else
            input_box "$mask" "$count" "$C_GRAY"
        fi
        lines+=("  $rail  ${BOX[0]}" "  $rail  ${BOX[1]}" "  $rail  ${BOX[2]}")
        if [ "$again" = 1 ]; then
            again_note="${C_DIM}again$C_RESET"
            input_room "$again_note"
            repeat_to mask "$S_MASK" $((${#second} < ROOM ? ${#second} : ROOM - 1))
            input_box "$mask$C_INVERSE $C_NO_INVERSE" "$again_note" "$border"
            lines+=("  $rail  ${BOX[0]}" "  $rail  ${BOX[1]}" "  $rail  ${BOX[2]}")
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
                    if [ "${#first}" -lt "$min" ]; then
                        error=$short
                    elif [ "$once" = once ]; then
                        break
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

# --- installer/src/20-helpers.sh ---
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
        # shellcheck disable=SC2209 # the state "file", not the command
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
        installed) STATUS="$C_ACCENT$S_ARROW ChatVia.ai is here: upgrade it$C_RESET" ;;
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
    # shellcheck disable=SC2088 # the typed ~ stays a ~
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
# shellcheck disable=SC2088 # a literal ~ is the point
pretty_path() {
    case $1 in
        "$HOME") printf '~' ;;
        "$HOME"/*) printf '~/%s' "${1#"$HOME"/}" ;;
        *) printf '%s' "$1" ;;
    esac
}

# shellcheck disable=SC2088 # matches a typed ~, which the shell did not expand
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

# --- installer/src/30-docker.sh ---
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

# --- installer/src/31-docker-upgrade.sh ---
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
    ok "ChatVia.ai is installed in $(bold "$(pretty_path "$INSTALL_DIR")")"
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

    ok "$(bold "ChatVia.ai $CHATVIA_VERSION is running.")"
    outro "Open $(accent "$APP_URL")"
}

# --- installer/src/40-complete.sh ---
#------------------------------------------------------------------------------
# Complete installation: everything directly on this server, without Docker
#
# Ubuntu 24.04 LTS or newer on a fresh server. It does what the guides
# "Install on Ubuntu" and "Deploy with Deployer" describe: PostgreSQL 18
# with the search extensions, Redis, PHP-FPM behind nginx, a Let's Encrypt
# certificate, Horizon, Reverb and Pulse under Supervisor, the scheduler in
# cron, and the firewall. One system user, chatvia, owns the code and runs every part of
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
    if [ "$OS" != linux ] || [ "$id" != ubuntu ] || [ -z "$version" ] ||
        ! version_at_least "$version" "$NATIVE_UBUNTU"; then
        if [ "$DRY_RUN" = 1 ]; then
            warn "This is $name, not Ubuntu $NATIVE_UBUNTU or newer: a real install stops here. $(dim '(dry run)')"
            return
        fi
        die "The complete installation needs Ubuntu $NATIVE_UBUNTU LTS or newer. This is $name." \
            "Use a fresh Ubuntu $NATIVE_UBUNTU (or newer) server, or install with Docker (--docker)."
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
            warn "ppa:ondrej/php has no packages for Ubuntu $version yet: a real install stops here. $(dim '(dry run)')"
            return
        fi
        die "ChatVia.ai needs PHP $NATIVE_MIN_PHP or newer, from ppa:ondrej/php. It has no packages for Ubuntu $version ($codename) yet, or this server cannot reach it." \
            "Install with Docker (--docker) for now, or run this installer again once the PPA carries Ubuntu $version."
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
    run_step "Looking for PostgreSQL $DB_MAJOR packages" native_add_postgres_repo || native_failed
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

# --- installer/src/41-complete-database.sh ---
#------------------------------------------------------------------------------
# An existing PostgreSQL server
#
# A server that runs PostgreSQL already (or --db-host, for one on another
# server) keeps it: the installer asks how to reach it, and checks that it
# can hold Chatvia before anything changes. It never installs PostgreSQL or
# changes its settings. It creates Chatvia's databases and extensions there
# when the database user may; what the user may not do is printed as SQL for
# a superuser to run, and the install stops until that is done.
#------------------------------------------------------------------------------

# DATABASE=existing for --db-host, a PostgreSQL installed on this server, or
# one that listens on its port (in a container, say).
native_detect_database() {
    DATABASE=local
    if [ -n "${CHATVIA_DB_HOST:-}" ] || [ -n "$(ls -A /etc/postgresql 2>/dev/null)" ]; then
        DATABASE=existing
    elif command -v ss >/dev/null 2>&1 && [ -n "$(ss -Hltn '( sport = :5432 )' 2>/dev/null)" ]; then
        DATABASE=existing
    fi
}

# The port of this server's PostgreSQL: its first running cluster's.
local_pg_port() {
    local port=''
    if command -v pg_lsclusters >/dev/null 2>&1; then
        port=$(pg_lsclusters -h 2>/dev/null | awk '$4 == "online" { print $3; exit }') || port=''
    fi
    printf '%s' "${port:-5432}"
}

valid_db_host() {
    if [[ $1 =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]] || [[ $1 =~ ^[0-9A-Fa-f:]*:[0-9A-Fa-f:]*$ ]]; then
        return 0
    fi
    INVALID='Type a host name or an IP address, like 127.0.0.1 or db.example.com.'
    return 1
}

valid_db_port() {
    if [[ $1 =~ ^[0-9]{1,5}$ ]] && [ "$1" -ge 1 ] && [ "$1" -le 65535 ]; then return 0; fi
    INVALID='Use a number from 1 to 65535.'
    return 1
}

# At most 49 characters, so the name with _analytics still fits in 63.
valid_db_name() {
    if [[ $1 =~ ^[a-z_][a-z0-9_]{0,48}$ ]]; then return 0; fi
    INVALID='Use lowercase letters, digits and _ (at most 49), starting with a letter or _.'
    return 1
}

valid_db_user() {
    if [[ $1 =~ ^[A-Za-z0-9_][A-Za-z0-9_.@-]{0,62}$ ]]; then return 0; fi
    INVALID='Use letters, digits, _, ., @ and - only.'
    return 1
}

# .env holds the password in single quotes, which cannot hold one itself.
valid_db_password() {
    if [[ $1 == *"'"* ]] || [[ $1 == *$'\n'* ]]; then
        INVALID="The password has a ' in it, which .env cannot hold. Give the user another password."
        return 1
    fi
    return 0
}

# The analytics, Pulse and Spectra databases are named after the app's, so
# they do not meet another app's databases on a shared server.
native_name_databases() {
    DB_ANALYTICS="${DB_NAME}_analytics"
    DB_PULSE="${DB_NAME}_pulse"
    DB_SPECTRA="${DB_NAME}_spectra"
}

# db_psql DATABASE ARGS...: psql on the existing server, as the database
# user. The password goes through the environment, never the command line.
db_psql() {
    local database=$1
    shift
    PGPASSWORD=$DB_PASS PGCONNECT_TIMEOUT=10 PGAPPNAME=chatvia-installer \
        psql -X -q -v ON_ERROR_STOP=1 -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$database" "$@"
}

# db_dump DATABASE: a pg_dump of it, in PostgreSQL's custom format, on stdout.
db_dump() {
    PGPASSWORD=$DB_PASS PGCONNECT_TIMEOUT=10 \
        pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -Fc "$1"
}

# The password saved in the shared .env, without its quotes.
native_db_password() {
    local value
    value=$(env_value DB_PASSWORD)
    value=${value#\'}
    printf '%s' "${value%\'}"
}

native_install_pg_client() {
    apt_get update
    apt_get install postgresql-client
}

native_database_settings() {
    DB_HOST=${CHATVIA_DB_HOST:-127.0.0.1}
    DB_PORT=${CHATVIA_DB_PORT:-}
    if [ -z "$DB_PORT" ]; then
        DB_PORT=5432
        if [ -z "${CHATVIA_DB_HOST:-}" ]; then DB_PORT=$(local_pg_port); fi
    fi
    DB_NAME=${CHATVIA_DB_DATABASE:-chatvia}
    DB_USER=${CHATVIA_DB_USERNAME:-chatvia}
    DB_PASS=${CHATVIA_DB_PASSWORD:-}

    if [ "$INTERACTIVE" = 0 ]; then
        valid_db_host "$DB_HOST" || die "--db-host: $INVALID"
        valid_db_port "$DB_PORT" || die "--db-port: $INVALID"
        valid_db_name "$DB_NAME" || die "--db-name: $INVALID"
        valid_db_user "$DB_USER" || die "--db-user: $INVALID"
        [ -n "$DB_PASS" ] || die "Set the password of the database user $DB_USER: CHATVIA_DB_PASSWORD."
        valid_db_password "$DB_PASS" || die "$INVALID"
        native_name_databases
        native_check_database || die "Could not connect to PostgreSQL at $DB_HOST:$DB_PORT as $DB_USER." "$DB_ERROR"
        return
    fi

    section 'Your PostgreSQL server'
    if [ -n "${CHATVIA_DB_HOST:-}" ]; then
        info "ChatVia.ai uses the PostgreSQL server at $(bold "$DB_HOST"). It installs no PostgreSQL of its own."
    else
        info 'PostgreSQL runs on this server already, so ChatVia.ai uses it. It installs'
        info 'no PostgreSQL of its own and leaves its settings as they are.'
    fi
    info "It needs PostgreSQL $NATIVE_MIN_PG or newer with pgvector, pg_textsearch and pg_partman."
    while true; do
        ask DB_HOST 'PostgreSQL host' --default "$DB_HOST" \
            --hint '127.0.0.1 for the PostgreSQL on this server.' --check valid_db_host
        ask DB_PORT 'PostgreSQL port' --default "$DB_PORT" --check valid_db_port
        ask DB_NAME 'Database name' --default "$DB_NAME" --check valid_db_name \
            --hint 'ChatVia.ai also uses three more next to it: NAME_analytics, NAME_pulse and NAME_spectra.'
        ask DB_USER 'Database user' --default "$DB_USER" --check valid_db_user \
            --hint 'A user that exists already. The databases are created for it when it may create databases.'
        while true; do
            ask_secret DB_PASS "Password of $DB_USER" once
            valid_db_password "$DB_PASS" && break
            warn "$INVALID"
        done
        native_name_databases
        native_check_database && return
        warn "Could not connect to PostgreSQL at $DB_HOST:$DB_PORT as $DB_USER."
        printf '%s\n' "$DB_ERROR" | quote
        choose 'What do you want to do?' \
            'Type the details again' \
            'Stop for now  — nothing was changed'
        if [ "$CHOICE" != 0 ]; then
            outro 'Nothing was changed.'
            exit 0
        fi
    done
}

# Connects to the existing server and checks, without changing anything,
# that it can hold Chatvia: its version, the extensions on it, pg_textsearch
# loaded when it starts, and that the user may create what is missing. A
# connection that fails returns 1, with psql's message in DB_ERROR. Anything
# else that is wrong stops the install, with what to do about it.
native_check_database() {
    local facts version_num version super createdb connect_to='' database
    local preload='' available='' trusted='' listed='' problems=() sql_dbs=() sql=() packages=() created=()
    DB_ERROR=''

    if ! command -v psql >/dev/null 2>&1; then
        if [ "$DRY_RUN" = 1 ]; then
            warn "psql is not installed, so the database is not checked. $(dim '(dry run)')"
            return 0
        fi
        run_step 'Installing the PostgreSQL client (psql) to check the database' native_install_pg_client ||
            die 'The PostgreSQL client could not be installed.' "See why: $LOG"
    fi

    # The app's database, or, while it does not exist, a database that is
    # always there, to read the rest.
    connect_to=$(native_db_reachable 2>&1) || {
        DB_ERROR=$connect_to
        return 1
    }

    facts=$(db_psql "$connect_to" -At -F '|' -c \
        "SELECT current_setting('server_version_num'), current_setting('server_version'), rolsuper, rolcreatedb
         FROM pg_roles WHERE rolname = current_user") || {
        DB_ERROR='Could not read the server version and the rights of the user.'
        return 1
    }
    IFS='|' read -r version_num version super createdb <<<"$facts"
    version=${version%% *}
    DB_MAJOR=$((version_num / 10000))
    if [ "$DB_MAJOR" -lt "$NATIVE_MIN_PG" ]; then
        die "The PostgreSQL server at $DB_HOST:$DB_PORT is version $version. ChatVia.ai needs PostgreSQL $NATIVE_MIN_PG or newer." \
            'Upgrade it, or use another PostgreSQL server. Nothing was changed.'
    fi
    ok "PostgreSQL $version at $DB_HOST:$DB_PORT $(dim "(user $DB_USER)")"

    # Which extensions the server has, and which of them a database owner
    # may create without being a superuser (trusted).
    available=$(db_psql "$connect_to" -At -F '|' -c \
        "SELECT e.name, v.trusted FROM pg_available_extensions e
         JOIN pg_available_extension_versions v ON v.name = e.name AND v.version = e.default_version") || available=''
    local extension
    for extension in $APP_EXTENSIONS pg_partman; do
        if ! grep -q "^$extension|" <<<"$available"; then
            case $extension in
                vector) packages+=("pgvector  $(dim "(Ubuntu: postgresql-$DB_MAJOR-pgvector)")") ;;
                pg_partman) packages+=("pg_partman  $(dim "(Ubuntu: postgresql-$DB_MAJOR-partman)")") ;;
                pg_textsearch) packages+=("pg_textsearch  $(dim "($PG_TEXTSEARCH_RELEASES)")") ;;
                *) packages+=("$extension  $(dim '(comes with PostgreSQL itself)')") ;;
            esac
        elif grep -q "^$extension|t$" <<<"$available"; then
            trusted="$trusted $extension"
        fi
    done
    if [ "${#packages[@]}" -gt 0 ]; then
        problems+=('These extensions are not installed on the PostgreSQL server:')
        for extension in "${packages[@]}"; do problems+=("  - $extension"); done
    fi

    # Only a superuser (or pg_read_all_settings) may read this. When it
    # cannot be read, creating pg_textsearch tells.
    preload=$(db_psql "$connect_to" -Atc "SELECT current_setting('shared_preload_libraries')" 2>/dev/null) || preload='?'
    if [ "$preload" != '?' ] && ! tr ',' '\n' <<<"$preload" | tr -d ' "' | grep -qx pg_textsearch; then
        problems+=('pg_textsearch is not loaded when PostgreSQL starts. Add it to shared_preload_libraries'
            "in postgresql.conf (now: '${preload}'), then restart PostgreSQL.")
    fi

    # The databases: there already, or created for the user when it may.
    listed=$(db_psql "$connect_to" -At -F '|' -c \
        "SELECT datname, has_database_privilege(datname, 'CONNECT'), has_database_privilege(datname, 'CREATE')
         FROM pg_database WHERE datname IN ('$DB_NAME', '$DB_ANALYTICS', '$DB_PULSE', '$DB_SPECTRA')") || listed=''
    local row can_connect can_create details schema_ok installed tables chatvia_tables needed absent=() ext create_it
    for database in "$DB_NAME" "$DB_ANALYTICS" "$DB_PULSE" "$DB_SPECTRA"; do
        case $database in
            "$DB_NAME") needed=$APP_EXTENSIONS ;;
            "$DB_ANALYTICS") needed=pg_partman ;;
            *) needed='' ;;
        esac
        row=$(grep "^$database|" <<<"$listed") || row=''
        installed='' create_it=0
        if [ -z "$row" ]; then
            if [ "$super" = t ] || [ "$createdb" = t ]; then
                create_it=1
                can_create=t
            else
                sql_dbs+=("CREATE DATABASE $database OWNER \"$DB_USER\";")
                can_create=f
            fi
        else
            IFS='|' read -r _ can_connect can_create <<<"$row"
            details=''
            if [ "$can_connect" = t ]; then
                details=$(db_psql "$database" -At -F '|' -c \
                    "SELECT has_schema_privilege('public', 'CREATE'),
                            (SELECT coalesce(string_agg(extname, ' '), '') FROM pg_extension),
                            (SELECT count(*) FROM pg_tables WHERE schemaname = 'public'),
                            to_regclass('public.agents') IS NOT NULL" 2>/dev/null) || details=''
            fi
            IFS='|' read -r schema_ok installed tables chatvia_tables <<<"$details"
            if [ "$can_connect" != t ] || [ "$can_create" != t ] || [ "$schema_ok" != t ]; then
                sql+=("ALTER DATABASE $database OWNER TO \"$DB_USER\";")
                can_create=f
            fi
            if [ "$database" = "$DB_NAME" ] && [ "${tables:-0}" -gt 0 ] && [ "$chatvia_tables" != t ]; then
                problems+=("The database $database holds the tables of another app. Use an empty database, or another name.")
            fi
        fi

        # The extensions it still needs (of those on the server), and who
        # may create them.
        absent=()
        for ext in $needed; do
            [[ " $installed " == *" $ext "* ]] && continue
            grep -q "^$ext|" <<<"$available" || continue
            if [ "$super" = t ] || { [ "$can_create" = t ] && [[ " $trusted " == *" $ext "* ]]; }; then continue; fi
            absent+=("$ext")
        done
        if [ "${#absent[@]}" -gt 0 ]; then
            # The superuser can only connect to it once it is there.
            if [ "$create_it" = 1 ]; then
                create_it=0
                sql_dbs+=("CREATE DATABASE $database OWNER \"$DB_USER\";")
            fi
            sql+=("\\connect $database")
            for ext in "${absent[@]}"; do
                if [ "$ext" = pg_partman ]; then
                    sql+=("CREATE SCHEMA IF NOT EXISTS partman AUTHORIZATION \"$DB_USER\";"
                        'CREATE EXTENSION IF NOT EXISTS pg_partman SCHEMA partman;'
                        "GRANT ALL ON ALL TABLES IN SCHEMA partman TO \"$DB_USER\";"
                        "GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA partman TO \"$DB_USER\";"
                        "GRANT EXECUTE ON ALL PROCEDURES IN SCHEMA partman TO \"$DB_USER\";")
                else
                    sql+=("CREATE EXTENSION IF NOT EXISTS $ext;")
                fi
            done
        fi
        if [ "$create_it" = 1 ]; then created+=("$database"); fi
    done

    # Backups (and the backup before each upgrade) need a pg_dump of the
    # server's version or newer: from Ubuntu, or from the PostgreSQL archive,
    # which the install adds when Ubuntu lacks it.
    if command -v apt-cache >/dev/null 2>&1 && [ ! -x "/usr/lib/postgresql/$DB_MAJOR/bin/pg_dump" ] &&
        ! apt_has_candidate "postgresql-client-$DB_MAJOR" && ! pgdg_carries_this_release; then
        problems+=("There is no package postgresql-client-$DB_MAJOR for backups of PostgreSQL $DB_MAJOR. Add the"
            'PostgreSQL apt repository (https://wiki.postgresql.org/wiki/Apt), then run the installer again.')
    fi

    sql=(${sql_dbs[@]+"${sql_dbs[@]}"} ${sql[@]+"${sql[@]}"})
    if [ "${#problems[@]}" -eq 0 ] && [ "${#sql[@]}" -eq 0 ]; then
        ok 'pgvector, pg_textsearch and pg_partman are on the server'
        if [ "${#created[@]}" -gt 0 ]; then
            info "$(dim "The install creates the databases ${created[*]}")"
        fi
        return 0
    fi

    # On stderr, like the failure itself, so the two never come apart.
    {
        fail 'This PostgreSQL server cannot hold ChatVia.ai yet.'
        if [ "${#problems[@]}" -gt 0 ]; then
            local line
            for line in "${problems[@]}"; do info "$line"; done
        fi
        if [ "${#sql[@]}" -gt 0 ]; then
            block text
            info "$DB_USER may not do this itself. A superuser runs it in psql:"
            if [ "$preload" = '?' ] && [[ ${sql[*]} == *pg_textsearch* ]]; then
                info "$(dim 'pg_textsearch must be in shared_preload_libraries first, or creating it fails.')"
            fi
            printf '%s\n' "${sql[@]}" | quote
        fi
    } >&2
    die 'Nothing was changed.' 'Fix these, then run the installer again.'
}

# The first database the user can connect to, on stdout: the app's, or,
# while that does not exist yet, one that is always there. Otherwise psql's
# message, and 1.
native_db_reachable() {
    local database output
    for database in "$DB_NAME" postgres template1; do
        if output=$(db_psql "$database" -Atc 'SELECT 1' 2>&1); then
            printf '%s' "$database"
            return 0
        fi
        [[ $output == *"database \"$database\" does not exist"* ]] || break
    done
    printf '%s\n' "$output"
    return 1
}

# Creates what the checks found missing: the databases (when the user may
# create databases) and the extensions (when it may create them). Safe to
# repeat, like every step.
native_prepare_database() {
    local database ext via
    via=$(native_db_reachable) || {
        echo "$via" >&2
        return 1
    }
    for database in "$DB_NAME" "$DB_ANALYTICS" "$DB_PULSE" "$DB_SPECTRA"; do
        if [ -z "$(db_psql "$via" -Atc "SELECT 1 FROM pg_database WHERE datname = '$database'")" ]; then
            db_psql "$via" -c "CREATE DATABASE $database"
            echo "Created the database $database."
        fi
    done
    for ext in $APP_EXTENSIONS; do
        db_psql "$DB_NAME" -c "CREATE EXTENSION IF NOT EXISTS $ext"
    done
    db_psql "$DB_ANALYTICS" -c 'CREATE SCHEMA IF NOT EXISTS partman' \
        -c 'CREATE EXTENSION IF NOT EXISTS pg_partman SCHEMA partman'
    db_psql "$DB_NAME" -Atc "SELECT extname || ' ' || extversion FROM pg_extension ORDER BY 1"
}

pgdg_carries_this_release() {
    local release
    # shellcheck source=/dev/null
    release=$(. /etc/os-release 2>/dev/null && printf '%s' "${VERSION_CODENAME:-}")
    [ -n "$release" ] && curl -fsI --max-time 10 "$PGDG_URL/dists/$release-pgdg/Release" >/dev/null 2>&1
}
# --- installer/src/42-complete-steps.sh ---
#------------------------------------------------------------------------------
# Complete installation: the install steps, and what they write
#------------------------------------------------------------------------------

# The certificate, when the answers ask for one (or an earlier attempt could
# not get it). Without it, Chatvia runs on HTTP: the next run of the
# installer tries again.
native_https() {
    if run_step "Getting an HTTPS certificate for $DOMAIN" native_get_certificate; then
        HTTPS=yes
        APP_URL="https://$DOMAIN"
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
        nginx redis-server supervisor certbot python3-certbot-nginx fail2ban \
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
; Chatvia's PHP-FPM pools. Written by the Chatvia installer.
[chatvia]
user = chatvia
group = chatvia

listen = /run/php/chatvia.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 10
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
; Knowledge files can be up to 50 MB.
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M

; Chat answers. Each one keeps a worker busy until it ends, so they get
; workers of their own and a busy chat cannot make pages wait.
[chatvia-streams]
user = chatvia
group = chatvia

listen = /run/php/chatvia-streams.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 20
pm.start_servers = 1
pm.min_spare_servers = 1
pm.max_spare_servers = 2
pm.max_requests = 500

php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
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

# The site, on HTTP. Certbot adds HTTPS to it.
native_configure_nginx() {
    local name=$DOMAIN
    if is_ip "$DOMAIN"; then name=_; fi
    sed -e "s|__SERVER_NAME__|$name|" -e "s|__ROOT__|$NATIVE_ROOT/current/public|" >/etc/nginx/sites-available/chatvia <<'EOF'
# Chatvia. Written by the Chatvia installer; Certbot adds HTTPS to it.
server {
    listen 80;
    listen [::]:80;
    server_name __SERVER_NAME__;

    root __ROOT__;
    index index.php;
    charset utf-8;

    # Knowledge files can be up to 50 MB.
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Live updates: the browser's WebSocket connections go to Reverb.
    location ^~ /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }

    # The chat widget. Other websites load it, so every file allows any origin.
    location ^~ /shakomako/ {
        add_header Access-Control-Allow-Origin "*" always;
        add_header Cache-Control "public, max-age=31536000, immutable" always;
        try_files $uri =404;
    }

    location = /shakomako/embed.js {
        add_header Access-Control-Allow-Origin "*" always;
        add_header Cache-Control "public, max-age=300" always;
        try_files $uri =404;
    }

    location = /shakomako/version.json {
        add_header Access-Control-Allow-Origin "*" always;
        add_header Cache-Control "no-cache" always;
        try_files $uri =404;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    # Chat answers go to the chatvia-streams pool. The same paths as
    # docker/app/nginx/streams.conf.
    location ~ ^/(?:chat/[^/]+/(?:stream/[^/]+|tools/[^/]+/resolve|conversations/[^/]+/messages/[^/]+/speech)|widget/stream|my/ask/stream|api/v1/agents/[^/]+/conversations/[^/]+/messages)/?$ {
        fastcgi_pass unix:/run/php/chatvia-streams.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300s;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 32k;
    }

    # $realpath_root: the release that `current` points at, so the first
    # request after a deploy runs the new one.
    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/chatvia.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_hide_header X-Powered-By;
        # Chat answers stream for a while.
        fastcgi_read_timeout 300s;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 16 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
    ln -sfn /etc/nginx/sites-available/chatvia /etc/nginx/sites-enabled/chatvia
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    systemctl enable nginx
    systemctl reload-or-restart nginx
}

# --keep-until-expiring: a certificate from an earlier attempt is put back
# into the site instead of asking for a new one.
native_get_certificate() {
    certbot --nginx --non-interactive --agree-tos --no-eff-email --keep-until-expiring --redirect \
        -m "$ADMIN_EMAIL" -d "$DOMAIN"
}

# The settings of this install. Secrets from an earlier attempt are kept:
# the database and Redis already use them.
native_write_env() {
    local file="$INSTALL_DIR/.env" app_key db_password redis_password reverb_id reverb_key reverb_secret mcp_secret
    local db_port secure=false
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
<?php

// Deploys Chatvia on this server. Written by the Chatvia installer, which
// replaces this file on an upgrade as long as you have not changed it.
//
// Every release goes into a folder of its own in __DEPLOY_PATH__/releases,
// and `current` points at the one that runs. A deploy builds the new release
// next to it and switches only when every step worked. As chatvia
// (sudo -iu chatvia), in ~/deploy:
//
//   vendor/bin/dep deploy --tag=v1.2.3                 deploy a release
//   vendor/bin/dep rollback && vendor/bin/dep artisan:reload
//                                                      back to the one before
//   vendor/bin/dep releases                            list the releases
//
// It runs on this server (localhost), as chatvia. To deploy from your own
// computer instead, copy this file there and replace localhost() with
// host('chat.example.com')->set('remote_user', 'chatvia').
// More: https://chatvia.ai/docs/guide/installation/deployer

namespace Deployer;

require 'recipe/laravel.php';

set('repository', '__REPOSITORY__');
set('keep_releases', 5);

// One user runs PHP-FPM, Horizon, Reverb and the scheduler, so a release
// needs no extra write permissions.
set('writable_mode', 'skip');

localhost()
    ->set('deploy_path', '__DEPLOY_PATH__');

desc('Creates the storage folders Laravel needs');
task('chatvia:storage', function () {
    run('mkdir -p {{deploy_path}}/shared/storage/framework/cache/data {{deploy_path}}/shared/storage/framework/sessions {{deploy_path}}/shared/storage/framework/views');
});

desc('Builds the frontend and the chat widget');
task('chatvia:build', function () {
    cd('{{release_path}}');
    run('npm ci --no-audit --no-fund');
    run('npm run build');
    run('npm run shakomako:build');
});

desc('Keeps every chat widget version online, in a folder all releases share');
task('chatvia:widget', function () {
    run('mkdir -p {{deploy_path}}/shared/shakomako');
    // A page that loaded an older widget still loads files from that
    // version's folder, so the versions of earlier releases stay. A folder
    // that is already there holds the same build and is skipped.
    run('for dir in {{current_path}}/public/shakomako/*/ {{release_path}}/public/shakomako/*/; do [ -d "$dir" ] || continue; version=$(basename "$dir"); [ -e "{{deploy_path}}/shared/shakomako/$version" ] || cp -R "${dir%/}" "{{deploy_path}}/shared/shakomako/$version"; done');
    // embed.js and version.json pick the version that loads. They stay with
    // the release until it goes live (chatvia:widget:publish).
    run('mv {{release_path}}/public/shakomako {{release_path}}/.shakomako');
    run('{{bin/symlink}} {{deploy_path}}/shared/shakomako {{release_path}}/public/shakomako');
});

desc('Makes the chat widget load the version of the release that runs');
task('chatvia:widget:publish', function () {
    // A release from before chatvia:widget serves its own public/shakomako.
    if (! test('[ -d {{current_path}}/.shakomako ]')) {
        return;
    }

    // Copied, then renamed, so nginx never serves a half-written file.
    run('cd {{current_path}}/.shakomako && for file in *; do [ -f "$file" ] || continue; cp "$file" "{{deploy_path}}/shared/shakomako/.$file.tmp" && mv -f "{{deploy_path}}/shared/shakomako/.$file.tmp" "{{deploy_path}}/shared/shakomako/$file"; done');
});

// In place of the recipe's plain migrate: chatvia:deploy:migrate creates the
// analytics, Pulse and Spectra databases, migrates, checks the embedding
// settings and adds the built-in tools, the same steps the Docker image runs.
desc('Migrates the database and runs the checks that go with it');
task('artisan:migrate', artisan('chatvia:deploy:migrate --no-interaction', ['skipIfNoEnv', 'showOutput']));

after('deploy:shared', 'chatvia:storage');
after('deploy:vendors', 'chatvia:build');
after('chatvia:build', 'chatvia:widget');
after('deploy:symlink', 'chatvia:widget:publish');
after('rollback', 'chatvia:widget:publish');
after('deploy:failed', 'deploy:unlock');
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
; Chatvia. Written by the Chatvia installer.
[program:chatvia-horizon]
command=/usr/bin/php __CURRENT__/artisan horizon
directory=__CURRENT__
user=chatvia
environment=HOME="__HOME__"
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
; A running job gets up to an hour to finish, so a long document is not cut off.
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/log/supervisor/chatvia-horizon.log

[program:chatvia-reverb]
command=/usr/bin/php __CURRENT__/artisan reverb:start
directory=__CURRENT__
user=chatvia
environment=HOME="__HOME__"
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/chatvia-reverb.log

[program:chatvia-pulse]
command=/usr/bin/php __CURRENT__/artisan pulse:check
directory=__CURRENT__
user=chatvia
environment=HOME="__HOME__"
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/chatvia-pulse.log
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
        -e "s|__INSTALLER_URL__|${CHATVIA_INSTALLER_URL:-$INSTALLER_URL}|" \
        -e "s|__SELF__|$NATIVE_AUTO_UPDATE|g" -e "s|__DOCS__|$NATIVE_DOCS|" <<'EOF'
#!/usr/bin/env bash
#
# ChatVia.ai automatic updates. Written by the ChatVia.ai installer, which
# replaces this file on every upgrade.
#
# While they are on, cron runs `chatvia-auto-update run` once a night. It
# looks for a newer release of the major version installed here: 1.4.2 goes
# to 1.5.0, but never to 2.0.0, as a new major version may need steps of
# its own. When there is one, it runs the installer's upgrade: a backup of
# the database first, then a deploy with Deployer, which switches over only
# when every step worked. A failed update leaves the running version alone.
#
#   sudo chatvia-auto-update status   on or off, the version, the last runs
#   sudo chatvia-auto-update run      look for a newer release now
#   sudo chatvia-auto-update off      turn automatic updates off
#   sudo chatvia-auto-update on       turn them on again
#
# More: __DOCS__#automatic-updates

set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

CONF=__CONF__
CRON=__CRON__
LOG=__LOG__
INSTALL_LOG=__INSTALL_LOG__
INSTALLER_URL=__INSTALLER_URL__

conf_value() {
    sed -n "s/^$1=//p" "$CONF" 2>/dev/null | tail -n 1
}

conf_set() {
    if grep -q "^$1=" "$CONF"; then
        sed -i "s|^$1=.*|$1=$2|" "$CONF"
    else
        printf '%s=%s\n' "$1" "$2" >>"$CONF"
    fi
}

# The releases (vX.Y.Z tags) of the repository, oldest first.
releases() {
    GIT_TERMINAL_PROMPT=0 git ls-remote --tags --refs "$(conf_value CHATVIA_REPO)" 'v*' |
        sed 's#.*refs/tags/##' | { grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' || true; } | sort -V
}

# newer A B: A is a later version than B.
newer() {
    [ "$1" != "$2" ] && [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | tail -n 1)" = "$1" ]
}

# When cron runs it, as HH:MM.
schedule() {
    awk '!/^#/ && NF >= 2 { printf "%02d:%02d", $2, $1; exit }' "$CRON"
}

run() {
    local installed major tags latest newest status=0
    if [ -t 1 ]; then
        exec > >(tee -a "$LOG") 2>&1
    else
        exec >>"$LOG" 2>&1
    fi
    echo "== $(date '+%Y-%m-%d %H:%M')"

    # One update at a time.
    exec 9>/run/chatvia-auto-update.lock
    if ! flock -n 9; then
        echo 'An update runs already.'
        return 0
    fi
    if ! grep -q '^# Install finished on' "$CONF" 2>/dev/null; then
        echo 'The install has not finished: run the installer again first.'
        return 1
    fi
    installed=$(conf_value CHATVIA_RELEASE)
    if [ -z "$installed" ]; then
        echo "This server runs the branch $(conf_value CHATVIA_BRANCH), not a release: automatic updates follow releases only."
        return 0
    fi
    major=${installed#v}
    major=${major%%.*}

    if ! tags=$(releases); then
        echo "Could not read the releases of $(conf_value CHATVIA_REPO). Trying again next time."
        return 1
    fi
    latest=$(printf '%s\n' "$tags" | grep -E "^v$major\." | tail -n 1) || latest=''
    newest=$(printf '%s\n' "$tags" | tail -n 1)
    if [ -n "$newest" ] && newer "$newest" "${latest:-$installed}"; then
        echo "ChatVia.ai $newest is out, a new major version. Automatic updates stay on $major.x:"
        echo 'read its changelog, then upgrade by hand (run the installer again).'
    fi
    if [ -z "$latest" ] || ! newer "$latest" "$installed"; then
        echo "ChatVia.ai $installed is the newest $major.x release. Nothing to do."
        return 0
    fi

    echo "Updating ChatVia.ai $installed to $latest."
    INSTALLER=$(mktemp)
    trap 'rm -f "$INSTALLER"' EXIT
    if ! curl -fsSL --max-time 120 "$INSTALLER_URL" -o "$INSTALLER" || ! bash -n "$INSTALLER"; then
        echo "Could not download the installer from $INSTALLER_URL. Trying again next time."
        return 1
    fi
    NO_COLOR=1 TERM=dumb bash "$INSTALLER" --complete --upgrade --yes --version "$latest" </dev/null || status=$?
    if [ "$status" != 0 ]; then
        echo "The update to $latest stopped: $installed still runs. See why: $INSTALL_LOG"
        return "$status"
    fi
    echo "ChatVia.ai $latest is running."
}

# A random time between 02:00 and 04:59, so servers do not all build at once.
turn_on() {
    if [ ! -f "$CRON" ]; then
        printf '# ChatVia.ai automatic updates. Written by __SELF__.\n# Turn them off: sudo chatvia-auto-update off\n%d %d * * * root __SELF__ run\n' \
            $((RANDOM % 60)) $((2 + RANDOM % 3)) >"$CRON.new"
        chmod 644 "$CRON.new"
        mv "$CRON.new" "$CRON"
    fi
    conf_set AUTO_UPDATE yes
    echo "Automatic updates are on: every night at $(schedule) (server time)."
    if [ -z "$(conf_value CHATVIA_RELEASE)" ]; then
        echo 'This server runs a branch, not a release, so they do nothing until it runs a release.'
    fi
}

turn_off() {
    rm -f "$CRON"
    conf_set AUTO_UPDATE no
    echo 'Automatic updates are off. Upgrade by hand: run the installer again.'
}

status() {
    local release
    if [ -f "$CRON" ]; then
        echo "Automatic updates are on: every night at $(schedule) (server time)."
    else
        echo 'Automatic updates are off.'
    fi
    release=$(conf_value CHATVIA_RELEASE)
    echo "Installed: ChatVia.ai ${release:-branch $(conf_value CHATVIA_BRANCH)}"
    if [ -s "$LOG" ]; then
        echo
        echo "The last lines of $LOG:"
        tail -n 8 "$LOG"
    fi
}

if [ "$(id -u)" != 0 ]; then
    echo 'Run it with sudo: sudo chatvia-auto-update status' >&2
    exit 1
fi
case ${1:-status} in
    run) run ;;
    on) turn_on ;;
    off) turn_off ;;
    status) status ;;
    *)
        echo 'Usage: sudo chatvia-auto-update status | run | on | off' >&2
        exit 1
        ;;
esac
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

# --- installer/src/43-complete-upgrade.sh ---
#------------------------------------------------------------------------------
# Complete installation: the finish, and running the installer again
#------------------------------------------------------------------------------

native_show_finish() {
    local title open admin email password saved='' n=1
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
    if [ "$HTTPS" = pending ]; then
        info "$n. Point $DOMAIN at this server, then run this installer again to get"
        info '   the HTTPS certificate.'
        n=$((n + 1))
    fi
    info "$n. Sign in to the admin panel and set up two-factor authentication."
    info "$((n + 1)). Follow the short setup there: your AI provider and its API key,"
    info '   the models, and email.'
    info "$((n + 2)). Then add the built-in tools, like charts, forms and web search:"
    info "   sudo -u $NATIVE_USER php $NATIVE_ROOT/current/artisan chatvia:mcp:sync-builtin"

    section 'Running on this server'
    info "Web app       nginx and PHP-FPM $NATIVE_PHP $(dim "($APP_URL)")"
    info "Queues        Horizon $(dim '(Supervisor: chatvia-horizon)')"
    info "Live updates  Reverb $(dim '(Supervisor: chatvia-reverb, through nginx at /app/)')"
    info "Server stats  Pulse $(dim '(Supervisor: chatvia-pulse)')"
    info "Scheduler     every minute $(dim '(cron: /etc/cron.d/chatvia)')"
    if [ "$DATABASE" = existing ]; then
        info "Database      your PostgreSQL $DB_MAJOR $(dim "($DB_HOST:$DB_PORT, database $DB_NAME, user $DB_USER)")"
    else
        info "Database      PostgreSQL $NATIVE_PG $(dim '(pgvector, pg_textsearch, pg_partman)')"
    fi
    info "Cache         Redis $(dim '(with a password, kept on disk)')"
    if [ "$HTTPS" = yes ]; then info "HTTPS         Let's Encrypt $(dim '(renews by itself)')"; fi
    if [ "$FIREWALL" = yes ]; then info "Firewall      ufw $(dim '(SSH, HTTP and HTTPS only)')"; fi
    info "Deploys       Deployer $(dim "(user $NATIVE_USER, in $NATIVE_DEPLOY_DIR)")"
    if [ "$AUTO_UPDATE" = yes ]; then
        info "Updates       automatic, once a night $(dim "(cron: $NATIVE_AUTO_UPDATE_CRON)")"
    else
        info "Updates       by hand $(dim '(automatic updates are off)')"
    fi

    section 'Good to know'
    info "Settings     $NATIVE_ROOT/shared/.env"
    info "             $(dim 'Back it up: its APP_KEY unlocks the secrets in the database.')"
    info "Upgrade      $(dim 'run this installer again: it deploys the newest release')"
    if [ "$AUTO_UPDATE" = yes ]; then
        info "Auto update  sudo chatvia-auto-update status $(dim '(or run, off, on)')"
        info "             $(dim 'Each night it looks for a newer release of the same major version,')"
        info "             $(dim "backs up the database, then deploys it. Log: $NATIVE_AUTO_UPDATE_LOG")"
    else
        info "Auto update  off $(dim '(turn it on: sudo chatvia-auto-update on)')"
    fi
    info "Status       sudo supervisorctl status"
    info "App logs     $NATIVE_ROOT/shared/storage/logs"
    info "Deployer     sudo -iu $NATIVE_USER, then cd deploy && vendor/bin/dep releases"
    info "Backups      $(dim 'the database, twice a day, to the storage set in the admin panel')"
    info "Install log  $NATIVE_LOG"

    count_install
    send_news_email
    offer_browser
    outro "$(dim "Guide: $NATIVE_DOCS")"
}

native_resume() {
    warn 'An earlier complete installation on this server did not finish.'
    if [ "$INTERACTIVE" = 1 ]; then
        choose 'What do you want to do?' \
            'Finish it  — the remaining steps, with the answers you gave then' \
            'Nothing, exit'
        if [ "$CHOICE" != 0 ]; then
            outro 'Nothing was changed.'
            exit 0
        fi
    fi
    native_load_settings

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
    native_run_steps
}

native_upgrade() {
    native_load_settings
    local installed new
    installed=$(native_version_text)
    ok "ChatVia.ai $(bold "$installed") is installed on this server $(dim '(complete installation)')"
    info "$(dim 'The install questions only run once. Your data and settings are kept.')"

    if [ "$HTTPS" = pending ]; then native_retry_https; fi

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

    run_step "$(native_auto_update_step)" native_configure_auto_update ||
        warn "chatvia-auto-update could not be updated. See why: $LOG"

    # Without --version: the newest release, or the newest code of the
    # branch an install of a branch follows.
    if [ "$VERSION_GIVEN" = 0 ]; then
        CHATVIA_VERSION=${CHATVIA_BRANCH:-latest}
    fi
    local old_release=$CHATVIA_RELEASE
    native_resolve_version
    new=$(native_version_text)
    if [ -n "$old_release" ] && [ -n "$CHATVIA_RELEASE" ]; then
        if [ "$old_release" = "$CHATVIA_RELEASE" ] && [ "$VERSION_GIVEN" = 0 ]; then
            outro "ChatVia.ai $old_release is the newest version. Nothing to do."
            return
        fi
        if ! version_at_least "${CHATVIA_RELEASE#v}" "${old_release#v}"; then
            die "$CHATVIA_RELEASE is older than the version installed here ($old_release)." \
                "Going back needs the data from before the upgrade: a backup in $NATIVE_BACKUPS."
        fi
    fi
    if [ "$installed" != "$new" ]; then info "Version $installed $S_ARROW $(bold "$new")"; fi

    native_refresh_deploy_file
    run_step "Updating PHP $NATIVE_PHP from ppa:ondrej/php" native_update_php ||
        die 'PHP could not be updated, so nothing was upgraded.' "See why: $LOG"
    run_step 'Installing the backup programs (pg_dump, pg_restore, redis-cli, gpg)' native_install_backup_programs ||
        warn "The backup programs are missing, so backups (Settings → Backups) fail. See why: $LOG"
    run_step 'Backing up the database' native_backup ||
        die 'The backup failed, so nothing was upgraded.' "See why: $LOG"
    run_step "Deploying ChatVia.ai $new" native_upgrade_deploy ||
        die "The upgrade stopped. ChatVia.ai $installed still runs: Deployer switches only when every step worked." \
            "See why: $LOG"
    if [ "$DRY_RUN" = 0 ]; then
        conf_set CHATVIA_RELEASE "$CHATVIA_RELEASE"
        conf_set CHATVIA_BRANCH "$CHATVIA_BRANCH"
    fi
    run_step 'Waiting for the web app' wait_until 120 native_app_is_up ||
        die 'The web app did not answer within 2 minutes.' \
            "See why: sudo tail -n 50 $NATIVE_ROOT/shared/storage/logs/laravel-$(date +%F).log"
    run_step 'Checking that every part runs' native_check_parts ||
        die 'ChatVia.ai runs, but not every part of it.' "See why: $LOG"
    ok "$(bold "ChatVia.ai $new is running.")"
    if [ "$AUTO_UPDATE" != yes ]; then
        info "$(dim 'Automatic updates are off. Turn them on: sudo chatvia-auto-update on')"
    fi
    outro "Open $(accent "$APP_URL")"
}

# deploy.php is the installer's until you change it: an upgrade replaces an
# unchanged one, so it gets the steps a new release may need.
native_refresh_deploy_file() {
    [ "$DRY_RUN" = 1 ] && return 0
    if [ "$(cksum <"$NATIVE_DEPLOY_DIR/deploy.php")" = "$(conf_value DEPLOY_FILE_SUM)" ]; then
        native_write_deploy_file
    else
        warn "$NATIVE_DEPLOY_DIR/deploy.php has your changes, so it was not updated."
    fi
}

# A copy of the database right before an upgrade, in /var/backups/chatvia.
# The newest five are kept.
native_backup() {
    local stamp file
    stamp="$(date '+%Y%m%d-%H%M%S')-before-$(native_version_text | tr ' ' '-')"
    install -d -m 700 "$NATIVE_BACKUPS"
    file="$NATIVE_BACKUPS/chatvia-$stamp.dump"
    native_dump "$DB_NAME" >"$file"
    chmod 600 "$file"
    echo "Saved $file"
    # The analytics numbers have a database of their own.
    if [ "$(native_backup_psql -Atc "SELECT 1 FROM pg_database WHERE datname = '$DB_ANALYTICS'")" = 1 ]; then
        file="$NATIVE_BACKUPS/analytics-$stamp.dump"
        native_dump "$DB_ANALYTICS" >"$file"
        chmod 600 "$file"
        echo "Saved $file"
    fi
    # shellcheck disable=SC2012 # The installer names these files.
    ls -1t "$NATIVE_BACKUPS"/chatvia-*-before-*.dump | tail -n +6 | xargs -r rm -f
    # shellcheck disable=SC2012
    ls -1t "$NATIVE_BACKUPS"/analytics-*-before-*.dump 2>/dev/null | tail -n +6 | xargs -r rm -f
}

# native_dump DATABASE: as the postgres superuser on this server's own
# cluster, or as the database user on an existing server.
native_dump() {
    if [ "$DATABASE" = existing ]; then
        db_dump "$1"
    else
        (cd / && runuser -u postgres -- pg_dump --cluster "$NATIVE_PG/main" -Fc "$1")
    fi
}

native_backup_psql() {
    if [ "$DATABASE" = existing ]; then
        db_psql "$DB_NAME" "$@"
    else
        psql_admin "$@"
    fi
}

native_retry_https() {
    warn "$DOMAIN has no HTTPS certificate yet."
    SERVER_IP=$(server_ip)
    native_check_dns || true
    if [ "$INTERACTIVE" = 1 ] && ! confirm "Get it now? $DOMAIN must point at this server." y; then return; fi
    if ! run_step "Getting an HTTPS certificate for $DOMAIN" native_get_certificate; then
        warn 'Still no certificate. ChatVia.ai keeps running on HTTP.'
        return
    fi
    HTTPS=yes
    APP_URL="https://$DOMAIN"
    run_step 'Switching ChatVia.ai to HTTPS' native_switch_to_https ||
        die 'Could not switch to HTTPS.' "See why: $LOG"
}

native_switch_to_https() {
    native_set_env APP_URL "$APP_URL"
    native_set_env SESSION_SECURE_COOKIE true
    conf_set HTTPS yes
    conf_set APP_URL "$APP_URL"
    as_chatvia "$NATIVE_ROOT/current" php artisan optimize
    as_chatvia "$NATIVE_ROOT/current" php artisan reload
}

# --- installer/src/90-main.sh ---
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
            "Complete installation  — everything directly on this server (Ubuntu $NATIVE_UBUNTU or newer)"
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
