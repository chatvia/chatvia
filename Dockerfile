FROM tensorchord/vchord-suite:pg17-latest

# Install dependencies for compiling AGE from source
RUN apt-get update && apt-get install -y \
    git \
    build-essential \
    postgresql-server-dev-17 \
    libpq-dev \
    make \
    gcc \
    flex \
    bison \
    && rm -rf /var/lib/apt/lists/*

# Check PostgreSQL version and pg_config
RUN pg_config --version

# Clone Apache AGE repository
RUN git clone https://github.com/apache/age.git \
    && cd age \
    && git checkout PG17/v1.5.0 \
    && make PG_CONFIG=/usr/bin/pg_config install \
    && cd .. \
    && rm -rf age

# Load AGE extension
RUN echo "shared_preload_libraries = 'age'" >> /usr/share/postgresql/postgresql.conf.sample
