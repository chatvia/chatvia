# Run with Docker

The Chatvia image runs the whole app: the web app, the queue workers, the
scheduler and the WebSocket server. The compose file adds the text splitter
(the open-source [chunker](https://github.com/chatvia/chunker), its own image
`ghcr.io/chatvia/chunker`), PostgreSQL (with the search extensions) and Redis.
You need Docker with Docker Compose 2.24 or newer.

Each part runs in its own container: `app` (web), `horizon` (queue workers),
`scheduler`, `reverb` (WebSockets), `chunker`, `postgres` and `redis`. Docker
restarts any of them that stops, and a health check marks one that hangs, so
no process supervisor is needed inside the containers. See them with
`docker compose ps`, and the logs of one with `docker compose logs horizon`.

The chunker is only reachable inside the stack and needs no API key. The
app uses it for every text it splits into chunks. It has its own
[releases](https://github.com/chatvia/chunker/releases), so its version is
set apart from Chatvia's, with `CHUNKER_VERSION`. By default the stack uses
its newest release.

The image is published at `ghcr.io/chatvia/chatvia`, with one tag per
release (for example `1.4.2`, `1.4`, `1` and `latest`).

The image also serves the chat widget, at `/shakomako/embed.js`. The embed
snippets in the dashboard point there, so the widget needs no extra setup.

## Install with one command

On Linux or macOS, the installer does everything for you:

```bash
curl -fsSL https://chatvia.ai/install.sh | bash
```

It:

1. asks for the install folder (`~/chatvia` by default). It holds only
   `compose.yaml` and `.env`: Docker keeps your data. If Chatvia is already in
   that folder, the installer offers to upgrade it;
2. checks that Docker is installed and running, and offers to install it
   (on Linux with Docker's official script, on macOS with Homebrew);
3. asks for the admin e-mail and password, the port and the address;
4. offers **Recommended settings** or **Custom**, where you choose how the
   search data is stored ([embedding storage](./index.md#embedding-storage),
   which can never change later);
5. shows your settings to check, then writes `compose.yaml` and `.env` into
   the folder, starts Chatvia and shows where to sign in.

Your password is passed to the first start only and is never written to a
file. The setup questions run once: run the installer again and pick the same
folder to upgrade. For an install without questions, pass the settings as
options (`--dir` sets the folder):

```bash
curl -fsSL https://chatvia.ai/install.sh | bash -s -- --yes --email you@example.com
```

`--help` lists every option. `--dry-run` shows what would happen without
changing anything.

## Start by hand

Download the compose file into an empty folder and start it:

```bash
mkdir chatvia && cd chatvia
curl -fsSLO https://raw.githubusercontent.com/chatvia/chatvia/main/docker/compose.yaml
docker compose up -d
```

Then open <http://localhost:8000>.

The first start sets up everything by itself:

1. It creates the secrets of your install: the app key, the database
   password and the WebSocket keys. They stay in a Docker volume, so no two
   installs share them.
2. It creates the database and runs the migrations.
3. It creates the first admin account.

## Sign in

Without your own admin password, the first start makes a random one and
prints it once:

```bash
docker compose logs migrate
```

Sign in with `admin@example.com` and that password, then:

1. Open the admin panel at `/admin` and set up two-factor authentication.
2. Follow the setup that opens next. It asks for three things: your AI
   provider, its API key and the model your agents answer with; the search
   service (embeddings) and its key; and, if you want, your email provider
   (SMTP, Amazon SES, Mailgun, Postmark, Resend, ...). Until e-mail is set up, e-mails are only written to the logs, and
   nobody can confirm a sign-up or accept an invitation. See
   [AI providers](./ai-providers.md).
3. Add the built-in tools (charts, forms, weather, currency, web search) to
   the tool catalog. This needs the search key from step 2:

   ```bash
   docker compose exec app php artisan app:sync-builtin-mcp
   ```

## Settings

Put your settings in a `.env` file next to `compose.yaml`. The file
[`docker/.env.example`](https://github.com/chatvia/chatvia/blob/main/docker/.env.example)
lists them. It only holds what Chatvia needs to start. E-mail, API keys, the
AI services, your brand and the other options are set in the admin panel,
under **Settings**.

| Setting | Default | What it does |
| --- | --- | --- |
| `APP_PORT` | `8000` | The port on your machine that serves the app. |
| `APP_URL` | `http://localhost:8000` | The address people use to open the app. Change it with `APP_PORT`, or set your domain. |
| `CHATVIA_VERSION` | `latest` | The image version. Pin a release on a real server. |
| `DB_DATABASE`, `DB_USERNAME` | `chatvia` | First start only. |
| `DB_PASSWORD` | random | First start only. |
| `ADMIN_EMAIL`, `ADMIN_NAME` | `admin@example.com`, `Admin` | First start only. |
| `ADMIN_PASSWORD` | random | First start only. Remove it from `.env` afterwards. |
| `APP_KEY` | random | Your own app key instead of a random one. |
| `CHUNKER_VERSION` | `latest` | The chunker image version. `latest` is its newest release. |
| `CHUNKER_WORKERS` | `2` | Worker processes of the chunker. Each one uses about 200 MB. |

"First start only" settings are read when the database is created. Changing
them later does not change the existing database or account.

A changed setting applies after `docker compose up -d`.

## Ports

Only the web container is published. It listens on port 8080 inside the
container and also passes the browser's WebSocket connections to the
WebSocket server. To use another port, set `APP_PORT` and `APP_URL`:

```dotenv
APP_PORT=9000
APP_URL=http://localhost:9000
```

PostgreSQL and Redis are not published. To reach them from your machine, add
a `ports` entry to their service, as with any Docker container.

## Your own domain and HTTPS

Put a reverse proxy with HTTPS (for example Caddy, Traefik or nginx) in front
of `APP_PORT`. It must pass WebSocket connections on the path `/app/`. Then
set:

```dotenv
APP_URL=https://chat.example.com
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=172.16.0.0/12
```

`TRUSTED_PROXIES` is the address range your proxy connects from. The value
above covers the default Docker networks.

Every agent's hosted chat lives under the same domain, at
`https://chat.example.com/chat/{agent}`, so one DNS record and one certificate
are enough. See [Hosted chat addresses](./index.md#hosted-chat-addresses).

## Upgrade

```bash
docker compose pull
docker compose up -d
```

The migrations run before the new version serves requests. `docker compose
pull` also gets the newest chunker, unless `CHUNKER_VERSION` pins one.

## Back up

Your data is in four Docker volumes:

| Volume | Holds |
| --- | --- |
| `pgdata` | The database |
| `storage` | Uploaded files and the database backups the scheduler makes |
| `secrets` | The app key and the other secrets |
| `redisdata` | Queues and caches |

Keep a copy of the `secrets` volume, or set your own `APP_KEY`. Without the
app key, the API keys in the admin panel cannot be read.

## Commands

Run Artisan commands in the `app` container:

```bash
docker compose exec app php artisan about
docker compose exec app php artisan admin:create-user you@example.com "Your Name"
```

See the logs of all services with `docker compose logs -f`.

## What the image includes

The programs that read file sources (poppler, pandoc, catdoc, ffmpeg),
WeasyPrint for exporting a chat as PDF and for summaries of office files, and fonts for every language the
dashboard offers.
