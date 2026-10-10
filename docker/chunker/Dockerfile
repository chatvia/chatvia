# syntax=docker/dockerfile:1

# The text splitter of the Chatvia stack: chunk.fit's open-source chunker
# (https://github.com/chatvia/chunker), a FastAPI app. That repository has no
# Dockerfile, so this one builds it from a pinned commit. Chatvia calls it on
# the Docker network at http://chunker:8000/api/v1 without an API key; the
# port is not published outside that network.
#
# Build:   docker build -t chatvia-chunker docker/chunker
# Update:  set CHUNKER_REF to a newer commit of github.com/chatvia/chunker.
# Workers: WEB_CONCURRENCY (uvicorn), 2 by default.

ARG PYTHON_IMAGE=python:3.14-slim-trixie
ARG UV_IMAGE=ghcr.io/astral-sh/uv:0.13.0

FROM ${UV_IMAGE} AS uv

#-------------------------------------------------------------------------------
# Dependencies, from the chunker's own lockfile.
#-------------------------------------------------------------------------------
FROM ${PYTHON_IMAGE} AS build

ARG CHUNKER_REF=f205fff739e8c06fe0d86256107f66d60ede0e8d

COPY --from=uv /uv /usr/local/bin/uv

ENV UV_COMPILE_BYTECODE=1 \
    UV_LINK_MODE=copy \
    UV_PYTHON_DOWNLOADS=never \
    NLTK_DATA=/opt/chunker/nltk_data

WORKDIR /opt/chunker
ADD https://github.com/chatvia/chunker.git#${CHUNKER_REF} .
RUN uv sync --frozen --no-dev

# The language data unstructured's text partitioner loads, saved where the
# service looks (NLTK_DATA), so the container needs no internet when it splits
# text. nltk.download() would otherwise write to the build user's home folder.
# Importing the app also fills the caches the libraries create on first use.
RUN .venv/bin/python -c "import nltk; [nltk.download(p, download_dir='${NLTK_DATA}', quiet=True, raise_on_error=True) for p in ('punkt_tab', 'averaged_perceptron_tagger_eng')]" \
    && .venv/bin/python -c "import main"

#-------------------------------------------------------------------------------
# The service.
#-------------------------------------------------------------------------------
FROM ${PYTHON_IMAGE}

ENV PATH=/opt/chunker/.venv/bin:$PATH \
    NLTK_DATA=/opt/chunker/nltk_data \
    PYTHONUNBUFFERED=1 \
    WEB_CONCURRENCY=2

RUN useradd --system --uid 10001 --no-create-home --shell /usr/sbin/nologin chunker

WORKDIR /opt/chunker
COPY --from=build /opt/chunker /opt/chunker

USER chunker
EXPOSE 8000

HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=5 \
    CMD ["python", "-c", "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8000/api/v1/health', timeout=4)"]

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
