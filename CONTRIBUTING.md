# Contributing to Chatvia

Thank you for your interest in Chatvia. Bug reports, fixes, documentation and
new features are all welcome. This guide explains how to set up the project,
how to send a change, and the code conventions we follow.

- Found a security problem? **Do not open an issue.** Follow [SECURITY.md](SECURITY.md).
- Please follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

- **Small fixes** (typos, small bugs, docs): open a pull request directly.
- **Bigger changes** (new features, new dependencies, schema changes, changes
  to how a module works): open an issue first and describe your idea. This
  saves you time if the change does not fit the project.
- Search the existing issues and pull requests first, so you do not repeat work.

## Development setup

The [README](README.md#getting-started) has the full steps. In short:

```bash
docker compose up -d          # Postgres (with vector + BM25 extensions), Redis, Mailpit
composer install
npm ci
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
composer dev                  # app server, queue worker, logs and Vite
```

You need PHP 8.5, Node 22 and the system tools listed in the README
(LibreOffice, pandoc, poppler, ffmpeg) only for the features that use them.

## Making a change

1. Fork the repository and create a branch from `main`. Use a short, clear
   name, for example `fix/widget-reconnect` or `feature/slack-threads`.
2. Make your change. Keep each pull request focused on **one** thing.
3. Add or update tests (see [Tests](#tests)).
4. Run every check:

   ```bash
   composer ci:check
   ```

   This runs exactly what CI runs: `composer validate` and `composer audit`,
   `npm audit`, Pint, Rector, PHPStan, Prettier, ESLint, `vue-tsc`, the i18n
   check and the test suite. It never changes files. To fix formatting and
   lint problems automatically first, run `composer ci` (it fixes what it can,
   then runs `ci:check`).
5. Add a line to the `Unreleased` section of [CHANGELOG.md](CHANGELOG.md) when
   users or self-hosters will notice the change.
6. Open a pull request against `main` and fill in the template. Explain
   **what** changed and **why**. Link the issue if there is one.

Commit messages: a short summary in the imperative mood ("Add Slack thread
replies", not "Added ..."), then a blank line and more detail if needed.

A maintainer will review your pull request. Please be patient; we may ask for
changes. By contributing, you agree that your work is released under the
project's [MIT License](LICENSE).

## Tests

- We use [Pest](https://pestphp.com). Unit tests live in `tests/Unit/`,
  feature (HTTP) tests in `tests/Feature/`, architecture rules in
  `tests/Architecture/`.
- Tests run against **PostgreSQL**, not SQLite, because the app uses the
  `vchord`, `vchord_bm25` and `pg_tokenizer` extensions. `docker compose up -d`
  gives you a server with an empty `testing` database. On the first run the
  suite installs the extensions, migrates and seeds that database by itself.
  When migrations change, it rebuilds the schema automatically.
- Run one file or folder while you work:

  ```bash
  php artisan test --compact tests/Unit/Agent
  ```

- Every Action that has real behaviour (a branch, a transaction, an event, an
  external call) needs a unit test: build the DTO, call `execute()`, and check
  the result and the dispatched events (`Event::fake()`).
  `tests/Architecture/ActionsHaveTestsTest.php` enforces this.
- Fix the code, not the test. Do not weaken an assertion to make a test pass.
- Tests must not call real external services. Use `Http::fake()` and the fakes
  in `tests/Support/`.

## Code conventions

Chatvia is a Laravel 13 + Inertia + Vue 3 app. The PHP code is split into three
root namespaces (see `composer.json` → `autoload`):

| Namespace | Folder | What goes there |
|---|---|---|
| `App\` | `src/App/` | The HTTP layer and framework wiring: controllers, form requests, middleware, providers, console commands, the admin panel. |
| `Domain\` | `src/Domain/` | Business logic, one folder per module (`Agent`, `Conversation`, `Knowledge`, `Integration`, `Billing`, ...): Actions, DTOs, Models, Services, Events, Listeners, Jobs. |
| `Support\` | `src/Support/` | Small helpers that do not depend on a module. |

The one exception is `App\Identity\` (`Workspace`, `Chatter`, `ApiKey`). It is
tightly coupled to authentication (Fortify, Sanctum, Socialite, sessions), so
it keeps its Actions, Models and Events under `App\`. **`Domain\` never imports
from `App\`**, except `App\Identity`.

### How a request flows

```
Route → Controller
          → FormRequest (validate + authorize) → toData() → DTO
          → Action::execute(DTO, models...) → model / DTO / void
          → (optional) Job::dispatch(DTO) → Job calls the same Action
        → Controller returns the response (Inertia, JSON, redirect)
```

### Rules for each kind of class

- **Actions** (`src/Domain/{Module}/Actions/{Feature}/{Verb}{Noun}Action.php`):
  one public method, `execute()`. They take DTOs and models, never a `Request`,
  the `Auth` facade or the session. They return a model, a DTO, a collection
  or nothing, never an HTTP response. Wrap multi-step writes in
  `DB::transaction()`. A trivial one-line write (for example
  `$model->update([...])`) does not need an Action.
- **DTOs** (`src/Domain/{Module}/Data/{Feature}/{Noun}Data.php`): extend
  `Spatie\LaravelData\Data`.
- **Form requests** (`src/App/Http/Requests/{Module}/{Feature}/{Verb}{Noun}Request.php`):
  hold `rules()`, `authorize()` and `toData()`, which returns the DTO.
- **Controllers** (`src/App/Http/Controllers/{Module}/`): thin. They call an
  Action and return a response. No database writes, external HTTP calls or
  event dispatching in the controller body. Read-only `index`/`show` pages may
  query directly.
- **Services** (`src/Domain/{Module}/Services/`): wrap external systems and
  stateless utilities (LLM providers, embeddings, file converters). Ask: is
  this something the app *does* (an Action) or something it *uses* (a
  Service)?
- **Events and listeners**: events are named in the past tense
  (`MessagePosted`), listeners by what they do (`SendAgentMemberInvitationEmail`).
  Side effects such as e-mails and notifications go in listeners, not in
  Actions. **Listeners are registered by hand** in
  `DomainServiceProvider::registerEventListeners()`; there is no
  auto-discovery, so a listener you do not register never runs.
- **Jobs**: no logic. They receive a DTO and call an Action.
- **Policies** live next to their model.

### Naming

- Suffixes: `...Action`, `...Data` (DTO), `...Request` (form request), `...Job`,
  `...Notification`, `...Policy`, `...Exception`. Models, Services, Events and
  Listeners have no suffix.
- Module names are singular (`Domain/Agent`, not `Domain/Agents`).
- Acronyms use Pascal case: `Llm`, `Http`, `Mcp`, `Api`.
- Use the project's words: a chat is a **Conversation**, training material is
  a **Source**, an end user of an embedded agent is a **Chatter**, an account
  owner is a **Workspace**, anything the model can call is a **Tool**.
- When a folder of Actions, DTOs or controllers grows past about seven files,
  split it into feature subfolders. Tests mirror that layout.

### Database

- Never edit a migration that is already on `main`. Add a new migration
  instead.
- Some enum-like columns have Postgres `CHECK` constraints. Adding a new value
  needs a migration that updates the constraint.
- Key workspace data to the workspace **owner** (`$agent->user_id`,
  `$user->workspace()->id`), not to the teammate who is acting.

### Frontend (Vue 3 + TypeScript)

- UI text goes through vue-i18n (`t('...')`). Add strings to the fragment
  files in `resources/js/i18n/locales/_fragments/`, then run
  `npm run i18n:merge`. A literal `@` in a message must be written `{'@'}`.
- Use logical Tailwind classes (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`,
  `end-*`, `gap-*`) so right-to-left languages work.
- Destructive confirmations use the shadcn `AlertDialog`, never the browser's
  `confirm()`.
- Theme CSS variables already hold a full color: use `var(--name)`, never
  `hsl(var(--name))`.
- Format dates with the `useDateFormat` composable.

### Comments

Explain **why** the code does something, in plain English. Do not describe
history ("we used to ..."), tickets or people. Give every new class a short
docblock that says what it is for.
