FROM pgvector/pgvector:0.8.0-pg17

# Install required system dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    git \
    curl \
    cmake \
    libpq-dev \
    postgresql-server-dev-all \
    pkg-config \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install Rust (via rustup) with specific toolchain
RUN curl https://sh.rustup.rs -sSf | sh -s -- -y --default-toolchain stable
ENV PATH="/root/.cargo/bin:${PATH}"

# Verify Rust installation
RUN rustc --version && cargo --version

RUN cargo install cargo-pgrx --version 0.14.1

# Initialize pgrx for PostgreSQL 17
RUN cargo pgrx init --pg17 $(which pg_config)

# Clone and build pg_tokenizer with proper PostgreSQL configuration
RUN git clone https://github.com/tensorchord/pg_tokenizer.rs /tmp/pg_tokenizer \
    && cd /tmp/pg_tokenizer \
    && mkdir -p /usr/share/postgresql/17/extension \
    && mkdir -p /usr/lib/postgresql/17/lib \
    && cargo pgrx install --release \
    && rm -rf /tmp/pg_tokenizer

# Clone and build VectorChord-bm25 with proper PostgreSQL configuration
RUN git clone https://github.com/tensorchord/VectorChord-bm25.git /tmp/VectorChord-bm25 \
    && cd /tmp/VectorChord-bm25 \
    && cargo pgrx install --release \
    && rm -rf /tmp/VectorChord-bm25

# PostgreSQL configuration will be handled via ALTER SYSTEM commands in init script

# Verify extensions are installed
RUN ls -la /usr/share/postgresql/17/extension/ | grep -E "(pg_tokenizer|vectorchord)"

# Clean up cargo cache to reduce image size
RUN rm -rf /root/.cargo/registry

# Create database initialization script
RUN mkdir -p /docker-entrypoint-initdb.d
COPY <<EOF /docker-entrypoint-initdb.d/01-setup-extensions.sql
DO \$\$
DECLARE
    current_libs text;
BEGIN
    SELECT setting INTO current_libs FROM pg_settings WHERE name = 'shared_preload_libraries';
    IF current_libs NOT LIKE '%pg_tokenizer.so%' THEN
        IF current_libs = '' THEN
            PERFORM pg_reload_conf();
            EXECUTE 'ALTER SYSTEM SET shared_preload_libraries = ''pg_tokenizer.so''';
        ELSE
            PERFORM pg_reload_conf();
            EXECUTE format('ALTER SYSTEM SET shared_preload_libraries = ''%s,pg_tokenizer.so''', current_libs);
        END IF;
    END IF;
END \$\$;

ALTER SYSTEM SET search_path TO "\$user", public, tokenizer_catalog;

SELECT pg_reload_conf();

CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_tokenizer;
CREATE EXTENSION IF NOT EXISTS vchord_bm25;
EOF
