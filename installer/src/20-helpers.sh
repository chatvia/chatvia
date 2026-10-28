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

