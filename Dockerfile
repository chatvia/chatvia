# syntax=docker/dockerfile:1

# Chatvia application image. One image runs every part of the app; the command
# picks the part (see docker/compose.yaml):
#
#   /init (default)                    web: nginx + PHP-FPM on port 8080
#   php artisan horizon                queue workers
#   php artisan schedule:work          scheduler
#   php artisan reverb:start           WebSocket server on port 8080
#   chatvia-setup                      one-shot: create the secrets of an install
#   chatvia-migrate                    one-shot: migrate, seed, first admin
#
# Build:  docker build --build-arg SHAKOMAKO_VERSION=$(npm run -s shakomako:version) -t chatvia .
# The release workflow (.github/workflows/docker.yml) builds it for every v* tag.

# PHP 8.5.12 or newer (composer.json): check-platform-reqs below fails the
# build on an older one. The tag names the patch release, so raising it is a
# change here, not a rebuild that happens to pull a newer image.
ARG PHP_IMAGE=serversideup/php:8.5.12-fpm-nginx-trixie-v5.0.3
ARG NODE_IMAGE=node:22-trixie-slim
# Shakomako, the chat widget, lives in its own repository;
# `npm run shakomako:build` (scripts/build-shakomako.mjs) reads SHAKOMAKO_REPO
# too. SHAKOMAKO_VERSION is the release to build and has no default:
# `npm run -s shakomako:version` prints the newest release, and the release
# workflow passes it in. The build cannot look up the newest release itself:
# Docker would reuse that step from its cache and keep an old widget.
ARG SHAKOMAKO_REPO=https://github.com/chatvia/shakomako.git
ARG SHAKOMAKO_VERSION

#-------------------------------------------------------------------------------
# PHP packages. Plain PHP files, so this stage runs on the build machine's own
# platform; extensions are checked in the final stage.
#-------------------------------------------------------------------------------
FROM --platform=$BUILDPLATFORM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --ignore-platform-reqs

#-------------------------------------------------------------------------------
# PHP runtime: the base image plus the extensions it does not have. Shared by
# the routes stage and the application.
#-------------------------------------------------------------------------------
FROM ${PHP_IMAGE} AS php

USER root

RUN install-php-extensions intl gd exif

#-------------------------------------------------------------------------------
# Wayfinder route functions (resources/js/routes), generated from the Laravel
# routes for the frontend build. It boots the app, so it needs PHP; the assets
# stage has none. No database, Redis or Reverb exists here, so those are off.
#-------------------------------------------------------------------------------
FROM php AS routes

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN set -eux; \
    cp docker/app/production.env .env; \
    mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/views storage/logs; \
    composer dump-autoload --no-dev --no-scripts --no-interaction; \
    BROADCAST_CONNECTION=null CACHE_STORE=array APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" \
        php artisan wayfinder:generate --skip-actions

#-------------------------------------------------------------------------------
# Frontend assets. Built without any VITE_* settings: the browser then takes
# the WebSocket address and key from the page (see resources/js/app.ts).
#-------------------------------------------------------------------------------
FROM --platform=$BUILDPLATFORM ${NODE_IMAGE} AS assets

WORKDIR /app
COPY package.json package-lock.json ./
# The build needs no install scripts.
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY . .
# Tailwind reads the pagination views from vendor/ (resources/css/app.css).
COPY --from=vendor /app/vendor ./vendor
COPY --from=routes /app/resources/js/routes ./resources/js/routes
COPY --from=routes /app/resources/js/wayfinder ./resources/js/wayfinder
RUN WAYFINDER_GENERATE=false npm run build

#-------------------------------------------------------------------------------
# The chat widget (Shakomako), the release in SHAKOMAKO_VERSION, from its own
# repository. Only its build reaches the image, as public/shakomako. Built
# without any settings too: it finds its server from the address its embed.js
# loads from, and gets the WebSocket settings from the widget API.
#-------------------------------------------------------------------------------
FROM --platform=$BUILDPLATFORM ${NODE_IMAGE} AS widget

ARG SHAKOMAKO_REPO
ARG SHAKOMAKO_VERSION

# Without a release, the ADD below would build the widget's default branch.
RUN test -n "$SHAKOMAKO_VERSION" || { \
        echo 'Set the widget release: docker build --build-arg SHAKOMAKO_VERSION=$(npm run -s shakomako:version) ...' >&2; \
        exit 1; \
    }

WORKDIR /shakomako
ADD ${SHAKOMAKO_REPO}#${SHAKOMAKO_VERSION} ./
# --ignore-scripts also skips its husky git-hook setup.
RUN npm ci --ignore-scripts --no-audit --no-fund \
    && npm run build

#-------------------------------------------------------------------------------
# The application.
#-------------------------------------------------------------------------------
FROM php AS app

# - postgresql-client-18: pg_dump for the scheduled backup must match the
#   PostgreSQL 18 server, so it comes from the PostgreSQL apt repository.
#   redis-tools (redis-cli) copies the Redis data into the backup, and gpg
#   encrypts it with the admin's public key (Settings → Backups). gpg is
#   named here so it stays when gnupg goes after the key step.
# - poppler (PDF), pandoc (Word, ODT, EPUB), catdoc (a backup for old .doc
#   and .ppt files) and ffmpeg (audio, video): file sources are read with
#   them. RTF, DOC, PPT, PPTX and XLSX are read in PHP.
# - Python and Pango for WeasyPrint (installed below), which makes the PDF
#   when a visitor exports a chat. The Noto fonts (incl. CJK) give it every
#   script the dashboard languages use.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl gnupg; \
    install -d /usr/share/keyrings; \
    curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /usr/share/keyrings/postgresql.gpg; \
    . /etc/os-release; \
    echo "deb [signed-by=/usr/share/keyrings/postgresql.gpg] https://apt.postgresql.org/pub/repos/apt ${VERSION_CODENAME}-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        postgresql-client-18 \
        redis-tools \
        gpg \
        poppler-utils \
        pandoc \
        catdoc \
        ffmpeg \
        python3 \
        python3-venv \
        libpango-1.0-0 \
        libpangoft2-1.0-0 \
        libharfbuzz-subset0 \
        fonts-dejavu-core \
        fonts-noto-core \
        fonts-noto-cjk; \
    apt-get purge -y gnupg; \
    apt-get autoremove -y; \
    rm -rf /var/lib/apt/lists/*

# WeasyPrint from PyPI in its own virtualenv: Debian's package pulls in numpy,
# scipy and sympy (about 200 MB more) that PDF export never uses.
ARG WEASYPRINT_VERSION=70.0
RUN python3 -m venv /opt/weasyprint \
    && /opt/weasyprint/bin/pip install --no-cache-dir "weasyprint==${WEASYPRINT_VERSION}" \
    && ln -s /opt/weasyprint/bin/weasyprint /usr/local/bin/weasyprint \
    && weasyprint --version

# Container scripts: the entrypoint wrapper, the one-shot commands, a start-up
# hook, nginx settings and the PHP-FPM pool for chat answers.
COPY --chmod=755 docker/app/bin/ /usr/local/bin/
COPY --chmod=755 docker/app/entrypoint.d/ /etc/entrypoint.d/
COPY docker/app/nginx/ /etc/chatvia/nginx/
COPY docker/app/nginx/security.conf /etc/nginx/server-opts.d/security.conf
COPY docker/app/nginx/shakomako.conf /etc/nginx/server-opts.d/shakomako.conf
COPY docker/app/nginx/streams.conf /etc/nginx/server-opts.d/streams.conf
COPY docker/app/php-fpm/chatvia-streams.conf /usr/local/etc/php-fpm.d/chatvia-streams.conf

# The app lives in /var/www/chatvia. The base image's scripts, its artisan
# helper and nginx follow these two settings.
ENV APP_BASE_DIR=/var/www/chatvia \
    NGINX_WEBROOT=/var/www/chatvia/public

# The app folder, and where chatvia-setup keeps the generated secrets (a
# volume in compose). The app folder belongs to root: the code, its packages
# and .env are read-only for the app (www-data). Only storage/ and
# bootstrap/cache/ are its own, so a flaw in the app cannot change the code
# that runs.
RUN install -d -m 0755 /var/www/chatvia \
    && install -d -o www-data -g www-data -m 0755 /var/lib/chatvia \
    && rmdir /var/www/html \
    && chown www-data:www-data /etc/nginx/server-opts.d/security.conf /etc/nginx/server-opts.d/shakomako.conf /etc/nginx/server-opts.d/streams.conf

WORKDIR /var/www/chatvia

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=widget /shakomako/dist ./public/shakomako
# Production defaults. Real environment variables always win over this file.
COPY docker/app/production.env ./.env

# composer dump-autoload runs the package scripts (package discovery and the
# Filament assets). They boot the app, which has no Reverb keys yet (those are
# made per install) and no Redis, so broadcasting and the cache are off for
# this one command. check-platform-reqs fails the build when an extension is
# missing. It runs as root, so the files it writes belong to root too; then
# storage/ and bootstrap/cache/ are handed to www-data.
RUN set -eux; \
    mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs; \
    export COMPOSER_ALLOW_SUPERUSER=1; \
    BROADCAST_CONNECTION=null CACHE_STORE=array composer dump-autoload --optimize --no-dev --no-interaction; \
    composer check-platform-reqs --no-dev; \
    ln -sfn /var/www/chatvia/storage/app/public public/storage; \
    rm -rf storage/framework/views/* storage/logs/*; \
    chown -R www-data:www-data storage bootstrap/cache

USER www-data

# OPcache: the code never changes inside a container, so the base image
# already skips the file checks (validate_timestamps=0). Room for every PHP
# file of the app and its packages (about 30,000), and the tracing JIT for
# the CPU work of the queue workers and the WebSocket server (the CLI uses
# OPcache too). An FPM worker is replaced after 500 requests, so a slow leak
# in a package cannot grow without end.
#
# PHP-FPM has two pools: up to 16 workers for pages and API calls
# (PHP_FPM_PM_MAX_CHILDREN), and up to 32 for chat answers
# (CHATVIA_STREAM_WORKERS, docker/app/php-fpm/chatvia-streams.conf), which
# each keep a worker busy until they end. A busy worker also holds one
# PostgreSQL connection, so 48 fit next to Horizon in the default 100.
ENV AUTORUN_ENABLED=false \
    SHOW_WELCOME_MESSAGE=false \
    PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=65407 \
    PHP_OPCACHE_JIT=tracing \
    PHP_OPCACHE_JIT_BUFFER_SIZE=64M \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=300 \
    PHP_FPM_PM_MAX_CHILDREN=16 \
    PHP_FPM_PM_MAX_REQUESTS=500 \
    CHATVIA_STREAM_WORKERS=32 \
    NGINX_FASTCGI_BUFFER_SIZE=32k \
    NGINX_FASTCGI_BUFFERS="16 32k" \
    CHATVIA_REVERB_UPSTREAM=reverb:8080

ENTRYPOINT ["chatvia-entrypoint"]
CMD ["/init"]
