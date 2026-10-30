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
#   where to sign in. When ChatVia.ai handles HTTPS (--https builtin), it
#   also runs Caddy, which gets the certificates, also for agents' custom
#   domains. Run it again in the same folder (or ./manage upgrade) to
#   upgrade. .env always names one exact version, like 1.4.2: the
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
#   --news              get ChatVia.ai news at the admin e-mail  CHATVIA_NEWS=yes
#   --no-news           do not, without asking            CHATVIA_NEWS=no
#   -h, --help          show this help
#
# Only with Docker:
#   --dir PATH          install folder               CHATVIA_DIR (~/chatvia)
#   --port PORT         port on this machine         CHATVIA_PORT (8899, or the next free one)
#   --url URL           address people open          CHATVIA_URL (http://localhost:PORT)
#   --https builtin     ChatVia.ai handles HTTPS     CHATVIA_HTTPS=builtin (needs ports 80, 443
#                       and --url https://DOMAIN or --domain DOMAIN)
#   --https self-signed ChatVia.ai handles HTTPS on the IP address, with its own
#                       certificate (browsers warn)  CHATVIA_HTTPS=self-signed
#                       (--url https://IP:PORT; else this server's address on 443)
#   --https proxy       your own proxy handles HTTPS CHATVIA_HTTPS=proxy (the default)
#   CHATVIA_HTTPS=yes or no (the complete installation's values) changes
#   nothing here.
#   --image NAME        image name, without version  CHATVIA_IMAGE (ghcr.io/chatvia/chatvia)
#   --no-pull           use the image already on this machine (a local build)
#   --no-start          upgrade: get the version ready, but start nothing
#
# Only with the complete installation:
#   --domain NAME       domain name or IP address people open   CHATVIA_DOMAIN
#   --no-https          no Let's Encrypt certificate (HTTP only) CHATVIA_HTTPS=no
#   CHATVIA_HTTPS here is yes (the default) or no; builtin, self-signed and
#   proxy are the Docker answers (--https).
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
#   CHATVIA_NEWS_URL              where to send the news subscription
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
# Sent with both: a fixed header that chatvia.ai requires, to turn away stray posts.
readonly INSTALLER_MARKER="1"

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

