#!/bin/sh
# Runs at the start of every Chatvia container, after the base image's own
# start-up scripts and before the command.
set -e

cd "${APP_BASE_DIR:-/var/www/chatvia}"

# A new storage volume is empty, and an older one may miss newer folders.
mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Web container only (the base image starts nginx through s6): forward the
# WebSocket path to Reverb. nginx resolves the name per request through the
# container's DNS server, so it still starts while Reverb is down.
if [ "$S6_INITIALIZED" = "true" ]; then
    resolver=$(awk '/^nameserver/ { print $2; exit }' /etc/resolv.conf)
    case "$resolver" in
        *:*) resolver="[$resolver]" ;;
    esac

    sed \
        -e "s|__RESOLVER__|${resolver:-127.0.0.11}|" \
        -e "s|__REVERB_UPSTREAM__|${CHATVIA_REVERB_UPSTREAM}|" \
        /etc/chatvia/nginx/reverb.conf.template > /etc/nginx/server-opts.d/reverb.conf
fi

# Cache the configuration once the install has its secrets. chatvia-setup
# runs before they exist and needs no cache.
if [ -n "${APP_KEY:-}" ]; then
    php artisan config:cache --no-interaction > /dev/null
    php artisan event:cache --no-interaction > /dev/null

    if [ "$S6_INITIALIZED" = "true" ]; then
        php artisan view:cache --no-interaction > /dev/null
    fi
fi
