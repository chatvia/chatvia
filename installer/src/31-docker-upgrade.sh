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
    read_https_settings
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
    wait_for_https

    ok "$(bold "ChatVia.ai $CHATVIA_VERSION is running.")"
    # Custom domains need a certificate per domain, which only the built-in
    # HTTPS gets by itself.
    if [ "$BUILTIN_HTTPS" = 0 ] && [ "$SELF_SIGNED" = 0 ] && [ "$APP_URL" != "http://localhost:$APP_PORT" ]; then
        info "$(dim "For agents' custom domains, ChatVia.ai can handle HTTPS: ./manage https on")"
    fi
    outro "Open $(accent "$APP_URL")"
}

