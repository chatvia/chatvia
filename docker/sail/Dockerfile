# Laravel Sail's PHP 8.5 runtime plus the programs Chatvia runs to read file
# sources and to make PDFs. The base image ("sail") is Sail's own runtime,
# built unchanged from vendor/laravel/sail/runtimes/8.5 by the sail-runtime
# service in docker-compose.yml, so Sail updates arrive with `composer update`.
#
# Development only. The production image is the Dockerfile at the root.
FROM sail

# poppler (PDF), pandoc (Word, ODT, RTF, EPUB, FB2), catdoc (old .doc and
# .ppt), and Pango and fonts for WeasyPrint. ffmpeg and Python come with Sail.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        poppler-utils \
        pandoc \
        catdoc \
        python3-venv \
        libpango-1.0-0 \
        libpangoft2-1.0-0 \
        libharfbuzz-subset0 \
        fonts-dejavu-core \
        fonts-noto-core \
        fonts-noto-cjk \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# WeasyPrint from PyPI in its own virtualenv. Same version as the production
# image (the Dockerfile at the root); keep the two in sync.
ARG WEASYPRINT_VERSION=70.0
RUN python3 -m venv /opt/weasyprint \
    && /opt/weasyprint/bin/pip install --no-cache-dir "weasyprint==${WEASYPRINT_VERSION}" \
    && ln -s /opt/weasyprint/bin/weasyprint /usr/local/bin/weasyprint \
    && weasyprint --version
