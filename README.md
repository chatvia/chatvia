<p align="center">
  <img src="public/images/logo.svg" alt="ChatVia.ai logo" width="96" height="96">
</p>

<h1 align="center">ChatVia.ai</h1>

<p align="center">
  An open-source platform for AI chat agents that answer from your own knowledge base.
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT License"></a>
  <img src="https://img.shields.io/badge/PHP-8.5-777bb4.svg" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/Laravel-13-ff2d20.svg" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PostgreSQL-18-336791.svg" alt="PostgreSQL 18">
</p>

---

ChatVia.ai lets a team create AI agents, give them knowledge (files, web pages,
text and Q&A pairs), connect them to tools, and put them in
front of people: on a hosted chat page, in a widget on any website, in a
command bar, through messaging apps, or through a REST API. Answers come from
retrieval-augmented generation (RAG) with hybrid search (vectors + keywords)
inside PostgreSQL. It is built with Laravel, Inertia and Vue, and is designed to work
with open-weight models.

## Contents

- [Features](#features)
- [How it is built](#how-it-is-built)
- [Requirements](#requirements)
- [Getting started](#getting-started)
- [Running the app](#running-the-app)
- [Develop with Laravel Sail](#develop-with-laravel-sail)
- [Tests and checks](#tests-and-checks)
- [The chat widget](#the-chat-widget)
- [Documentation site](#documentation-site)
- [Going to production](#going-to-production)
- [Test the Docker install](#test-the-docker-install)
- [Contributing, security and license](#contributing-security-and-license)

## Features

**Agents**
- Persona and instructions, model settings, branding, conversation starters
  and a playground to test changes.
- Guardrails for prompt injection, off-topic questions, toxic content and
  personal data.
- Evaluations: test sets that score an agent's answers with an LLM judge.

**Knowledge base**
- Sources: documents (PDF, Word, PowerPoint, Excel, CSV, RTF, text, Markdown,
  EPUB), images (OCR), audio and video (transcription), web pages, text
  snippets and Q&A pairs. (YouTube sources are experimental and hidden unless
  `FEATURE_YOUTUBE_SOURCES=true`; videos are not ingested yet.)
- Ingestion runs in background queues and reports progress live.
- Hybrid search: vector search ([pgvector](https://github.com/pgvector/pgvector) HNSW)
  and keyword search ([pg_textsearch](https://github.com/timescale/pg_textsearch) BM25,
  falling back to PostgreSQL's own full-text search), merged and reranked.

**Tools**
- Functions: call your own HTTP endpoints (webhooks).
- MCP: connect any Model Context Protocol server, or a ready-made connector
  that signs in with OAuth.
- Approval step for risky tool calls, rate limits and an audit log of every
  tool call.

**Where people chat**
- Hosted chat page, embeddable chat widget and command bar.
- Messaging channels: Slack, Microsoft Teams, Telegram, WhatsApp, Messenger
  and Instagram.
- Public REST API with bearer tokens. OpenAPI docs are served at `/docs/api`.

**Conversations**
- Chat logs, leads, analytics and topics, user feedback, human handoff,
  forms inside the chat, and export to PDF.

**Workspaces**
- Teams with roles and permissions, sign-in with two-factor authentication,
  passkeys or social login, and workspace security settings (for example an
  IP allowlist).
- Every workspace gets every feature. Optional monthly message limits: per
  workspace (set in the admin panel) and per chatter (set in the dashboard).
- An admin panel and a dashboard translated into many languages, including
  right-to-left languages.

## How it is built

| Part | Technology |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Inertia, Vue 3, TypeScript, Tailwind CSS 4, shadcn-vue, Vite |
| Database | PostgreSQL 18 with pgvector; `pg_textsearch` for BM25 keyword search where available |
| Queues and cache | Redis, Laravel Horizon |
| Real-time updates | Laravel Reverb (WebSockets) and server-sent events for streamed answers |
| Admin panel | Filament |
| Tests | Pest |

The PHP code is split into three namespaces:

- `src/App/` (`App\`): the HTTP layer and framework wiring, plus
  `App\Identity` (accounts and sign-in).
- `src/Domain/` (`Domain\`): business logic, one folder per module
  (`Agent`, `Conversation`, `Knowledge`, `Integration`, `Channel`, ...).
  State changes go through Actions that take DTOs.
- `src/Support/` (`Support\`): small shared helpers.

The frontend lives in `resources/js/`, and the embeddable widget in its own
repository, [chatvia/shakomako](https://github.com/chatvia/shakomako). The
user guide is at <https://chatvia.ai/docs/>. [ARCHITECTURE.md](ARCHITECTURE.md) explains
how the code is organised and the conventions; [CONTRIBUTING.md](CONTRIBUTING.md)
explains how to work on it.

## Requirements

- **PHP 8.5** with these extensions: `dom`, `fileinfo`, `intl`, `libxml`,
  `openssl` and `pdo` (required by `composer.json`), plus `pdo_pgsql`, `redis`,
  `mbstring`, `curl`, `zip`, `gd`, `exif` and `pcntl`.
- **Composer 2**
- **Node.js 22.18** or newer, npm and **git** (to download and build the chat
  widget)
- **PostgreSQL 17 or 18** with [pgvector](https://github.com/pgvector/pgvector)
  (`vector`). [pg_textsearch](https://github.com/timescale/pg_textsearch) is
  recommended for true BM25 keyword ranking, but optional: it must be preloaded
  into the server, so on a managed database (where that is not possible)
  keyword search falls back to PostgreSQL's built-in full-text search
  (`SEARCH_LEXICAL_DRIVER=auto` picks this up). The easiest way is the
  `ghcr.io/chatvia/postgres` Docker image (see below).
- **Redis** 7 or newer
- **System tools** for some file types. They are all optional: without one,
  the app does its work itself, or refuses only the files it cannot read. So
  ChatVia.ai also runs on a host that does not let you install programs:
  - [pandoc](https://pandoc.org/): EPUB books and Word (`.docx`) files. Without it, the app reads
    them itself. The app reads RTF files itself; pandoc is only a backup for them.
  - [catdoc](https://www.wagner.pp.ru/~vitus/software/catdoc/) (`catdoc`, `catppt`): only a backup
    for old Word (`.doc`) and PowerPoint (`.ppt`) files, which the app reads itself. PowerPoint
    (`.pptx`) and Excel (`.xlsx`) files need no extra program either.
  - [poppler](https://poppler.freedesktop.org/) (`pdfinfo`, `pdftotext`): PDF files. `pdfinfo`
    counts their pages and finds locked ones; without it, the app does this itself. `pdftotext` is
    only a backup: the document reader reads PDF files.
  - [ffmpeg](https://ffmpeg.org/) (`ffmpeg`, `ffprobe`): makes audio and video small enough for
    speech to text. Without it, only recordings the speech-to-text service takes as they are (such
    as an MP3 under 25 MB) can be uploaded. Without `ffprobe`, the app measures recordings itself.
  - [WeasyPrint](https://weasyprint.org/) (`weasyprint`): exporting a chat as PDF, and summaries
    of office files. Without it, pick Cloudflare Browser Rendering or a
    [Gotenberg](https://gotenberg.dev/) server as the PDF maker in the admin panel
    (Settings → Platform → PDF maker).

  If a tool is not on the `PATH` of the web server or the queue worker (for
  example Homebrew's `/opt/homebrew/bin` on a Mac), set its full path in the
  admin panel: **Settings → Platform → Programs**.

**External AI services.** ChatVia.ai calls AI models through OpenAI-compatible
APIs. You need a chat provider and an embedding provider. The default
configuration uses Scaleway's Generative APIs with open-weight models (Qwen3
and others) for chat and embeddings. Other chat providers: OpenRouter,
OpenAI, Mistral, Google Gemini, Groq, or your own OpenAI-compatible server.
OpenRouter, OpenAI, Mistral and Google Gemini make embeddings with the same
key.
Reranking uses OpenRouter or Cohere. PDF, Word, slides and images are read by
Mistral OCR or Azure Document Intelligence; without one of them, those files
cannot be uploaded. Text is split into chunks inside ChatVia.ai, by tokens or by
characters (**Settings → Splitting**). Website import uses Firecrawl. Each
API key is entered once in the admin panel (**Settings → AI providers**), and
the models in **Settings → AI models**, not in `.env`; see
[AI providers](https://chatvia.ai/docs/guide/installation#6-add-your-api-keys).

## Getting started

### 1. Start PostgreSQL, Redis and Mailpit with Docker

The repository has a `docker-compose.yml` for the services the app needs. The
app itself runs on your machine. To run the app in Docker as well, skip to
[Develop with Laravel Sail](#develop-with-laravel-sail).

```bash
docker compose up -d
docker compose ps        # wait until the services are "healthy"
```

| Service | Address | Notes |
|---|---|---|
| PostgreSQL 18 | `127.0.0.1:5432` | user `chatvia`, password `password`, databases `chatvia` and `testing` |
| Redis | `127.0.0.1:6379` | no password |
| Mailpit | SMTP `127.0.0.1:1025`, web UI <http://localhost:8025> | catches all e-mail |

These credentials are for local development only. If a port is already in
use, change it with `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`,
`FORWARD_MAILPIT_PORT` or `FORWARD_MAILPIT_DASHBOARD_PORT`, for example
`FORWARD_DB_PORT=5433 docker compose up -d`. `docker compose down -v` deletes
all data.

<details>
<summary><strong>Without Docker</strong></summary>

1. Install PostgreSQL 17 or 18 with pgvector, and (recommended) the
   `pg_textsearch` extension from its
   [releases](https://github.com/timescale/pg_textsearch/releases). On a
   managed database without it, keyword search uses PostgreSQL's built-in
   full-text search instead.
2. With pg_textsearch, load it in `postgresql.conf` and restart PostgreSQL:

   ```
   shared_preload_libraries = 'pg_textsearch'
   ```

3. Create a role and the two databases. The first migration runs
   `CREATE EXTENSION`, so the role needs the rights to do that (in development,
   a superuser is simplest):

   ```sql
   CREATE ROLE chatvia LOGIN SUPERUSER PASSWORD 'password';
   CREATE DATABASE chatvia OWNER chatvia;
   CREATE DATABASE testing OWNER chatvia;
   ```

4. Install Redis 7 or newer. For e-mail, use Mailpit or set `MAIL_MAILER=log`.

</details>

### 2. Install the app

```bash
git clone <repository-url> chatvia
cd chatvia

composer install
npm ci
npm run shakomako:build

cp .env.example .env
php artisan key:generate
```

`npm run shakomako:build` downloads and builds the chat widget into
`public/shakomako`; see [The chat widget](#the-chat-widget).

### 3. Configure `.env`

`.env.example` lists what the app needs to start, each with a short comment.
Everything else (API keys, AI services, mail, storage, brand) is set later in
the admin panel, under **Settings**. The default values in `.env.example`
work with the Docker services above:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chatvia
DB_USERNAME=chatvia
DB_PASSWORD=password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

The `MAIL_*` lines are only a preset for the Mailpit Docker service. The
normal place for e-mail is the admin panel (**Settings → Email**); a value
saved there wins over `.env`.

Redis keeps the queues, the cache, the chat memory and the sign-in sessions.
Each one has its own Redis database (`REDIS_DB`, `REDIS_CACHE_DB`,
`REDIS_CHAT_DB` and `REDIS_SESSION_DB`), so clearing the cache does not sign
anyone out.

Then set `BUILTIN_MCP_SECRET` to a long random string (the built-in tools need
it).

**Before the first migration**, decide how the database stores embeddings:
`EMBEDDING_VECTOR_TYPE` (`halfvec`, the default, or `vector`) and
`EMBEDDING_DIMENSIONS` (default `1024`). The tables are created with these
values, so you cannot change them later. The app stops with an error when they
no longer match the database (`php artisan chatvia:search:check-embedding-storage`).

Sign-up, the message limit of new workspaces and how long conversations are
kept are set later in the admin panel (**Settings → Workspaces**), not in
`.env`.

### 4. Create the database schema and demo data

```bash
php artisan chatvia:db:create-tool-databases
php artisan migrate --seed
php artisan storage:link
```

The first command creates the databases of Pulse and Spectra
(`DB_PULSE_DATABASE` and `DB_SPECTRA_DATABASE`, by default `pulse` and
`spectra`) when they are missing; the Docker services create them on their
first start. The first migration installs the PostgreSQL extensions. The
seeders load the list of AI models and, outside production, create a demo
admin account (`SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`, by default
`admin@example.com` / `password`) with sample data; see
`database/seeders/DatabaseSeeder.php`.

### 5. Add your API keys

Sign in to the admin panel at `/admin` with the demo admin account and open
**Settings → AI providers**. Enter the key of your chat and embedding
provider (with the defaults, one Scaleway key covers both). Then add the
built-in tools (charts, forms, weather, currency, web search) to the tool
catalog:

```bash
php artisan chatvia:mcp:sync-builtin
```

## Running the app

During development you need these processes:

| Process | Command | What it does |
|---|---|---|
| Web server | `php artisan serve` (or Herd / Valet) | serves the app |
| Vite | `npm run dev` | builds the frontend with hot reload |
| Queues | `php artisan horizon` | runs background jobs (ingestion, AI replies on channels, analytics, exports) |
| WebSockets | `php artisan reverb:start` | live updates, for example ingestion progress |
| Scheduler | `php artisan schedule:work` | cleanup and other scheduled tasks |
| Server stats (optional) | `php artisan pulse:check` | CPU, memory and disk on Pulse's Servers card (`/pulse`) |

`composer dev` starts the web server, a simple queue worker, the log viewer
(`php artisan pail`) and Vite together. Its worker only reads the `default`
queue, so start `php artisan horizon` as well when you work on ingestion,
channels or exports: jobs use several named queues (see `config/horizon.php`).

**Messaging channels on your machine.** Slack, Teams, Telegram, WhatsApp,
Messenger and Instagram can only call a public HTTPS URL. Use a tunnel (for
example [cloudflared](https://github.com/cloudflare/cloudflared)) to your local
site, point the channel's webhook at the tunnel URL, and keep Horizon running,
because replies are sent from queued jobs. Messaging channels only work with
public agents.

## Develop with Laravel Sail

[Laravel Sail](https://laravel.com/docs/sail) runs everything in Docker: the
app, Horizon, the scheduler, Reverb and Pulse's server stats, next to the
PostgreSQL, Redis and Mailpit services above. You only need Docker. The image
has every system tool (pandoc, catdoc, poppler, ffmpeg, WeasyPrint) installed.

Sail is for development only. It uses `docker-compose.yml` (project
`chatvia-dev`) and `docker/sail/Dockerfile`; the production image, its compose
file and CI do not use either.

1. Install the PHP packages. With PHP on your machine, run `composer install`.
   Without it, use Composer's Docker image:

   ```bash
   docker run --rm -v "$(pwd):/app" -w /app composer:2 install --ignore-platform-reqs --no-scripts
   ```

2. Create `.env` and turn Sail on:

   ```bash
   cp .env.example .env
   ```

   In `.env`, uncomment `COMPOSE_PROFILES=sail` and `APP_PORT=8000`. The
   `postgres` container creates its database, user and password from
   `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` the first time it starts.

3. Start the containers. The first start builds the image, which takes a few
   minutes:

   ```bash
   vendor/bin/sail up -d
   ```

4. Set up the app inside the containers:

   ```bash
   vendor/bin/sail composer install
   vendor/bin/sail artisan key:generate
   vendor/bin/sail npm ci
   vendor/bin/sail npm run shakomako:build
   vendor/bin/sail artisan chatvia:db:create-tool-databases
   vendor/bin/sail artisan migrate --seed
   vendor/bin/sail artisan storage:link
   ```

   Set `BUILTIN_MCP_SECRET` in `.env`, run `vendor/bin/sail restart`, then
   add your API keys as in [step 5](#5-add-your-api-keys), with
   `vendor/bin/sail artisan chatvia:mcp:sync-builtin`.

5. Start Vite and open <http://localhost:8000>:

   ```bash
   vendor/bin/sail npm run dev
   ```

| Container | What it runs | On your machine |
|---|---|---|
| `laravel.test` | the web server, and Vite when you start it | <http://localhost:8000>, Vite on port 5173 |
| `horizon` | queued jobs | |
| `scheduler` | scheduled tasks | |
| `reverb` | WebSockets | `ws://localhost:8080` |
| `pulse` | Pulse's server stats (`pulse:check`) | |
| `postgres`, `redis`, `mailpit` | as above | as above |

### Where the settings live

**In `.env`.** You set these:

- The database: `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`. The
  `postgres` container creates this database and user the first time it
  starts. If you change them later, the existing database keeps the old values;
  `vendor/bin/sail down -v` deletes all data, so the next start creates them
  again. The tests expect the defaults (`chatvia` / `password`, see
  `phpunit.xml`).
- Sail and the ports on your machine: `COMPOSE_PROFILES`, `APP_PORT`,
  `FORWARD_REVERB_PORT`, `VITE_PORT` and the `FORWARD_*_PORT` settings of the
  services.
- Everything else, as without Sail (`APP_KEY`, `BUILTIN_MCP_SECRET` and so on).

**In `docker-compose.yml`.** These are set for you. Do not copy them into
`.env`:

- How the containers find each other: `DB_HOST=postgres`, `REDIS_HOST=redis`,
  `MAIL_HOST=mailpit`, `REVERB_HOST=reverb` and
  `BUILTIN_MCP_INTERNAL_URL=http://laravel.test`.
- The addresses your browser uses: `APP_URL` (`http://localhost:` and
  `APP_PORT`) and the Reverb address (`localhost:` and `FORWARD_REVERB_PORT`).

Inside the containers, these values win over `.env`. So one `.env` works with
Sail and without it: `.env` keeps `DB_HOST=127.0.0.1` for when the app runs on
your machine. After you change `.env`, run `vendor/bin/sail restart`.

### Good to know

- Horizon loads the code once. After you change code that runs in a queued
  job, run `vendor/bin/sail restart horizon`.
- `node_modules` is shared with your machine, and some packages are built for
  one system. After `sail npm ci`, run `npm ci` again before you use
  `npm run dev` outside Sail.
- Do not run Horizon or Reverb on your machine at the same time: two workers
  would take jobs from the same Redis. If port 8080 or 5173 is in use (for
  example by Herd's Reverb), set `FORWARD_REVERB_PORT` or `VITE_PORT`.
- Tests: `vendor/bin/sail artisan test --compact`. Stop everything with
  `vendor/bin/sail down` (your data is kept).

## Tests and checks

```bash
composer test                                  # the whole Pest suite
php artisan test --compact tests/Unit/Agent    # one folder or file
composer ci:check                              # every CI gate (see below)
```

- Tests run against PostgreSQL with the same extensions as the app, using the
  `testing` database on `127.0.0.1:5432` (user `chatvia`, password `password`;
  see `phpunit.xml`). The Docker setup creates this database for you. On the
  first run, the suite installs the extensions, migrates and seeds it; when
  migrations change, it rebuilds the schema by itself.
- Run the suite in one process, as `composer test` does. With `--parallel`,
  every process builds its own test database at the same time, and PostgreSQL
  runs out of locks (`max_locks_per_transaction`).
- `composer ci:check` runs, without changing any file: `composer validate`,
  `composer audit`, `npm audit` (production dependencies), Pint, Rector,
  PHPStan, Prettier, ESLint, `vue-tsc`, the i18n check and the test suite. CI
  runs the same command. `composer ci` fixes formatting and lint problems
  first, then runs the checks.

## The chat widget

The embeddable widget (chat bubble and command bar) is called Shakomako. It
lives in its own repository,
[chatvia/shakomako](https://github.com/chatvia/shakomako). The app serves a
built copy from `public/shakomako`. That folder is in `.gitignore`, so a fresh
clone has no widget: build it once after you clone, and again when the widget
has a new release.

### Build the widget

```bash
npm run shakomako:build
```

This command needs git and Node. It:

1. finds the newest release of the widget: the highest `vX.Y.Z` tag in its
   repository;
2. downloads that release into `storage/framework/shakomako/<version>`. It
   does this only once per release, and keeps only the newest one there;
3. installs the widget's packages and builds it;
4. replaces `public/shakomako` with the new build.

The version is not written anywhere in this repository. To download a release
again, delete `storage/framework/shakomako`.

### Which release is built

The build always uses a release tag, never unfinished work:

- Only full releases count (`v3.1.0`). Pre-release tags (`v3.1.0-beta.1`) and
  commits on the widget's `main` branch are never built.
- Tags are compared as numbers, so `v3.10.0` is newer than `v3.9.0`.

To see which release the build would use, or to build a chosen one (for
example to skip a broken release):

```bash
npm run -s shakomako:version
```

```bash
SHAKOMAKO_VERSION=v3.0.0 npm run shakomako:build
```

**Docker.** The `Dockerfile` has no default widget version, so you pass it in
when you build the image. The release workflow (`.github/workflows/docker.yml`)
does this for every ChatVia.ai release, and shows the version in the job summary.
To build the image yourself:

```bash
docker build --build-arg SHAKOMAKO_VERSION=$(npm run -s shakomako:version) -t chatvia .
```

Without the version, the build stops with an error. The image cannot find the
newest release by itself: Docker would reuse that step from its cache and keep
an old widget.

### Try widget changes that have no release yet

Clone the widget next to this repository and build your own copy:

```bash
git clone https://github.com/chatvia/shakomako.git ../shakomako
```

```bash
SHAKOMAKO_PATH=../shakomako npm run shakomako:build
```

Run the second command again after each change to the widget. It installs the
widget's packages only when its `node_modules` folder is missing, so run
`npm ci` in the widget folder when its packages change. The build reads the
widget's `.env` file, and those values end up in the built files. The command
shows a warning when that file exists. A copy for a server needs a build
without it.

### Ship a widget change

1. When the widget change also needs a server change (the `/widget` API, the
   `WidgetHeaders` names or the widget settings response), merge the server
   change here **first**. Every build takes the newest widget release, so the
   server must be ready before the release exists.
2. In the widget repository, tag a release: `npm version X.Y.Z`, then
   `git push --follow-tags` (see its `VERSIONING.md`).
3. Run `npm run shakomako:build` to use it on your machine. The next ChatVia.ai
   release image takes it by itself.

### Serve the widget

- The embed snippets in the dashboard point to
  `{APP_URL}/shakomako/embed.js`. To serve the widget from another place, for
  example a CDN, set **Widget script address** in the admin panel
  (**Settings → Platform**).
- Other websites load these files, so the web server must allow any origin
  for `/shakomako/`. The Docker image does this for you; for nginx without
  Docker, see the [installation guide](https://chatvia.ai/docs/guide/installation).

See [CONTRIBUTING.md](CONTRIBUTING.md) to work on the widget and the app
together.

## Documentation site

The user guide is at <https://chatvia.ai/docs/>. It is not in this
repository.

## Going to production

The installer asks a few questions and starts everything:

```bash
curl -fsSL https://chatvia.ai/install.sh | bash
```

On Linux it asks how to install ChatVia.ai (macOS always uses Docker):

- **With Docker:** it checks Docker and runs ChatVia.ai in containers. See
  [Run with Docker](https://chatvia.ai/docs/guide/installation/docker).
- **Complete installation:** everything directly on a fresh Ubuntu 26.04
  LTS server, without Docker. It installs PostgreSQL 18 with the search
  extensions, Redis, PHP-FPM, nginx, a Let's Encrypt certificate,
  Supervisor (Horizon, Reverb, Pulse), cron and the firewall, creates the
  `chatvia` user, and deploys the newest release with Deployer into
  `/var/www/chatvia`. It sets up the whole server, so use one that runs
  nothing else. Run the installer again to upgrade (it backs up the
  database first), or to finish an install that stopped.
  `--complete --yes --email you@example.com --domain chat.example.com`
  installs without questions; `--help` lists every option.

The
[installation guide](https://chatvia.ai/docs/guide/installation) has the
steps without Docker, done by hand. In short:

- PHP-FPM behind nginx, with `APP_ENV=production` and `APP_DEBUG=false`.
- `.env` only holds what the app needs to start (APP_KEY, the database,
  Redis, Reverb and so on). Mail, storage, API keys and the AI services are
  set in the admin panel, under **Settings**.
- Build the frontend with `npm ci && npm run i18n:merge && npm run build`.
- Run `php artisan chatvia:db:create-tool-databases`, then
  `php artisan migrate --seed --force` (in production the seeders load
  only the list of AI models, no demo data), create the first admin with
  `php artisan chatvia:admin:create-user <email> <name>`, add your API keys in the
  admin panel, then run `php artisan chatvia:mcp:sync-builtin`.
- Set up e-mail in the admin panel (**Settings → Email**). People who sign up at `/register` must
  confirm their e-mail address before they can use the app, and invitations
  and sign-in links also go out by e-mail.
- Choose where files are kept in the admin panel (**Settings → Storage**):
  this server or S3-compatible storage. Knowledge files, chat attachments and
  backups follow it.
- Cache the configuration, events, routes and views with
  `php artisan config:cache`, `event:cache`, `route:cache` and `view:cache`.
  Run them again after each deploy and after each change to `.env`.
- Keep `php artisan horizon`, `php artisan reverb:start` and
  `php artisan pulse:check` running under a process manager (for example
  Supervisor or systemd), and run `php artisan schedule:run` every minute from
  cron. With more than one app server, you can run the scheduler on each of
  them: each task runs on one server only, as long as all servers use the same
  Redis. Run `pulse:check` on each server, so Pulse shows all of them.
- Redis also keeps the sign-in sessions. If Redis loses its data, everyone has
  to sign in again.
- Build the widget with `npm run shakomako:build`.
  Other sites load it, so nginx must allow any origin for `/shakomako/` (see
  [The chat widget](#the-chat-widget)).
- How long the logs are kept (tool calls, guardrails and security events) is
  set in the admin panel (**Settings → Security**).
- Go through the security settings in `.env.example` (for example
  `TRUSTED_PROXIES` and `SESSION_SECURE_COOKIE`) and set each one for your
  environment. Behind Cloudflare, include the word `cloudflare` in
  `TRUSTED_PROXIES` (it stands for Cloudflare's published IP ranges), or
  visitor IPs, countries and rate limits see Cloudflare's edge instead of the
  visitor. The content security policy and the sign-in bot check are in
  the admin panel (**Settings → Security**).

## Test the Docker install

You can test the production image (`Dockerfile`) and the installer on your
machine before you tag a release. Nothing is pushed to the registry. This is
not Sail: it runs the same setup people get from `install.sh`, with the code
built into the image.

1. Build the image:

   ```bash
   docker build --build-arg SHAKOMAKO_VERSION=$(npm run -s shakomako:version) -t chatvia-local:dev .
   ```

2. Run the installer from this repository:

   ```bash
   CHATVIA_COMPOSE_URL="file://$PWD/docker/compose.yaml" CHATVIA_MANAGE_URL="file://$PWD/docker/manage" bash install.sh --no-pull --image chatvia-local --version dev --dir ~/chatvia-test
   ```

`--no-pull` uses the image on your machine. The two `CHATVIA_*_URL`
variables make the installer use your copies of `docker/compose.yaml` and
`docker/manage`. Without them, it downloads these files from GitHub, and you
do not test your changes. Add `--dry-run` to see the questions and the output
without starting anything.

The test install is the Compose project `chatvia`. It shares no containers,
data or ports with Sail (`chatvia-dev`), so both can run at the same time. It
publishes only the app port: 8899, or the next free port.

After a change:

- **App code:** build the image again (step 1, same tag), then run
  `docker compose up -d` in `~/chatvia-test`. Compose sees the new image and
  restarts the containers.
- **`install.sh`, `docker/compose.yaml` or `docker/manage`:** run step 3 again
  in the same folder. This tests the upgrade.
- **A fresh install:** run `docker compose down -v` in `~/chatvia-test` (this
  deletes its data), delete the folder, then run step 3 again.

## Test the complete installation

The complete installation (`install.sh --complete`) sets up a whole Ubuntu
26.04 server. A throwaway VPS is the real test. On your machine, a container
with systemd stands in for one:

1. Build an image with systemd, from a folder with this `Dockerfile`:

   ```dockerfile
   FROM ubuntu:26.04
   RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
         systemd systemd-sysv dbus curl ca-certificates git sudo openssh-server iproute2
   STOPSIGNAL SIGRTMIN+3
   CMD ["/sbin/init"]
   ```

   ```bash
   docker build -t chatvia-server .
   ```

2. Start it, with this repository inside:

   ```bash
   docker run -d --name chatvia-server --privileged --cgroupns=host -v /sys/fs/cgroup:/sys/fs/cgroup:rw --tmpfs /run --tmpfs /run/lock -p 8088:80 -v "$PWD:/src/chatvia:ro" chatvia-server
   ```

3. Give it a copy of your commits to deploy, then run the installer from
   this repository:

   ```bash
   docker exec chatvia-server bash -c 'git config --system --add safe.directory "*" && git clone -q --bare /src/chatvia /srv/chatvia.git'
   ```

   ```bash
   docker exec -it -e CHATVIA_REPO=/srv/chatvia.git chatvia-server bash /src/chatvia/install.sh --complete --version main --domain 127.0.0.1
   ```

ChatVia.ai then answers on <http://localhost:8088>. Run step 3's last command
again to test an upgrade; to deploy newer commits, clone them again first.
Without a widget release, add `-e SHAKOMAKO_VERSION=main` to it. The frontend
build needs about 3 GB of memory next to the running app: give Docker enough,
or the container's systemd stops the installer. `docker rm -f chatvia-server`
removes everything.

## Contributing, security and license

- Bug reports and feature requests are welcome as
  [issues](https://github.com/chatvia/chatvia/issues/new/choose). We do not
  accept pull requests: a maintainer makes every change. See
  [CONTRIBUTING.md](CONTRIBUTING.md) and our [Code of Conduct](CODE_OF_CONDUCT.md).
- Found a security problem? Please report it privately; see
  [SECURITY.md](SECURITY.md).
- Changes are listed in [CHANGELOG.md](CHANGELOG.md).
- ChatVia.ai is open-source software licensed under the [MIT License](LICENSE).
