#!/usr/bin/env bash
#
# ChatVia.ai automatic updates. Written by the ChatVia.ai installer, which
# replaces this file on every upgrade.
#
# While they are on, cron runs `chatvia-auto-update run` once a night. It
# looks for a newer release of the major version installed here: 1.4.2 goes
# to 1.5.0, but never to 2.0.0, as a new major version may need steps of
# its own. When there is one, it runs that release's installer, taken from
# the repository itself (the place the code comes from, so no other site
# decides what runs as root here): a backup of the database first, then a
# deploy with Deployer, which switches over only when every step worked. A
# failed update leaves the running version alone.
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
# Set when the install was made with CHATVIA_INSTALLER_URL: then every
# update takes its installer from there instead of the release.
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

# installer_url RELEASE: where the installer of that release is read from:
# the repository on GitHub at that release's tag, unless the install named a
# place of its own. A repository elsewhere than GitHub has no such address,
# so its installs must name one.
installer_url() {
    local repo
    if [ -n "$INSTALLER_URL" ]; then
        printf '%s' "$INSTALLER_URL"
        return 0
    fi
    repo=$(conf_value CHATVIA_REPO)
    case $repo in
        https://github.com/*) repo=${repo#https://github.com/} ;;
        git@github.com:*) repo=${repo#git@github.com:} ;;
        *) return 1 ;;
    esac
    printf 'https://raw.githubusercontent.com/%s/%s/install.sh' "${repo%.git}" "$1"
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
    local installed major tags latest newest url status=0
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
    if ! url=$(installer_url "$latest"); then
        echo "The repository $(conf_value CHATVIA_REPO) is not on GitHub, so the installer has no address: install with CHATVIA_INSTALLER_URL set."
        return 1
    fi
    INSTALLER=$(mktemp)
    trap 'rm -f "$INSTALLER"' EXIT
    if ! curl -fsSL --max-time 120 "$url" -o "$INSTALLER" || ! bash -n "$INSTALLER"; then
        echo "Could not download the installer from $url. Trying again next time."
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
