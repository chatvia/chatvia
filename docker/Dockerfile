# PostgreSQL 18 with Vector Extensions for ChatVia (Development)
FROM postgres:18

# Extension versions
ENV VCHORDBM25=0.3.0
ENV PGTOKENIZER=0.1.1
ENV VCHORD=1.0.0
ENV PGVECTOR=0.8.1
ENV PG_MAJOR=18

# Default database configuration for development only
# WARNING: These are default development credentials - override in production!
# For production, use Docker secrets or pass credentials via docker-compose.yml
ENV POSTGRES_DB=chatvia
ENV POSTGRES_USER=chatvia
# nosemgrep: dockerfile-source-secret
ENV POSTGRES_PASSWORD=password

# Install dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    wget \
    ca-certificates \
    postgresql-common \
    build-essential \
    gnupg \
    apt-transport-https \
    lsb-release \
    && rm -rf /var/lib/apt/lists/*

# Add PostgreSQL APT repository
RUN /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y

# Add TimescaleDB repository
RUN echo "deb https://packagecloud.io/timescale/timescaledb/debian/ $(lsb_release -c -s) main" > /etc/apt/sources.list.d/timescaledb.list && \
    wget --quiet -O - https://packagecloud.io/timescale/timescaledb/gpgkey | gpg --dearmor -o /etc/apt/trusted.gpg.d/timescaledb.gpg

# Update and install pgvector and TimescaleDB
RUN apt-get update && apt-get install -y --no-install-recommends \
    postgresql-${PG_MAJOR}-pgvector=${PGVECTOR}-* \
    timescaledb-2-postgresql-${PG_MAJOR} \
    && rm -rf /var/lib/apt/lists/*

# Download and install VectorChord-bm25
RUN wget https://github.com/tensorchord/VectorChord-bm25/releases/download/${VCHORDBM25}/postgresql-${PG_MAJOR}-vchord-bm25_${VCHORDBM25}-1_$(dpkg --print-architecture).deb -P /tmp && \
    apt-get install -y /tmp/postgresql-${PG_MAJOR}-vchord-bm25_${VCHORDBM25}-1_$(dpkg --print-architecture).deb && \
    rm /tmp/postgresql-${PG_MAJOR}-vchord-bm25_${VCHORDBM25}-1_$(dpkg --print-architecture).deb

# Download and install pg_tokenizer
RUN wget https://github.com/tensorchord/pg_tokenizer.rs/releases/download/${PGTOKENIZER}/postgresql-${PG_MAJOR}-pg-tokenizer_${PGTOKENIZER}-1_$(dpkg --print-architecture).deb -P /tmp && \
    apt-get install -y /tmp/postgresql-${PG_MAJOR}-pg-tokenizer_${PGTOKENIZER}-1_$(dpkg --print-architecture).deb && \
    rm /tmp/postgresql-${PG_MAJOR}-pg-tokenizer_${PGTOKENIZER}-1_$(dpkg --print-architecture).deb

# Download and install VectorChord
RUN wget https://github.com/tensorchord/VectorChord/releases/download/${VCHORD}/postgresql-${PG_MAJOR}-vchord_${VCHORD}-1_$(dpkg --print-architecture).deb -P /tmp && \
    apt-get install -y /tmp/postgresql-${PG_MAJOR}-vchord_${VCHORD}-1_$(dpkg --print-architecture).deb && \
    rm /tmp/postgresql-${PG_MAJOR}-vchord_${VCHORD}-1_$(dpkg --print-architecture).deb

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Copy initialization scripts
COPY init-extensions.sql /docker-entrypoint-initdb.d/01-init-extensions.sql

# Enable shared preload libraries for extensions
RUN echo "shared_preload_libraries = 'timescaledb,vchord,vchord_bm25,vector,pg_tokenizer,pg_stat_statements'" >> /usr/share/postgresql/postgresql.conf.sample

# Expose PostgreSQL port
EXPOSE 5432

