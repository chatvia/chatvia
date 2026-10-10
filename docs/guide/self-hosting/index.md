# Self-hosting

Chatvia is open source (MIT License). You can run it on your own servers. This
page explains what you need, how to install it, the most important settings,
and how to upgrade.

::: tip Just trying it out?
For a local install on your own computer, follow the "Getting started" steps
in the project's `README.md`. They use Docker for the database, Redis and
e-mail. This page is about a server that other people use.
:::

## What you need

### Software

| Software | Version | Notes |
| --- | --- | --- |
| PHP | 8.5 | With the extensions `dom`, `fileinfo`, `intl`, `libxml`, `openssl`, `pdo`, `pdo_pgsql`, `redis`, `mbstring`, `curl`, `zip`, `gd`, `exif` and `pcntl`. Run the web app with PHP-FPM behind nginx (or another web server). |
| Composer | 2 | Installs the PHP packages. |
| Node.js and npm | 22 | Builds the frontend and the chat widget. |
| PostgreSQL | 18 | With the `vchord`, `vchord_bm25`, `pg_tokenizer` and `vector` (pgvector) extensions. See [The database](#the-database). |
| Redis | 7 or newer | Queues, cache, rate limits and the live chat history. |
| A process manager | | Supervisor or systemd, to keep the queue workers and the WebSocket server running. |
| cron | | Runs the scheduler every minute. |

Some file types need extra programs. Install only the ones for the features
you use:

| Program | Used for |
| --- | --- |
| LibreOffice (`soffice`) | Word, PowerPoint and Excel files |
| poppler (`pdfinfo`, `pdftotext`) | PDF files |
| pandoc | EPUB and FB2 e-books |
| ffmpeg (`ffmpeg`, `ffprobe`) | Audio and video files |
| Chrome (headless) | Exporting a chat as PDF. `npm ci` downloads one. |
| `pg_dump` | The built-in database backup |

The programs must be on the `PATH` of the queue worker, because files are
processed in background jobs. If a program is somewhere else, set its path in
`.env` (`LIBREOFFICE_BIN_PATH`, `PDFTOTEXT_BIN_PATH`, `PDFINFO_BIN_PATH`,
`PANDOC_BIN_PATH`, `FFMPEG_BIN_PATH`, `FFPROBE_PATH`, `CHROME_EXECUTABLE_PATH`).

### Accounts with outside services

You need at least **one chat provider and one embedding provider** (the
service that turns text into search vectors). With the default settings, one
Scaleway API key covers both. Everything else is optional: Chatvia can read
documents and split text on your own server. See
[AI providers](./ai-providers.md) for every option.

### The database

The database needs four extensions. The easiest way is the
`tensorchord/vchord-suite` Docker image (for example the tag
`pg18-20260501`), which has all of them.

If you install PostgreSQL yourself:

1. Install the [VectorChord](https://github.com/tensorchord/VectorChord),
   [VectorChord-bm25](https://github.com/tensorchord/VectorChord-bm25),
   [pg_tokenizer](https://github.com/tensorchord/pg_tokenizer.rs) and
   [pgvector](https://github.com/pgvector/pgvector) extensions.
2. Load them in `postgresql.conf` and restart PostgreSQL:

   ```ini
   shared_preload_libraries = 'vchord,vchord_bm25,vector,pg_tokenizer'
   ```

3. Create a database and a user for Chatvia. The first migration runs
   `CREATE EXTENSION`, so the user needs the right to create extensions in
   that database the first time.

## Install

These steps install Chatvia in `/var/www/chatvia`. Run them as the user that
runs PHP.

### 1. Get the code and the packages

```bash
git clone <repository-url> /var/www/chatvia
cd /var/www/chatvia

composer install --no-dev --optimize-autoloader
npm ci
```

### 2. Create the `.env` file

```bash
cp .env.example .env
php artisan key:generate
```

`.env.example` has a short comment on every setting. Change at least these:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chat.example.com

DB_HOST=127.0.0.1
DB_DATABASE=chatvia
DB_USERNAME=chatvia
DB_PASSWORD=a-strong-password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=a-strong-password

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_FROM_ADDRESS=chat@example.com

REVERB_APP_ID=a-random-value
REVERB_APP_KEY=a-random-value
REVERB_APP_SECRET=a-random-value
REVERB_HOST=chat.example.com
REVERB_PORT=443
REVERB_SCHEME=https

BUILTIN_MCP_SECRET=a-long-random-value
SESSION_SECURE_COOKIE=true
```

You can make random values with `openssl rand -hex 32`. See
[Security settings](#security-settings) for the rest.

::: warning Decide on the embedding storage now
`EMBEDDING_VECTOR_TYPE` and `EMBEDDING_DIMENSIONS` can only be set before step
4 creates the tables. The defaults suit almost every install. See
[Embedding storage](#embedding-storage).
:::

E-mail must work before other people use the server. People who sign up must
confirm their e-mail address, and invitations and sign-in links are sent by
e-mail.

::: info API keys are not in `.env`
API keys and OAuth apps for outside services (AI providers, Firecrawl,
chunk.fit, social sign-in, Messenger/Instagram/WhatsApp, connectors,
Resend, Turnstile, IP2Location) are entered in the admin panel, under
**Settings**. They are stored encrypted in the database. `.env` only chooses
which service to use, its address and its models.
:::

### 3. Build the frontend

```bash
npm run i18n:merge
npm run build
```

The build reads the `VITE_*` values from `.env` (for example where the browser
finds the WebSocket server). Build again after you change one of them.

### 4. Create the database tables

```bash
php artisan migrate --seed --force
php artisan storage:link
```

The first migration installs the PostgreSQL extensions. `--seed` loads the
list of AI models that agents can pick. In production it does **not** create
demo accounts or demo data.

### 5. Create the first admin

```bash
php artisan admin:create-user you@example.com "Your Name"
```

The command asks for a password, so it does not end up in your shell history.
The new account is an admin with its own workspace and team, so it can sign in
to the dashboard (`/login`) and start building agents.

### 6. Add your API keys

1. Open the admin panel at `/admin` and sign in with the admin account.
2. Set up two-factor authentication with an authenticator app. In
   production, the admin panel requires it.
3. Go to **Settings → AI providers** and enter the key of your chat and
   embedding provider. Add other keys (for example **Settings → Knowledge** for
   website import) when you need them.

### 7. Add the built-in tools

```bash
php artisan app:sync-builtin-mcp
```

This adds Chatvia's built-in tools (charts, forms, weather, currency, web
search) to the tool catalog and makes them searchable. It uses your embedding
provider, so run it after step 6. Run it again after every upgrade.

### 8. Start the background processes

Chatvia needs three long-running processes next to the web server:

| Process | Command | What it does |
| --- | --- | --- |
| Queue workers | `php artisan horizon` | Reads files and web pages, sends replies on messaging channels, analytics, exports. |
| WebSocket server | `php artisan reverb:start` | Live updates in the browser: ingestion progress, human handoff, typing. |
| Scheduler | `* * * * * cd /var/www/chatvia && php artisan schedule:run >> /dev/null 2>&1` (cron) | Clean-up, conversation analysis, statistics, backups. |

Keep `horizon` and `reverb:start` running with Supervisor or systemd, and
restart them when they stop.

Your web server must pass WebSocket connections for the `REVERB_HOST` and
`REVERB_PORT` to the Reverb server (`REVERB_SERVER_HOST` and
`REVERB_SERVER_PORT`, port 8080 by default).

### 9. Cache the configuration

```bash
php artisan config:cache
php artisan event:cache
php artisan view:cache
```

Run `config:cache` again every time you change `.env`.

## Configuration overview

`.env.example` groups the settings into sections. The table shows what each
section is for.

| Section | What it sets |
| --- | --- |
| App | Name, URL, environment, logging. |
| Brand | Your product name and links (see [Your brand](#your-brand)). |
| Database | PostgreSQL connection. Analytics, Pulse, Telescope and the AI request log can move to their own databases. |
| Redis, cache, sessions and queues | Redis connection. Keep `SESSION_DRIVER=database` and `QUEUE_CONNECTION=redis`. |
| Mail | How e-mail is sent. |
| Broadcasting / Reverb | The WebSocket server. |
| Storage | Where files are kept (see [Files and storage](#files-and-storage)). |
| Workspaces | Nothing: sign-up, message limits and how long conversations are kept are set in the admin panel (see [Workspaces and sign-up](#workspaces-and-sign-up)). |
| AI providers | Which service does each AI step. See [AI providers](./ai-providers.md). |
| Binaries | Paths to LibreOffice, poppler, pandoc, ffmpeg and Chrome. |
| Security | Built-in tool secret, trusted proxies, cookies, content security policy, rate limits. |
| Channels, Connectors, Social login | Only addresses and versions. The keys are in the admin panel. |
| Observability | Error reports, Horizon alerts, Pulse, Telescope, the AI request log. |
| Advanced tuning | Search, tools, retention and audit settings. The defaults are fine for most installs. |

### Workspaces and sign-up

Sign-up, message limits and how long conversations are kept are not set in
`.env`. Set them in the admin panel, under **Settings → Workspaces**:

| Option | Default | What it does |
| --- | --- | --- |
| Open sign-up | On | Opens the sign-up page at `/register`. People who sign up must confirm their e-mail address before they can use the app, so e-mail sending (the `MAIL_*` settings) must work. Turn it off after you have created your accounts. You can still invite teammates. |
| Monthly message limit of new workspaces | `0` | How many agent replies a new workspace can get each month. It is applied when someone signs up. `0` means no limit. |
| Days to keep conversations | `366` | Older conversations are deleted. A workspace can choose fewer days in its security settings, but never more. |

A change applies from the next request. Every workspace can use every
feature.

To change the limit of one workspace, open **Workspaces** in the admin panel,
open the workspace and click **Set message limit**. Leave the field empty for
no limit. See [Message limits](/guide/account/message-limits) for what is
counted and what happens when a limit is reached.

### Optional features

| Setting | Value in `.env.example` | What it does |
| --- | --- | --- |
| `FEATURE_YOUTUBE_SOURCES` | `false` | YouTube videos as sources. This feature is not finished: videos are saved but not read yet. |

### Your brand

Chatvia can show your own name and links instead of its own:

| Setting | What it changes |
| --- | --- |
| `BRAND_NAME` | Product name in the app, e-mails and file names. The default is `APP_NAME`. |
| `BRAND_WEBSITE_URL`, `BRAND_DOCS_URL`, `BRAND_API_DOCS_URL` | Links shown in the app. Leave one empty to hide that link. |
| `BRAND_POWERED_BY_ENABLED`, `BRAND_POWERED_BY_LABEL`, `BRAND_POWERED_BY_URL` | The "Powered by ..." line under hosted chats and widgets. Each agent can also hide it. |

### Embedding storage

Chatvia stores a vector (an embedding) for every chunk of your knowledge, and
for every tool and form, to find the ones that match a question. Two settings
decide how. They are read when the tables are created and **cannot change
afterwards**:

| Setting | Default | What it does |
| --- | --- | --- |
| `EMBEDDING_VECTOR_TYPE` | `halfvec` | `halfvec` stores 2 bytes per dimension, half the space of `vector` (4 bytes), with almost the same search quality. |
| `EMBEDDING_DIMENSIONS` | `1024` | The size of each vector. 1024 works with every embedding provider; other sizes limit your choice (see [AI providers](./ai-providers.md#search-vectors-embeddings)). |

After the migrations, `php artisan app:check-embedding-storage` compares the
settings with the database. It fails, with the columns that differ, when
someone changed them after install. Run it after every upgrade (the Docker
image does).

Keyword search is different: it is a switch in the admin panel (**Settings →
Knowledge → Knowledge search**) that you can change at any time. When it is
on (the default), agents search by keywords as well as by meaning.

### Files and storage

Chatvia uses three storage disks:

| Disk | Holds | Who can read it |
| --- | --- | --- |
| `private` | Knowledge files, database backups | Only the app |
| `public` | Chat attachments, images from documents | Anyone with the link |
| `assets` | Profile photos, widget logos | Anyone with the link |

With no storage settings, all three use the local disk under `storage/app`.
Run `php artisan storage:link` once so public files load at `/storage`.

To use object storage instead, set the `SCALEWAY_*` settings. The names start
with `SCALEWAY_`, but any S3-compatible service works. When `SCALEWAY_BUCKET`
is set, the `private` disk uses that bucket and the `public` disk uses
`SCALEWAY_BUCKET_PUBLIC`. The `assets` disk uses Bunny CDN when
`BUNNYCDN_STORAGE_ZONE` is set. To choose a driver yourself, set
`FILESYSTEM_PRIVATE_DRIVER`, `FILESYSTEM_PUBLIC_DRIVER` (`local` or `s3`) or
`FILESYSTEM_ASSETS_DRIVER` (`local` or `bunnycdn`).

::: warning Backups
The scheduler runs `php artisan app:backup-database` twice a day. It saves a
`pg_dump` copy of the database on the `private` disk and keeps the newest 14.
With the local disk, the backups stay on the same server. Use object storage
for the `private` disk, or copy the backups somewhere else.
:::

### Hosted chat addresses

Every agent's hosted chat page lives under `APP_URL`, at `/chat/{agent}`: for
example `https://chat.example.com/chat/support` when `APP_URL` is
`https://chat.example.com`. `{agent}` is the agent's chat address (you can
change it on the agent's **Persona** page) or its ID. The embed snippets in the
dashboard use the ID, so they keep working after a rename. You need no extra
DNS records or certificates.

The hosted chat has its own sign-in, separate from the dashboard: signing in to
a chat never signs you in to the dashboard, and the reverse.

An agent can also have its own domain (for example `support.example.org`):

1. Point the domain at your server with a DNS record. For a subdomain, use a
   CNAME to your `APP_URL` host.
2. Add a TLS certificate for that domain to your web server or proxy.
3. Enter the domain under **Setup → Domain** in the agent.

The domain then shows only that agent's chat.

### The chat widget

The widget that customers put on their own websites is called Shakomako. Its
code is in the `shakomako/` folder. Build it into `public/shakomako`:

```bash
npm ci --prefix shakomako
npm run shakomako:build
```

The built widget needs no settings of its own:

- It loads its other files from the folder its `embed.js` came from.
- It uses the `/widget` API on the same server.
- It gets the live-chat (Reverb) address from the server. The server sends
  `VITE_REVERB_HOST`, `VITE_REVERB_PORT` and `VITE_REVERB_SCHEME` from `.env`,
  the same values the dashboard is built with. When they are empty, the widget
  connects to the same server, so your web server must forward `/app/` to
  Reverb.

The embed snippets in the dashboard point to `{APP_URL}/shakomako/embed.js`.
To serve the widget from somewhere else, for example a CDN, set
`WIDGET_SCRIPT_URL`.

Other websites load these files, and the loader reads `version.json` with a
browser request, so nginx must allow any origin for `/shakomako/`. A release
folder never changes after it is built, so it can stay in caches for a long
time:

```nginx
location ^~ /shakomako/ {
    add_header Access-Control-Allow-Origin "*" always;
    add_header Cache-Control "public, max-age=31536000, immutable" always;
    try_files $uri =404;
}

location = /shakomako/embed.js {
    add_header Access-Control-Allow-Origin "*" always;
    add_header Cache-Control "public, max-age=300" always;
    try_files $uri =404;
}

location = /shakomako/version.json {
    add_header Access-Control-Allow-Origin "*" always;
    add_header Cache-Control "no-cache" always;
    try_files $uri =404;
}
```

Each agent has a list of websites where its widget may load. To allow a host
for every agent, add it to `WIDGET_ALWAYS_ALLOWED_ORIGINS`.

### Security settings

Before you open the install to other people, check these settings:

| Setting | Value |
| --- | --- |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_URL` | Your `https://` address |
| `SESSION_SECURE_COOKIE` | `true` |
| `TRUSTED_PROXIES` | The IP addresses or ranges of your load balancer or CDN. Without it, every visitor has the proxy's IP address and rate limits stop working. |
| `BUILTIN_MCP_SECRET` | A long random value. The built-in tools stop working while it is empty. |
| `REVERB_APP_SECRET` | A long random value. |
| `CSP_MODE` | Starts as `report-only`. Switch to `enforce` when the reports are clean. |
| `DB_SSLMODE` | `require` or `verify-full` when the database is on another server. |
| Open sign-up (admin panel, **Settings → Workspaces**) | Off once your accounts exist, unless you want open sign-up. |

Chat answers stream as server-sent events. Chatvia sends the header
`X-Accel-Buffering: no`; make sure no proxy between the app and the browser
buffers the response. A streaming answer keeps one PHP-FPM worker busy until
it ends, so give PHP-FPM enough workers.

### Staff tools

Horizon (`/horizon`), Pulse (`/pulse`), Telescope (`/telescope`, off by
default) and the AI request log (`/spectra`) open only for an admin who is
signed in to the admin panel with two-factor authentication.

## Upgrading

Read `CHANGELOG.md` of the new version first, and make a backup of the
database.

```bash
cd /var/www/chatvia
git pull                     # or check out the new release tag

composer install --no-dev --optimize-autoloader
npm ci
npm run i18n:merge
npm run build
npm ci --prefix shakomako
npm run shakomako:build

php artisan migrate --force
php artisan app:check-embedding-storage
php artisan app:sync-builtin-mcp

php artisan config:cache
php artisan event:cache
php artisan view:cache

php artisan horizon:terminate   # your process manager starts Horizon again
php artisan reverb:restart
```

Then reload PHP-FPM, so it loads the new code.

From the first release on, migrations only move forward: a released
migration is never changed. Queue workers keep running old code until they
restart, which is why `horizon:terminate` is part of every upgrade.

## Next steps

- [AI providers](./ai-providers.md): choose a service for each AI step.
- [Embed the widget](/embed/widget) on a website.
- Messaging channels: [WhatsApp](/guide/channels/whatsapp),
  [Microsoft Teams](/guide/channels/msteams),
  [Instagram](/guide/channels/instagram). The Meta app for Messenger,
  Instagram and WhatsApp is set up in the admin panel, under
  **Settings → Channels**.
