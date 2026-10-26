# ChatVia.ai's PostgreSQL image: the official Postgres 18 image plus
# the extensions the app installs (database/migrations/
# 2025_07_12_120000_install_postgres_extensions.php):
#   - vector (pgvector), from the PostgreSQL apt repository the official image
#     already has set up, pinned;
#   - pg_textsearch (Timescale) for BM25 keyword search, preloaded by the
#     default command;
#   - pg_partman, which splits analytics_events into monthly partitions and
#     drops the expired ones (the analytics prune runs its maintenance, so it
#     needs no preloaded background worker), from the PostgreSQL apt
#     repository too, pinned like pg_textsearch;
#   - pg_trgm, btree_gin, btree_gist and pg_stat_statements, which ship with
#     the official image (contrib).
# Published as ghcr.io/chatvia/postgres by .github/workflows/postgres-image.yml
# as 18-r<IMAGE_REVISION> and the moving 18. Installs (docker/compose.yaml)
# use 18, so they always get the newest build; the dev stack
# (docker-compose.yml) and CI (.github/workflows/tests.yml) pin 18-rN. The
# tag names our build, not the extensions: their versions are image labels
# (docker inspect). Bump IMAGE_REVISION on every change to this file, then the
# two pins; the workflow refuses to overwrite a published tag.
#
# The Debian release is pinned: a new one brings a new glibc, whose sort rules
# make Postgres warn about a collation version mismatch on existing databases
# until their text indexes are rebuilt.
FROM postgres:18-trixie

ARG IMAGE_REVISION=1
ARG PGVECTOR_VERSION=0.8.7
ARG PG_TEXTSEARCH_VERSION=1.5.1
ARG PG_PARTMAN_VERSION=5.5.0
ARG TARGETARCH

LABEL org.opencontainers.image.title="ChatVia.ai PostgreSQL" \
      org.opencontainers.image.version="18-r${IMAGE_REVISION}" \
      ai.chatvia.pgvector.version="${PGVECTOR_VERSION}" \
      ai.chatvia.pg_textsearch.version="${PG_TEXTSEARCH_VERSION}" \
      ai.chatvia.pg_partman.version="${PG_PARTMAN_VERSION}"

ADD https://github.com/timescale/pg_textsearch/releases/download/v${PG_TEXTSEARCH_VERSION}/pg-textsearch-v${PG_TEXTSEARCH_VERSION}-pg18-${TARGETARCH}.zip /tmp/pg_textsearch.zip

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip "postgresql-${PG_MAJOR}-pgvector=${PGVECTOR_VERSION}-*" "postgresql-${PG_MAJOR}-partman=${PG_PARTMAN_VERSION}-*" \
    && unzip -o /tmp/pg_textsearch.zip -d /tmp/pg_textsearch \
    && dpkg -i /tmp/pg_textsearch/*.deb \
    && apt-get purge -y unzip \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /tmp/pg_textsearch /tmp/pg_textsearch.zip

# More lock slots than the default 64 per connection: the test suite runs in
# parallel (one database per worker), and a test transaction that touches many
# tables holds a lock on each. The slots are shared by every database on the
# server, so several test runs at once used them all up ("out of shared
# memory"). 256 costs a few MB of shared memory.
CMD ["postgres", "-c", "shared_preload_libraries=pg_textsearch,pg_stat_statements", "-c", "max_locks_per_transaction=256"]
