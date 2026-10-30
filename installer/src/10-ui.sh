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
green() { printf '%s%s%s' "$C_GREEN" "$*" "$C_RESET"; }

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

        # The default and a placeholder are greyed out until you type, as in
        # ./manage; the line below says Enter uses the default. A long answer
        # shows its end, where you type.
        input_room "$STATUS"
        if [ -n "$value" ] && [ "${#value}" -ge "$ROOM" ] && [ "$ROOM" -gt 10 ]; then
            input="$C_DIM$S_MORE$C_RESET${value:$((${#value} - ROOM + 1 + ${#S_MORE}))}$C_INVERSE $C_NO_INVERSE"
        elif [ -n "$value" ]; then
            input="$value$C_INVERSE $C_NO_INVERSE"
        elif [ -n "$default" ]; then
            input="$C_INVERSE${default:0:1}$C_NO_INVERSE$C_DIM${default:1}$C_RESET"
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

