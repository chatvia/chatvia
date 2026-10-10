# Run with Docker

The Chatvia image runs the whole app: the web app, the queue workers, the
scheduler and the WebSocket server. The compose file adds PostgreSQL (with
the search extensions) and Redis. You need Docker with Docker Compose 2.24 or
newer.

The image is published at `ghcr.io/ahmadmayahi/chatvia`, with one tag per
release (for example `1.4.2`, `1.4`, `1` and `latest`).

## Start

Download the compose file into an empty folder and start it:

```bash
mkdir chatvia && cd chatvia
curl -fsSLO https://raw.githubusercontent.com/ahmadmayahi/chatvia/main/docker/compose.yaml
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
2. Go to **Settings → AI providers** and enter the API key of your chat and
   embedding provider. See [AI providers](./ai-providers.md).
3. Add the built-in tools (charts, forms, weather, currency, web search) to
   the tool catalog. This needs the embedding key from step 2:

   ```bash
   docker compose exec app php artisan app:sync-builtin-mcp
   ```

## Settings

Put your settings in a `.env` file next to `compose.yaml`. The file
[`docker/.env.example`](https://github.com/ahmadmayahi/chatvia/blob/main/docker/.env.example)
lists the common ones. Every setting from the project's `.env.example` works
too. API keys are not set in this file: enter them in the admin panel.

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
| `MAIL_*` | write to the logs | Your SMTP server. Needed for sign-up, invitations and password resets. |

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

Every agent's hosted chat lives on a subdomain (`{agent}.chat.example.com`).
Add a wildcard DNS record and a certificate that covers it. See
[Hosted chat addresses](./index.md#hosted-chat-addresses).

## Upgrade

```bash
docker compose pull
docker compose up -d
```

The migrations run before the new version serves requests.

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

## Not in the image

- **The chat widget.** It is a separate project in the `widget/` folder. See
  [The chat widget](./index.md#the-chat-widget).
- **Chrome.** Exporting a chat as a PDF needs it, so that export does not work
  in the image.
