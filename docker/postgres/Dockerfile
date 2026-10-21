# ChatVia.ai's PostgreSQL image: the official Postgres 18 image (Debian) plus
# the extensions the app installs (database/migrations/
# 2025_06_28_000000_install_postgres_extensions.php):
#   - vector (pgvector), from the PostgreSQL apt repository the official image
#     already has set up;
#   - pg_textsearch (Timescale) for BM25 keyword search, preloaded by the
#     default command;
#   - pg_trgm, btree_gin, btree_gist and pg_stat_statements, which ship with
#     the official image (contrib).
# Published as ghcr.io/chatvia/postgres by .github/workflows/postgres-image.yml;
# used by docker/compose.yaml, the dev stack (docker-compose.yml) and CI
# (.github/workflows/tests.yml).
FROM postgres:18

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

CMD ["postgres", "-c", "shared_preload_libraries=pg_textsearch,pg_stat_statements"]
