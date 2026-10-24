# ChatVia.ai's PostgreSQL image: the official Postgres 18 image plus
# the extensions the app installs (database/migrations/
# 2025_07_12_120000_install_postgres_extensions.php):
#   - vector (pgvector), from the PostgreSQL apt repository the official image
#     already has set up;
#   - pg_textsearch (Timescale) for BM25 keyword search, preloaded by the
#     default command;
#   - pg_trgm, btree_gin, btree_gist and pg_stat_statements, which ship with
#     the official image (contrib).
# Published as ghcr.io/chatvia/postgres by .github/workflows/postgres-image.yml;
# used by docker/compose.yaml, the dev stack (docker-compose.yml) and CI
# (.github/workflows/tests.yml).
#
# The Debian release is pinned: a new one brings a new glibc, whose sort rules
# make Postgres warn about a collation version mismatch on existing databases
# until their text indexes are rebuilt.
FROM postgres:18-trixie

ARG PG_TEXTSEARCH_VERSION=1.5.1
ARG TARGETARCH

ADD https://github.com/timescale/pg_textsearch/releases/download/v${PG_TEXTSEARCH_VERSION}/pg-textsearch-v${PG_TEXTSEARCH_VERSION}-pg18-${TARGETARCH}.zip /tmp/pg_textsearch.zip

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip "postgresql-${PG_MAJOR}-pgvector" \
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
