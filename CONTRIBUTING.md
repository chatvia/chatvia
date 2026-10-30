# Contributing to ChatVia.ai

Thank you for your interest in ChatVia.ai. Bug reports, ideas for new features
and notes on the documentation are all welcome, as **issues**.

- Found a security problem? **Do not open an issue.** Follow [SECURITY.md](SECURITY.md).
- Please follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## We do not accept pull requests

A maintainer writes every change to ChatVia.ai. This keeps the code, the
conventions and the license in one hand. Pull requests from outside the
project are closed automatically, with a link back to the issue forms.

You can still help a lot:

- **Report a bug.** Use the *Bug report* form. Steps to reproduce, the version
  and the logs make a fix much faster.
- **Ask for a feature.** Use the *Feature request* form. Describe the problem
  you want to solve, not only the solution you have in mind.
- **Point out a documentation problem.** Use the *Documentation* form.
- **Suggest a fix.** Each form has a field for it. If you know where the
  problem is in the code, describe it there; a short code snippet or diff is
  welcome.

Search the [existing issues](https://github.com/chatvia/chatvia/issues) first,
so you do not repeat a report. If someone already asked for the same thing, add
a 👍 or a comment with your own case.

The rest of this guide explains how to set up the project and check a change.
It is for the maintainers, and for anyone who runs a changed copy of
ChatVia.ai (the [ChatVia.ai License](LICENSE) allows this, as long as the
"Powered by ChatVia.ai" credit stays). Read
[ARCHITECTURE.md](ARCHITECTURE.md) before a bigger change. It explains how the
code is organised, the conventions, and the words we use.

## Development setup

The full steps follow below. In short:

```bash
docker compose up -d          # PostgreSQL (with the search extensions), Redis, Mailpit
composer install
npm ci
cp .env.example .env && php artisan key:generate
php artisan chatvia:db:create-tool-databases   # the analytics, Pulse and Spectra databases, when missing
php artisan migrate --seed
composer dev                  # app server, queue worker, logs and Vite
```

You need PHP 8.5, Composer 2 and Node 22. The system tools listed under
[Requirements](#requirements) (pandoc, catdoc, poppler, ffmpeg, WeasyPrint) are optional: without
one, the app does its work itself or refuses only the files it cannot read.
Tests of a tool skip when it is not installed.

With only Docker on your machine, use Laravel Sail instead: it runs the app,
the queue worker, the scheduler, Reverb and Pulse's server stats in
containers, with every system tool installed. See [Develop with Laravel Sail](#develop-with-laravel-sail).

### Requirements

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
OpenAI, Anthropic, Mistral, Google Gemini, Groq, or your own OpenAI-compatible
server.
OpenRouter, OpenAI, Mistral and Google Gemini make embeddings with the same
key.
Reranking uses OpenRouter or Cohere. PDF, Word, slides and images are read by
Mistral OCR or Azure Document Intelligence; without one of them, those files
cannot be uploaded. Text is split into chunks inside ChatVia.ai, by tokens or by
characters (**Settings → Splitting**). Website import uses Firecrawl. Each
API key is entered once in the admin panel (**Settings → AI providers**), and
the models in **Settings → AI models**, not in `.env`; see
[AI providers](https://chatvia.ai/docs/guide/installation#6-add-your-api-keys).

### Getting started

#### 1. Start PostgreSQL, Redis and Mailpit with Docker

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

#### 2. Install the app

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
`public/shakomako`; see [The widget](#the-widget).

#### 3. Configure `.env`

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
`EMBEDDING_INDEX` (`binary`, the default, or `full`) decides what the chunk
HNSW indexes hold and may change later: `php artisan chatvia:search:vector-indexes`
rebuilds them (the Docker start-up runs it).

Sign-up, the message limit of new workspaces and how long conversations are
kept are set later in the admin panel (**Settings → Workspaces**), not in
`.env`.

#### 4. Create the database schema and demo data

```bash
php artisan chatvia:db:create-tool-databases
php artisan migrate --seed
php artisan storage:link
```

The first command creates the databases of the analytics tables, Pulse and
Spectra (`DB_ANALYTICS_DATABASE`, `DB_PULSE_DATABASE` and
`DB_SPECTRA_DATABASE`, by default `analytics`, `pulse` and `spectra`) when they
are missing; the Docker services create them on their
first start. The first migration installs the PostgreSQL extensions. The
seeders load the list of AI models and, outside production, create a demo
admin account (`SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD`, by default
`admin@example.com` / `password`) with sample data; see
`database/seeders/DatabaseSeeder.php`.

#### 5. Add your API keys

Sign in to the admin panel at `/admin` with the demo admin account and open
**Settings → AI providers**. Enter the key of your chat and embedding
provider (with the defaults, one Scaleway key covers both). Then add the
built-in tools (charts, forms, weather, currency, web search) to the tool
catalog:

```bash
php artisan chatvia:mcp:sync-builtin
```

### Running the app

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

### Develop with Laravel Sail

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

#### Where the settings live

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

#### Good to know

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

## Making a change

1. Make the change. Keep each commit focused on **one** thing.
2. Add or update tests (see [Tests](#tests)).
3. Run the checks (see [Checks](#checks)): `composer ci:check` before you
   push.
4. Add a line to the `Unreleased` section of [CHANGELOG.md](CHANGELOG.md) when
   users or self-hosters will notice the change.
5. Go through the [change checklist](#change-checklist).

Commit messages: a short summary in the imperative mood ("Add Slack thread
replies", not "Added ..."), then a blank line and more detail if needed. When
the change comes from an issue, close it from the commit ("Closes #123").

### Releasing

A release is a version tag. To make one:

1. In [CHANGELOG.md](CHANGELOG.md), turn the `Unreleased` section into
   `## [x.y.z] - YYYY-MM-DD`, start a new empty `Unreleased` section above
   it, and add the version's compare link at the end of the file.
2. Commit, then tag that commit `vx.y.z` (an annotated, signed tag) and push
   the tag.

The [Docker image workflow](.github/workflows/docker.yml) takes it from there:
it runs every check, builds and pushes the image, and creates the GitHub
release with the version's changelog section as its notes
(`.github/scripts/release-notes` extracts it). A pre-release tag such as
`v1.5.0-rc.1` is marked as a pre-release. When the changelog has no section
for the version, the release gets GitHub's generated notes and the run shows
a warning.

## Checks

One Composer script, defined in `composer.json`, runs every gate.

### `composer ci:check`: fix, then check

It runs the CI gates in this order and stops at the first failure. Where a
tool can fix what it finds (Rector, Pint, Prettier, ESLint), it **fixes the
files** instead of only checking them, so look at `git diff` afterwards and
commit the fixes you want. CI runs the check-only form of each gate, which
never changes a file. In CI every gate is a job of its own, named after its
tool, and all of them run side by side, so one run shows every gate that
fails:

- `security.yml`: Composer audit, npm audit (steps 2 and 3). It also runs
  every morning, because new advisories appear without a push.
- `php-quality.yml`: Composer validate, Pint, Rector, PHPStan (steps 1, 4, 5
  and 6).
- `typescript-quality.yml`: Prettier, ESLint, TypeScript, Vitest, Translations, Build
  (steps 7 to 11, plus `npm run build`).
- `tests.yml`: Pest (step 12).

The jobs share their setup through `.github/actions/setup-php-app` and
`.github/actions/setup-frontend`. When you add a gate, add it to both
`composer.json` and its workflow.

| Step | Command | What it checks |
|---|---|---|
| 1 | `composer validate --strict` | `composer.json` is valid. |
| 2 | `composer audit` | No known security problems in PHP packages. |
| 3 | `npm audit --omit=dev` | No known security problems in production npm packages. |
| 4 | `composer refactor` (`rector process`) | Applies Rector (PHP 8.5 and Laravel 13 rules, types, dead code). CI: `composer refactor:check`. |
| 5 | `composer format` (`pint`) | Fixes PHP code style. CI: `composer format:check`. |
| 6 | `composer analyse` (`phpstan analyse`) | PHPStan with Larastan on `src/` (`phpstan.neon.dist`). |
| 7 | `npm run format` (`prettier --write resources/`) | Fixes frontend code style. CI: `npm run format:check`. |
| 8 | `npm run lint` (`eslint . --fix`) | ESLint rules for Vue and TypeScript; fails on what it cannot fix. CI: `npm run lint:check`. |
| 9 | `npm run typecheck` (`vue-tsc --noEmit`) | TypeScript types in the frontend. |
| 10 | `npm run test` (`vitest run`) | The frontend unit tests: `*.test.ts` files in `resources/js/tests/`, at the path of the code they test. |
| 11 | `npm run i18n:check` | Every static `t('…')` key exists in `en.json`, and every locale's messages compile. |
| 12 | `composer test` | The Pest suite (Unit, Feature, Architecture, Eval). |

CI also checks the shell scripts and workflows in `scripts.yml`, in two jobs of their
own: that `install.sh` is built from `installer/`, ShellCheck on the
installer, `docker/manage` and the scripts in the image, actionlint on
`.github/workflows`, and a parse of `install.sh` and `docker/manage` with
bash 3.2, the bash of macOS.

`install.sh` is built: change the files in `installer/src` and
`installer/templates`, then run `installer/build`, and commit both
([installer/README.md](installer/README.md)). After changing a script, run:

```bash
installer/build --check
shellcheck install.sh installer/build installer/templates/chatvia-auto-update.sh docker/manage docker/app/bin/* docker/app/entrypoint.d/*.sh
/bin/bash -n install.sh && /bin/bash -n docker/manage   # bash 3.2 on macOS
actionlint                                                # brew install actionlint
```

### Running one tool

| Tool | Fix | Check only |
|---|---|---|
| Pint (PHP style) | `composer format` or `vendor/bin/pint <files>` | `composer format:check` |
| Rector | `composer refactor` | `composer refactor:check` |
| PHPStan | | `composer analyse` or `vendor/bin/phpstan analyse --memory-limit=2G <paths>` |
| Prettier | `npm run format` or `npx prettier --write <files>` | `npm run format:check` |
| ESLint | `npm run lint` or `npx eslint --fix <files>` | `npm run lint:check` |
| vue-tsc | | `npm run typecheck` |
| Vitest | | `npm run test` |
| i18n | `npm run i18n:merge` | `npm run i18n:check` |

### The widget

The embeddable widget, Shakomako, lives in its own repository,
[chatvia/shakomako](https://github.com/chatvia/shakomako), with its own tools
and CI. Change the widget there.

`npm run shakomako:build` builds the newest widget release (the highest
`vX.Y.Z` tag of its repository) into `public/shakomako`, where the app serves
it. The release workflow passes the same release to the Docker image build.
`npm run -s shakomako:version` prints it, and `SHAKOMAKO_VERSION=vX.Y.Z` builds
a chosen one instead.

To work on the widget and the app together, clone the widget next to this
repository and build your checkout instead:

```bash
SHAKOMAKO_PATH=../shakomako npm run shakomako:build
```

To ship a widget change, tag a release in the widget repository (see its
`VERSIONING.md`). The next build takes it; nothing changes here. When the
change also needs a server change (the `/widget` API, the `WidgetHeaders`
names, the widget settings response), merge the server change here first,
then tag the widget release: every build takes the newest release.

## Tests

- The frontend's plain TypeScript (helpers in `resources/js/shared/lib`,
  say) is tested with [Vitest](https://vitest.dev): `resources/js/x/name.ts`
  is tested by `resources/js/tests/x/name.test.ts`, run by `npm run test`.
- We use [Pest](https://pestphp.com). Unit tests live in `tests/Unit/`,
  feature tests (through a route, an admin page or a command) in
  `tests/Feature/`, architecture rules in
  `tests/Architecture/`, and quality checks with real models in `tests/Eval/`
  (they skip themselves without an API key).
- Tests run against **PostgreSQL**, not SQLite, because the app uses the
  `vector` (and, when available, `pg_textsearch`) extensions.
  `docker compose up -d` starts a server with these extensions and an empty
  `testing` database (user `chatvia`, password `password`; see
  `phpunit.xml`). Without pg_textsearch the suite still runs: keyword search
  falls back to the built-in full-text search.
- **The test database sets itself up.** On the first run the suite installs
  the extensions, runs the migrations and seeds the reference data. When the
  migration files change, it rebuilds the schema on the next run. Each test
  runs inside a transaction. You never need to migrate the test database by
  hand.
- `composer test` runs the suite in parallel, one process per CPU core (at
  most 8, so PostgreSQL keeps enough locks for its other users). Each
  process gets its own database (`testing_test_1`, `testing_test_2`, …), a
  copy of `testing` made one at a time, so the migrations run once. A copy
  is made again when the migration files change. `--recreate-databases`
  makes every copy again. CI runs the suite in one process instead: a
  runner has too few cores for parallel to save time.
- Run one file or folder while you work:

  ```bash
  php artisan test --compact tests/Unit/Domain/Agent
  vendor/bin/pest tests/Architecture        # fast: no app, no database
  ```

- Test a feature through its entry point (a route, an admin page, a
  command), and check what the user gets: the saved record, the response,
  the queued job. Write a unit test only for a critical rule (tenancy,
  permissions, sign-in, limits, SSRF, signatures, guardrails, tool approval,
  data erasure, secrets) or for logic with real branches (parsers, decoders,
  chunkers), with one case per branch. Don't test labels, copy, prompt
  wording, DTO shape, config defaults or wiring.
  [ARCHITECTURE.md](ARCHITECTURE.md#what-earns-a-test) has the full list.
- `ClassesHaveDocblocksTest` keeps a **shrink-only allowlist** (empty today):
  every class has a docblock with at least one sentence of prose before its
  `@` tags. A new class without a docblock fails. When you fix an
  allowlisted entry, the test fails until you remove it from the list. It
  prints its coverage on every run.
- Fix the code, not the test. Do not weaken an assertion to make a test pass.
- Tests must not call real external services. Use `Http::fake()` and the fakes
  in `tests/Support/`. `TestCase` calls `Http::preventStrayRequests()`, so a
  request that no fake answers fails the test instead of going out.
- Put a unit test at the path of the class it tests:
  `tests/Unit/Domain/Knowledge/Retrieval/Services/KnowledgeBaseTest.php` for
  `src/Domain/Knowledge/Retrieval/Services/KnowledgeBase.php`, App code under
  `tests/Unit/App/…` (`TestLayoutTest` checks it). Put a feature test at the
  path of its entry point: `tests/Feature/App/Http/Controllers/Agent/AgentControllerTest.php`
  for `src/App/Http/Controllers/Agent/AgentController.php`, admin pages under
  `tests/Feature/App/Admin/…`, commands under `tests/Feature/App/Console/Commands/…`.
  Use the factories
  and the helpers in `tests/Pest.php` rather than building models by hand. A
  helper function you declare in a test file is global, so give it a short
  prefix of its own (`cscChunk()`), or two files will clash.

## Testing the installers

### Test the Docker install

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
- **`install.sh`, `docker/compose.yaml` or `docker/manage`:** run step 2 again
  in the same folder. This tests the upgrade. After a change in `installer/`,
  run `installer/build` first.
- **A fresh install:** run `docker compose down -v` in `~/chatvia-test` (this
  deletes its data), delete the folder, then run step 2 again.

### Test the complete installation

The complete installation (`install.sh --complete`) sets up a whole Ubuntu
server (24.04 LTS or newer). A throwaway VPS is the real test: try 24.04,
which takes PostgreSQL and Composer from elsewhere, as well as 26.04. On
your machine, a container with systemd stands in for one:

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

## Code conventions (short version)

[ARCHITECTURE.md](ARCHITECTURE.md) has the full rules. The most important ones:

- **Three namespaces.** `App\` (`src/App/`) is the HTTP layer and framework
  wiring, `Domain\` (`src/Domain/`) is the business logic, `Support\`
  (`src/Support/`) holds small generic helpers. Domain never imports from App,
  except `App\Identity` (accounts). Support never uses Domain or App.
- **Request flow.** Route → controller → form request (`rules()`,
  `authorize()`, `toData()`) → DTO → Action `execute()` → response. Jobs call
  the same Actions.
- **Actions** have one public method, `execute()`. They take DTOs and models,
  never a `Request`, the `Auth` facade or the session, and never return an HTTP
  response. Multi-step writes go in `DB::transaction()`.
- **Controllers** are thin: no database writes, external HTTP calls or event
  dispatching in the controller body.
- **Side effects** (e-mails, notifications, broadcasts) go in listeners.
  **Listeners are registered by hand** in
  `DomainServiceProvider::registerEventListeners()`; there is no
  auto-discovery.
- **DTOs** extend `Spatie\LaravelData\Data` and end in `Data`. Enum-like
  strings are backed enums.
- **Naming:** singular module names, Pascal-case acronyms (`Llm`, `Mcp`,
  `Http`), and the words from the glossary (Conversation, Session, Source,
  Chunk, Tool, Chatter, Workspace, ...).
- **Comments** explain *why*, in plain English. Every class has a short
  docblock that says what it is for.

### Database

- Until the first release, each table has one create migration. Put a schema
  change into that migration instead of adding a new one, and do not add
  data-fix migrations. (From the first release on, migrations only move
  forward.)
- Some enum-like columns have Postgres `CHECK` constraints. A new value goes
  into the column's `enum()` list in its create migration.
- An embedding column is `$table->embedding('name')`, never `halfvec()` or
  `vector()` with a fixed size: it takes the install's `EMBEDDING_VECTOR_TYPE`
  and `EMBEDDING_DIMENSIONS`. A query vector is cast with
  `EmbeddingStorage::columnType()`, and an HNSW index uses
  `EmbeddingStorage::type()->operatorClass()`.
- A vector index only pays off on the large shared `chunks` table. A small
  per-agent set (its tools, functions, forms, or the chunks of an agent with
  few of them) is searched exactly: collect the agent's rows in a
  `MATERIALIZED` CTE and order them by distance.
- Keyword search goes through `LexicalSearch` (never inline BM25 or
  full-text SQL): it writes the driver's SQL, and
  `ConfigureLexicalIndexesAction` keeps the indexes in the driver's shape
  (`php artisan chatvia:search:lexical-indexes` after changing the driver or
  `SEARCH_TEXT_CONFIG`).
- Key workspace data to the **workspace** (`$agent->workspace_id`,
  `$user->workspace()->id`), not to the person who is acting.

### Frontend (Vue 3 + TypeScript)

- `resources/js/` is grouped by who uses it: `shared/`, `auth/` (sign-in),
  `chat/` (hosted chat) and `dashboard/` (one folder per feature, such as
  `dashboard/agents/`). Put new code in the narrowest folder that uses it.
- Every component uses `<script setup lang="ts">`. Components call the server
  through the typed modules in the `api/` folders, never axios directly.
- Destructive confirmations use the shadcn `AlertDialog`, never `confirm()`.
- Use logical Tailwind classes (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`,
  `end-*`, `gap-*`) so right-to-left languages work.
- Format dates with `useDateFormat` (or `useLocaleDate`).
- Theme CSS variables already hold a full color: use `var(--name)`, never
  `hsl(var(--name))`.
- `resources/js/shared/ui/` is generated by shadcn-vue. Put your own
  shared components in `resources/js/shared/components/`.

### Translations (i18n)

1. Use `t('area.key')` for every UI string.
2. Add the key to a fragment file in
   `resources/js/shared/i18n/locales/_fragments/`, named `{area}.{locale}.json`
   (at least `{area}.en.json`).
3. Merge the fragments into the locale files:

   ```bash
   npm run i18n:merge            # every locale
   npm run i18n:merge -- de fr   # only these locales
   ```

4. Commit the fragment and the merged locale files, then run
   `npm run i18n:check`.

A literal `@` in a message must be written `{'@'}`. A plural message has
exactly two forms (`one | many`). Missing keys in other locales fall back to
English. Server-side text (e-mails, flash messages) uses `__('English text')`
and `lang/*.json` instead.

### URLs in the frontend

Frontend URLs come from Wayfinder: typed functions generated from the named
Laravel routes into `resources/js/routes` (for example
`import { store } from '@/routes/agents/forms'` and `store.url(agentId)`).
The folder is gitignored. `npm run dev` and `npm run build` generate it, and
`composer ci:check` does too; to generate it by hand, run:

```bash
php artisan wayfinder:generate --skip-actions
```

Keep route names stable: the frontend imports by name.

### TypeScript types from PHP

DTOs and enums marked `#[TypeScript]` are turned into TypeScript types in
`resources/types/generated.d.ts`. After you add or change such a class, run:

```bash
php artisan typescript:transform
```

and commit the regenerated file. Do not edit it by hand.

## Change checklist

- [ ] `composer ci:check` passes on your machine.
- [ ] You added or updated tests, and new Actions have a unit test.
- [ ] New classes have a docblock that says what they are for.
- [ ] Schema changes are in the table's create migration (no new `add_*` migration).
- [ ] New listeners are registered in `DomainServiceProvider`.
- [ ] New UI text goes through vue-i18n (fragments + `npm run i18n:merge`).
- [ ] `resources/types/generated.d.ts` is regenerated if a `#[TypeScript]`
      class changed.
- [ ] `CHANGELOG.md` has a line under `Unreleased` (if users or self-hosters
      will notice the change).
- [ ] No secrets, personal data or production details in the code, tests or
      screenshots.
