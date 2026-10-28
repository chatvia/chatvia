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
    if [ "${HTTPS_SERVER:-}" = caddy ]; then
        info "Web app       Caddy, then nginx and PHP-FPM $NATIVE_PHP $(dim "($APP_URL)")"
    else
        info "Web app       nginx and PHP-FPM $NATIVE_PHP $(dim "($APP_URL)")"
    fi
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
    if [ "$HTTPS" = yes ]; then
        info "HTTPS         Let's Encrypt, through Caddy $(dim '(renews by itself, also for custom domains)')"
    fi
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
        local options=('Upgrade to the newest version  — keeps your data')
        if [ "${HTTPS_SERVER:-}" = certbot ]; then
            options+=("Move HTTPS from Certbot to Caddy  — for agents' custom domains")
        fi
        choose 'What do you want to do?' "${options[@]}" 'Nothing, exit'
        if [ "$CHOICE" = 1 ] && [ "${HTTPS_SERVER:-}" = certbot ]; then
            native_move_to_caddy
            return
        fi
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
    native_use_caddy_env
    conf_set HTTPS yes
    conf_set APP_URL "$APP_URL"
    as_chatvia "$NATIVE_ROOT/current" php artisan optimize
    as_chatvia "$NATIVE_ROOT/current" php artisan reload
}

# The app learns that Caddy is in front: it believes Caddy about each
# visitor (a proxy list the install already had is kept) and turns custom
# domains on.
native_use_caddy_env() {
    local proxies
    proxies=$(env_value TRUSTED_PROXIES)
    case ",$proxies," in
        *,127.0.0.1,*) ;;
        ,,) native_set_env TRUSTED_PROXIES 127.0.0.1 ;;
        *) native_set_env TRUSTED_PROXIES "$proxies,127.0.0.1" ;;
    esac
    native_set_env CHATVIA_ON_DEMAND_TLS true
    HTTPS_SERVER=caddy
    conf_set HTTPS_SERVER caddy
}

# An install from before Caddy: Caddy takes ports 80 and 443 from nginx and
# Certbot, so agents' custom domains get their certificates by themselves.
# When Caddy gets no certificate, nginx keeps Certbot's site as it was.
native_move_to_caddy() {
    run_step "Moving HTTPS for $DOMAIN to Caddy" native_get_certificate ||
        die 'Caddy got no certificate, so nothing changed: nginx and Certbot serve HTTPS as before.' \
            "See why: $LOG"
    run_step 'Stopping the Certbot renewals' certbot delete --non-interactive --cert-name "$DOMAIN" ||
        warn "Certbot still renews the old certificate. It does no harm. See why: $LOG"
    if [ "$FIREWALL" = yes ]; then
        run_step 'Opening port 443 for HTTP/3' ufw allow 443/udp comment 'HTTPS (HTTP/3)' ||
            warn "Port 443/udp stays closed, so browsers use HTTP/2. See why: $LOG"
    fi
    run_step 'Telling ChatVia.ai that Caddy is in front' native_switch_to_https ||
        die 'Caddy serves HTTPS, but the app settings were not changed.' "See why: $LOG"
    ok "$(bold 'Caddy serves HTTPS now.')"
    info 'Agents can show their Hosted Chat on custom domains: Settings → Platform → Custom domains.'
    outro "Open $(accent "$APP_URL")"
}

