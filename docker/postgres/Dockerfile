# Chatvia's PostgreSQL image: the official pgvector image (Postgres 18 +
# pgvector) plus Timescale's pg_textsearch extension for BM25 keyword search,
# preloaded by the default command. Published as ghcr.io/chatvia/postgres by
# .github/workflows/postgres-image.yml; used by docker/compose.yaml, the dev
# stack (docker-compose.yml) and CI (.github/workflows/tests.yml).
FROM pgvector/pgvector:pg18

ARG PG_TEXTSEARCH_VERSION=1.5.1
ARG TARGETARCH

ADD https://github.com/timescale/pg_textsearch/releases/download/v${PG_TEXTSEARCH_VERSION}/pg-textsearch-v${PG_TEXTSEARCH_VERSION}-pg18-${TARGETARCH}.zip /tmp/pg_textsearch.zip

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && unzip -o /tmp/pg_textsearch.zip -d /tmp/pg_textsearch \
    && dpkg -i /tmp/pg_textsearch/*.deb \
    && apt-get purge -y unzip \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /tmp/pg_textsearch /tmp/pg_textsearch.zip

CMD ["postgres", "-c", "shared_preload_libraries=pg_textsearch,pg_stat_statements"]
