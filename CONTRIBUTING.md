# Contributing to Chatvia

Thank you for your interest in Chatvia. Bug reports, fixes, documentation and
new features are all welcome. This guide explains how to set up the project,
how to check your change, and how to send it.

- Found a security problem? **Do not open an issue.** Follow [SECURITY.md](SECURITY.md).
- Please follow our [Code of Conduct](CODE_OF_CONDUCT.md).
- Read [ARCHITECTURE.md](ARCHITECTURE.md) before a bigger change. It explains
  how the code is organised, the conventions, and the words we use.

## Before you start

- **Small fixes** (typos, small bugs, docs): open a pull request directly.
- **Bigger changes** (new features, new dependencies, schema changes, changes
  to how a module works): open an issue first and describe your idea. This
  saves you time if the change does not fit the project.
- Search the existing issues and pull requests first, so you do not repeat work.

## Development setup

The [README](README.md#getting-started) has the full steps. In short:

```bash
docker compose up -d          # PostgreSQL (with the search extensions), Redis, Mailpit
composer install
npm ci
cp .env.example .env && php artisan key:generate
php artisan chatvia:db:create-tool-databases   # Pulse's and Spectra's databases, when missing
php artisan migrate --seed
composer dev                  # app server, queue worker, logs and Vite
```

You need PHP 8.5, Composer 2 and Node 22. The system tools listed in the
README (pandoc, catdoc, poppler, ffmpeg, WeasyPrint) are optional: without
one, the app does its work itself or refuses only the files it cannot read.
Tests of a tool skip when it is not installed.

With only Docker on your machine, use Laravel Sail instead: it runs the app,
the queue worker, the scheduler, Reverb and Pulse's server stats in
containers, with every system tool installed. See [Develop with Laravel Sail](README.md#develop-with-laravel-sail).

## Making a change

1. Fork the repository and create a branch from `main`. Use a short, clear
   name, for example `fix/widget-reconnect` or `feature/slack-threads`.
2. Make your change. Keep each pull request focused on **one** thing.
3. Add or update tests (see [Tests](#tests)).
4. Run the checks (see [Checks](#checks)): `composer ci` while you work,
   `composer ci:check` before you push.
5. Add a line to the `Unreleased` section of [CHANGELOG.md](CHANGELOG.md) when
   users or self-hosters will notice the change.
6. Open a pull request against `main` and fill in the template. Explain
   **what** changed and **why**. Link the issue if there is one.

Commit messages: a short summary in the imperative mood ("Add Slack thread
replies", not "Added ..."), then a blank line and more detail if needed.

A maintainer will review your pull request. Please be patient; we may ask for
changes. By contributing, you agree that your work is released under the
project's [MIT License](LICENSE).

## Checks

There are two Composer scripts. Both are defined in `composer.json`.

### `composer ci:check`: the read-only gate

This is exactly what CI runs (`.github/workflows/tests.yml`). It **never
changes a file**. It runs, in this order, and stops at the first failure:

| Step | Command | What it checks |
|---|---|---|
| 1 | `composer validate --strict` | `composer.json` is valid. |
| 2 | `composer audit` | No known security problems in PHP packages. |
| 3 | `npm audit --omit=dev` | No known security problems in production npm packages. |
| 4 | `composer format:check` (`pint --test`) | PHP code style. |
| 5 | `composer refactor:check` (`rector process --dry-run`) | Rector would not change anything (PHP 8.5 and Laravel 13 rules, types, dead code). |
| 6 | `composer analyse` (`phpstan analyse`) | PHPStan with Larastan on `src/` (`phpstan.neon.dist`). |
| 7 | `npm run format:check` (`prettier --check resources/`) | Frontend code style. |
| 8 | `npm run lint:check` (`eslint .`) | ESLint rules for Vue and TypeScript. |
| 9 | `npm run typecheck` (`vue-tsc --noEmit`) | TypeScript types in the frontend. |
| 10 | `npm run test` (`vitest run`) | The frontend unit tests: `*.test.ts` files next to the code they test. |
| 11 | `npm run i18n:check` | Every static `t('…')` key exists in `en.json`, and every locale's messages compile. |
| 12 | `composer test` | The Pest suite (Unit, Feature, Architecture, Eval). |

CI also checks the shell scripts and workflows in two small jobs of their
own: ShellCheck on the installer, `docker/manage` and the scripts in the
image, actionlint on `.github/workflows`, and a parse of `install.sh` and
`docker/manage` with bash 3.2, the bash of macOS. After changing one of them,
run:

```bash
shellcheck install.sh docker/manage docker/app/bin/* docker/app/entrypoint.d/*.sh
/bin/bash -n install.sh && /bin/bash -n docker/manage   # bash 3.2 on macOS
actionlint                                                # brew install actionlint
```

### `composer ci`: fix, then check

`composer ci` first fixes what tools can fix, then runs `composer ci:check`:

1. `npm run format` (`prettier --write resources/`)
2. `npm run lint` (`eslint . --fix`)
3. `composer format` (`pint`)
4. `composer refactor` (`rector process`)
5. `composer ci:check`

It changes files, so look at `git diff` afterwards and commit the fixes you
want.

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

- The frontend's plain TypeScript (helpers in `resources/js/lib`, say) is
  tested with [Vitest](https://vitest.dev): a `name.test.ts` next to
  `name.ts`, run by `npm run test`.
- We use [Pest](https://pestphp.com). Unit tests live in `tests/Unit/`,
  feature (HTTP) tests in `tests/Feature/`, architecture rules in
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
- The suite runs one process at a time; `--parallel` is not supported.
- Run one file or folder while you work:

  ```bash
  php artisan test --compact tests/Unit/Agent
  vendor/bin/pest tests/Architecture        # fast: no app, no database
  ```

- Every Action that has real behaviour (a branch, a transaction, an event, an
  external call) needs a unit test: build the DTO, call `execute()`, and check
  the result and the dispatched events (`Event::fake()`).
- Two architecture tests keep **shrink-only allowlists** (both are empty
  today): `ActionsHaveTestsTest` (every Action has a test) and
  `ClassesHaveDocblocksTest` (every class has a docblock with at least one
  sentence of prose before its `@` tags). A new Action without a test, or a
  new class without a docblock, fails. When you fix an allowlisted entry, the
  test fails until you remove it from the list. Both print their coverage on
  every run.
- Fix the code, not the test. Do not weaken an assertion to make a test pass.
- Tests must not call real external services. Use `Http::fake()` and the fakes
  in `tests/Support/`. `TestCase` calls `Http::preventStrayRequests()`, so a
  request that no fake answers fails the test instead of going out.
- Put a unit test at the path of the class it tests:
  `tests/Unit/Knowledge/Retrieval/Services/KnowledgeBaseTest.php` for
  `src/Domain/Knowledge/Retrieval/Services/KnowledgeBase.php`, App code under
  `tests/Unit/{Admin,Http,Identity,Mcp}/…` (`UnitTestLayoutTest` checks it). Use the factories
  and the helpers in `tests/Pest.php` rather than building models by hand. A
  helper function you declare in a test file is global, so give it a short
  prefix of its own (`cscChunk()`), or two files will clash.

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

- Every component uses `<script setup lang="ts">`. Components call the server
  through the typed modules in `resources/js/api/`, never axios directly.
- Destructive confirmations use the shadcn `AlertDialog`, never `confirm()`.
- Use logical Tailwind classes (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`,
  `end-*`, `gap-*`) so right-to-left languages work.
- Format dates with `useDateFormat` (or `useLocaleDate`).
- Theme CSS variables already hold a full color: use `var(--name)`, never
  `hsl(var(--name))`.
- `resources/js/components/ui/` is generated by shadcn-vue. Put your own
  shared components in `resources/js/components/common/`.

### Translations (i18n)

1. Use `t('area.key')` for every UI string.
2. Add the key to a fragment file in
   `resources/js/i18n/locales/_fragments/`, named `{area}.{locale}.json`
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

## Pull request checklist

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
