#!/usr/bin/env bash
#
# Build the public (open-source) release tree from this private repository.
#
#   scripts/export-public.sh <target-dir> [git-ref]
#
# The tree is made with `git archive`, so it holds only files that are committed
# at <git-ref> (default: HEAD). Untracked and ignored files, and uncommitted
# changes, are never exported. Paths marked `export-ignore` in .gitattributes
# (production infra, internal/, AI-assistant settings, this script) are skipped.
#
# After the export the script checks the tree:
#   1. every private path is really absent;
#   2. no known private marker (production IP, personal e-mail addresses, the
#      cloud project id, local machine paths), private key, live payment key or
#      hard-coded database password is left in any file.
# It exits non-zero when a check fails. Do not publish the tree in that case.
#
# The private markers are listed in this file, which is itself export-ignored,
# so the list never reaches the public tree. Maintainer notes:
# internal/PUBLIC-EXPORT.md.
#
# Works with the bash 3.2 that ships with macOS.

set -euo pipefail

die() {
    printf 'export-public: %s\n' "$*" >&2
    exit 2
}

usage() {
    sed -n '3,5p' "$0" | sed 's/^# \{0,1\}//' >&2
    exit 2
}

[ $# -ge 1 ] && [ $# -le 2 ] || usage

target=$1
ref=${2:-HEAD}

repo_root=$(git rev-parse --show-toplevel 2>/dev/null) || die 'run this inside the Chatvia git repository'
cd "$repo_root"

git rev-parse --verify --quiet "${ref}^{commit}" >/dev/null || die "unknown git ref: $ref"

if [ -e "$target" ]; then
    [ -d "$target" ] || die "target exists and is not a directory: $target"
    [ -z "$(ls -A "$target")" ] || die "target directory is not empty: $target"
fi

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    printf 'export-public: warning: you have uncommitted changes. They are NOT exported; only %s is.\n' "$ref" >&2
fi

mkdir -p "$target"
target=$(cd "$target" && pwd)

# --worktree-attributes also reads the .gitattributes of this checkout, so a new
# export-ignore rule takes effect before it is committed (and also applies when
# you export an older ref).
git archive --worktree-attributes --format=tar "$ref" | tar -x -C "$target"

commit=$(git rev-parse --short "${ref}^{commit}")
file_count=$(find "$target" -type f | wc -l | tr -d ' ')
printf 'Exported %s (%s): %s files -> %s\n' "$ref" "$commit" "$file_count" "$target"

# ------------------------------------------------------------------------------
# Checks
# ------------------------------------------------------------------------------

cd "$target"
findings=0

report() {
    # Prints only the location and a label, never the matched text, so a real
    # secret is not copied into terminal scrollback or CI logs.
    printf '  %s  [%s]\n' "$1" "$2" >&2
    findings=$((findings + 1))
}

# 1. Private paths. Keep in sync with the export-ignore list in .gitattributes.
private_paths="
server
server.sh
deploy.php
.github/workflows/deploy.yml
postgresql.conf
Caddyfile
widget/.github
internal
scripts/export-public.sh
CLAUDE.md
AGENTS.md
.cursor
.junie
.claude
"
for path in $private_paths; do
    if [ -e "$path" ]; then
        report "$path" 'private path was exported (check .gitattributes)'
    fi
done

# Files that must never be public, wherever they are.
while IFS= read -r file; do
    report "${file#./}" 'secret-bearing file'
done < <(find . -type f \( -name '.env' -o -name '.env.production' -o -name '.env.backup' \
    -o -name 'auth.json' -o -name '*.pem' -o -name '*.key' -o -name 'id_rsa*' -o -name 'id_ed25519*' \) -print)

# 2a. Known private markers (fixed strings, case-insensitive). An optional third
# argument, "skip-lockfiles", ignores composer.lock / package-lock.json: they
# copy author metadata from the public package registries (the maintainer's own
# published packages list his e-mail there), which is not a leak from this repo.
check_marker() {
    local marker=$1 label=$2 hit
    local excludes='--exclude=.no-such-file'
    if [ "${3:-}" = 'skip-lockfiles' ]; then
        excludes='--exclude=composer.lock --exclude=package-lock.json'
    fi
    while IFS= read -r hit; do
        report "$hit" "$label"
    done < <(grep -rnIiF $excludes -- "$marker" . 2>/dev/null | cut -d: -f1,2 | sed 's#^\./##' || true)
}

check_marker '0.0.0.0' 'production server IP'
check_marker 'ahmad@mayahi.net' 'personal e-mail address' skip-lockfiles
check_marker 'ahmad.mayahi@gmail.com' 'personal e-mail address' skip-lockfiles
check_marker 'ce82c941-' 'cloud project id'
check_marker '/Users/ahmad/' 'local machine path'

# 2b. Secret-shaped strings (extended regex).
check_pattern() {
    local pattern=$1 label=$2 hit
    while IFS= read -r hit; do
        report "$hit" "$label"
    done < <(grep -rnIE -- "$pattern" . 2>/dev/null | cut -d: -f1,2 | sed 's#^\./##' || true)
}

check_pattern '-----BEGIN [A-Z ]*PRIVATE KEY-----' 'private key'
check_pattern '(sk|rk)_live_[0-9A-Za-z]{10,}' 'live Stripe key'

# 2c. Hard-coded database passwords: PASSWORD '<value>' where the value is not
# an obvious placeholder or a variable reference.
while IFS= read -r hit; do
    location=$(printf '%s' "$hit" | cut -d: -f1,2 | sed 's#^\./##')
    value=$(printf '%s' "$hit" | cut -d: -f3- | sed -E "s/^[^']*'(.*)'$/\1/" | tr '[:upper:]' '[:lower:]')
    case $value in
        '' | password | secret | changeme | '$'* | ':'* | '<'* | '{'* | '%'*) continue ;;
    esac
    report "$location" 'hard-coded database password'
done < <(grep -rnIoiE -- "PASSWORD[[:space:]]+'[^']*'" . 2>/dev/null || true)

if [ "$findings" -gt 0 ]; then
    printf '\nexport-public: FAILED with %s finding(s) above. Do NOT publish %s.\n' "$findings" "$target" >&2
    printf 'Fix them in the private repo (or add an export-ignore rule), commit, and export again.\n' >&2
    exit 1
fi

printf 'Safety checks passed. Review the tree, then publish it as a fresh history.\n'
