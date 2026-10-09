<p align="center">
  <img src="public/images/logo.svg" alt="Chatvia logo" width="96" height="96">
</p>

<h1 align="center">Chatvia</h1>

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

Chatvia lets a team create AI agents, give them knowledge (files, web pages,
text and Q&A pairs), connect them to tools, and put them in
front of people: on a hosted chat page, in a widget on any website, in a
command bar, through messaging apps, or through a REST API. Answers come from
retrieval-augmented generation (RAG) with hybrid search (vectors + BM25) inside
PostgreSQL. It is built with Laravel, Inertia and Vue, and is designed to work
with open-weight models.

## Contents

- [Features](#features)
- [How it is built](#how-it-is-built)
- [Requirements](#requirements)
- [Getting started](#getting-started)
- [Running the app](#running-the-app)
- [Tests and checks](#tests-and-checks)
- [The chat widget](#the-chat-widget)
- [Documentation site](#documentation-site)
- [Going to production](#going-to-production)
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
- Hybrid search: vector search ([VectorChord](https://github.com/tensorchord/VectorChord))
  and keyword search ([VectorChord-bm25](https://github.com/tensorchord/VectorChord-bm25)),
  merged and reranked.

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
- Optional paid plans with Stripe. Self-hosted installs can turn billing off.
- An admin panel and a dashboard translated into many languages, including
  right-to-left languages.

## How it is built

| Part | Technology |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Inertia, Vue 3, TypeScript, Tailwind CSS 4, shadcn-vue, Vite |
| Database | PostgreSQL 18 with `vchord`, `vchord_bm25`, `pg_tokenizer` and pgvector |
| Queues and cache | Redis, Laravel Horizon |
| Real-time updates | Laravel Reverb (WebSockets) and server-sent events for streamed answers |
| Admin panel | Filament |
| Tests | Pest |

The PHP code is split into three namespaces:

- `src/App/` (`App\`): the HTTP layer and framework wiring, plus
  `App\Identity` (accounts and sign-in).
- `src/Domain/` (`Domain\`): business logic, one folder per module
  (`Agent`, `Conversation`, `Knowledge`, `Integration`, `Channel`, `Billing`, ...).
  State changes go through Actions that take DTOs.
- `src/Support/` (`Support\`): small shared helpers.

The frontend lives in `resources/js/`, the embeddable widget in `widget/`, and
the user documentation in `docs/`. [ARCHITECTURE.md](ARCHITECTURE.md) explains
how the code is organised and the conventions; [CONTRIBUTING.md](CONTRIBUTING.md)
explains how to work on it.

## Requirements

- **PHP 8.5** with these extensions: `dom`, `fileinfo`, `intl`, `libxml`,
  `openssl` and `pdo` (required by `composer.json`), plus `pdo_pgsql`, `redis`,
  `mbstring`, `curl`, `zip`, `gd`, `exif` and `pcntl`.
- **Composer 2**
- **Node.js 22** and npm
- **PostgreSQL 18** with the [VectorChord](https://github.com/tensorchord/VectorChord) (`vchord`),
  [VectorChord-bm25](https://github.com/tensorchord/VectorChord-bm25) (`vchord_bm25`)
  and [pg_tokenizer](https://github.com/tensorchord/pg_tokenizer.rs) (`pg_tokenizer`)
  extensions, and pgvector (`vector`). The easiest way is the
  `tensorchord/vchord-suite` Docker image (see below).
- **Redis** 7 or newer
- **System tools** for some file types. You only need the ones for the
  features you use:
  - [LibreOffice](https://www.libreoffice.org/) (`soffice`): Word, PowerPoint and Excel files
  - [pandoc](https://pandoc.org/): EPUB and other e-book formats
  - [poppler](https://poppler.freedesktop.org/) (`pdfinfo`, `pdftotext`): PDF files
  - [ffmpeg](https://ffmpeg.org/) (`ffmpeg`, `ffprobe`): audio and video
  - Chrome (headless) for PDF export of chats. `npm install` downloads one
    through Puppeteer.

  If a tool is not at the default path in `config/services.php`, set its path
  in `.env`: `LIBREOFFICE_BIN_PATH`, `PANDOC_BIN_PATH`, `PDFINFO_BIN_PATH`,
  `PDFTOTEXT_BIN_PATH`, `FFMPEG_BIN_PATH`, `FFPROBE_PATH`,
  `CHROME_EXECUTABLE_PATH` (and `NODE_BIN_PATH` / `NPM_BIN_PATH` for
  Puppeteer).

**External AI services.** Chatvia calls AI models through OpenAI-compatible
APIs. You need a chat provider and an embedding provider. The default
configuration uses Scaleway's Generative APIs with open-weight models (Qwen3
and others) for chat, embeddings and reranking. The chat provider can be
changed with `LLM_PROVIDER` (`scaleway`, `mistral`, `groq` or `openrouter`).
Reading documents and splitting text run on your server unless you add a
Mistral or chunk.fit key. Website import uses Firecrawl. API keys are entered
in the admin panel (**Settings**), not in `.env`; see
[AI providers](docs/guide/self-hosting/ai-providers.md).

## Getting started

### 1. Start PostgreSQL, Redis and Mailpit with Docker

The repository has a `docker-compose.yml` for the services the app needs. The
app itself runs on your machine.

```bash
docker compose up -d
docker compose ps        # wait until all three services are "healthy"
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

1. Install PostgreSQL 18 and build or install the `vchord`, `vchord_bm25` and
   `pg_tokenizer` extensions and pgvector (see each project's README).
2. Load the libraries in `postgresql.conf` and restart PostgreSQL:

   ```
   shared_preload_libraries = 'vchord,vchord_bm25,vector,pg_tokenizer'
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
npm ci                       # also downloads headless Chrome for PDF export

cp .env.example .env
php artisan key:generate
```

### 3. Configure `.env`

`.env.example` lists the settings, each with a short comment. Its default
values work with the Docker services above:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chatvia
DB_USERNAME=chatvia
DB_PASSWORD=password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

Then set `BUILTIN_MCP_SECRET` to a long random string (the built-in tools need
it). Billing is off in `.env.example` (`BILLING_ENABLED=false`), so the app
runs without Stripe; see `config/billing.php` to turn it on.

### 4. Create the database schema and demo data

```bash
php artisan migrate --seed
php artisan storage:link
```

The first migration installs the PostgreSQL extensions. The seeders load the
list of AI models and, outside production, create a demo admin account
(`SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`, by default `admin@example.com` /
`password`) with sample data; see `database/seeders/DatabaseSeeder.php`.

### 5. Add your API keys

Sign in to the admin panel at `/admin` with the demo admin account and open
**Settings → AI providers**. Enter the key of your chat and embedding provider
(with the defaults, one Scaleway key covers both). Then add the built-in tools
(charts, forms, weather, currency, web search) to the tool catalog:

```bash
php artisan app:sync-builtin-mcp
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
- `composer ci:check` runs, without changing any file: `composer validate`,
  `composer audit`, `npm audit` (production dependencies), Pint, Rector,
  PHPStan, Prettier, ESLint, `vue-tsc`, the i18n check and the test suite. CI
  runs the same command. `composer ci` fixes formatting and lint problems
  first, then runs the checks.

## The chat widget

The embeddable widget (chat bubble and command bar) is a separate frontend
project in [`widget/`](widget/README.md), with its own `package.json` and build.
See [widget/README.md](widget/README.md).

## Documentation site

The user guide is a [VitePress](https://vitepress.dev/) site in `docs/`:

```bash
npm run docs:dev      # http://localhost:4000
npm run docs:build    # static site in docs/.vitepress/dist
```

## Going to production

The [self-hosting guide](docs/guide/self-hosting/index.md) has the full steps.
In short:

- PHP-FPM behind nginx, with `APP_ENV=production` and `APP_DEBUG=false`.
- Build the frontend with `npm ci && npm run i18n:merge && npm run build`.
- Run `php artisan migrate --seed --force` (in production the seeders load
  only the list of AI models, no demo data), create the first admin with
  `php artisan admin:create-user <email> <name>`, add your API keys in the
  admin panel, then run `php artisan app:sync-builtin-mcp`.
- Cache the configuration with `php artisan config:cache`, `event:cache` and
  `view:cache`. Do not run `route:cache`: the localized public pages use
  routes that the route cache cannot store.
- Keep `php artisan horizon` and `php artisan reverb:start` running under a
  process manager (for example Supervisor or systemd), and run
  `php artisan schedule:run` every minute from cron.
- Build the widget in `widget/` for your server and set `WIDGET_SCRIPT_URL`.
- Go through the security settings in `.env.example` and set each one for
  your environment.

## Contributing, security and license

- Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md)
  and our [Code of Conduct](CODE_OF_CONDUCT.md).
- Found a security problem? Please report it privately; see
  [SECURITY.md](SECURITY.md).
- Changes are listed in [CHANGELOG.md](CHANGELOG.md).
- Chatvia is open-source software licensed under the [MIT License](LICENSE).
