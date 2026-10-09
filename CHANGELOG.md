# Changelog

All notable changes to Chatvia are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

This is the first open-source release. The list below groups the changes by
theme.

### Security

- Fixed many security problems found in an internal audit and during the
  refactor. They cover sign-in and account checks (deactivated accounts,
  two-factor authentication, a separate sign-in for the admin panel),
  access control between workspaces, agents and team roles, connector OAuth,
  signing of internal tool calls and outgoing webhooks, protection against
  server-side request forgery, CSRF protection on the hosted chat, rate limits
  on public endpoints, and secrets in logs. Each fix has a regression test.
  Self-hosters should update to this version.
- A content security policy, set with `CSP_MODE` (`off`, `report-only` or
  `enforce`; the default is `report-only`).
- Chat lookups (stream, feedback, rename) are scoped to the caller's own
  session, and malformed widget requests are answered with a client error
  instead of a server error.

### Open source

- Chatvia is open source under the [MIT License](LICENSE).
- Community files: `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`,
  issue templates and a pull request template.
- New `ARCHITECTURE.md`: how the code is organised, the conventions and the
  glossary. The README was rewritten for new contributors and self-hosters.
- The product name, links and the operator's company details come from
  configuration (`BRAND_*`, `COMPANY_*`). There are no hard-coded links,
  personal e-mail addresses or vendor account ids any more.
- Fonts are served by the app itself. Page-view analytics (Plausible) only
  load when `PLAUSIBLE_DOMAIN` is set.
- Seeders and factories no longer depend on a personal account, and demo data
  is never created in production.
- The site footer says that Chatvia is open source under the MIT License.

### Self-hosting

- `BILLING_ENABLED=false` runs Chatvia without Stripe: every workspace gets
  every feature, there are no quotas, and the billing pages are off. Plan
  features moved to `config/billing.php`.
- `REGISTRATION_ENABLED` opens normal sign-up (on by default when billing is
  off). `MARKETING_SITE_ENABLED=false` turns the public website off.
- AI services sit behind contracts with `auto` drivers: document reading
  (Mistral or local `pdftotext` + LibreOffice), text splitting (chunk.fit or
  local), reranking, speech to text and whole-PDF reading. Each one uses the
  hosted service when its API key is set and a local or "off" option when it
  is not. Endpoints and models are set in `.env`.
- Chatvia stops with a clear error when the embedding model does not return
  1024 dimensions.
- API keys and OAuth apps for outside services are managed in the admin panel
  under **Settings** and stored encrypted.
- Storage disks fall back to the local disk when no bucket is configured.
- `php artisan admin:create-user` creates an admin with its own workspace,
  the usual way to create the first account.
- `.env.example` was rewritten: a fresh copy starts without paid services.
- A self-hosting guide in the documentation (`docs/guide/self-hosting/`).
- The widget can be pointed at any server at runtime with `data-api-url` and
  `data-reverb-*` attributes on the embed tag.
- YouTube sources are hidden unless `FEATURE_YOUTUBE_SOURCES=true` (they are
  not finished).

### Architecture

- The code follows three layers (`App`, `Domain`, `Support`) with rules that
  architecture tests enforce. `Support` no longer depends on `Domain` or
  `App`, and `Integration` no longer depends on `Conversation`.
- The tool layer is one subdomain, `Domain\Integration\Tool`. The built-in MCP
  servers moved to `App\Mcp`, with their business logic in `Domain`.
- Every domain was refactored: DTOs instead of arrays, backed enums, events
  and manually registered listeners for side effects, thin controllers with
  form requests, and large services split into focused classes (the chat
  turn, message storage, the Stripe webhook, the messaging channels).
- Helper functions that hid logic were replaced by request-scoped resolvers
  and constructor injection.
- PHP 8.5 and `declare(strict_types=1)` across the codebase; every class has a
  docblock.
- Dead code, unused routes and broken endpoints were removed.
- Bugs fixed along the way include: deleting the original PDF after
  summarising it, chunks left behind after a bulk URL delete, unlinking a
  shared knowledge file, cached tool lists after a connector or function was
  deleted, renewals that read the plan from metadata instead of the price,
  add-on quotas after a Checkout purchase, and several analytics queries.

### Frontend

- Large components were split along real UI parts; no file is much over 350
  lines.
- Components call the server through typed modules in `resources/js/api/`;
  explicit `any` types were removed and every component is TypeScript.
- Shared conventions: `AlertDialog` for confirmations, `useDateFormat` for
  dates, logical (right-to-left safe) Tailwind classes, labels on icon-only
  buttons. Custom components moved from `components/ui` to
  `components/common`.
- Every dashboard language has every key; Polish plurals were fixed.
- Fixed listener leaks, chart dates that were one day early west of UTC, and
  the chat composer that did not shrink after sending.

### Fixed

- "Unlink" for a file used by several agents always failed.
- Signing in with an e-mailed link, and the two-factor challenge, did not work
  on an agent's own address (hosted chat).
- The hosted chat showed the PDF upload button when no model could read PDF
  files, so the upload then failed.
- Conversation analysis retried a failing conversation every hour forever. It
  now stops after five attempts.
- The widget could resume a remembered conversation under a new session, after
  which typing, feedback and other actions on it failed. It now starts a new
  conversation in that case.
- Country names in country pickers and security messages are shown in the
  user's language.
- Calls to tools the agent was not offered are now in the tool-call audit log.
- Internal tool calls carry a one-time value, so a captured call cannot be
  sent again. Outgoing webhook and tool calls connect to the address that was
  checked, closing a DNS-rebinding gap.
- The exchange-rate tool sends its API key in a header instead of the URL.
- Deleting a file source also deletes its stored parse result (OCR pages or
  transcript). Stored files of attachments and file sources are deleted only
  after the database delete commits, so a failed delete keeps them.
- German texts use the formal "Sie" everywhere (some messages used "du").
- The documentation site builds even when some screenshots are not added yet;
  a missing screenshot is left out of the page.
- Text sources always got the extra AI step during ingestion, even with
  `CHUNKER_ENHANCE=false`. They now follow their own setting,
  `CHUNKER_ENHANCE_TEXT` (on by default, as before).

### Tooling

- All Composer and npm dependencies were upgraded, including Pest 5,
  `laravel/mcp` 1.0, `spatie/laravel-permission` 8,
  `spatie/typescript-transformer` 3 and Intervention Image 4. Unused packages
  were removed, among them Laravel Octane and RoadRunner.
- `composer ci:check` runs every CI gate without changing files:
  `composer validate` and `composer audit`, `npm audit` for production
  dependencies, Pint, Rector, PHPStan, Prettier, ESLint, `vue-tsc`, the i18n
  check and the Pest test suite. `composer ci` fixes what it can first, then
  runs the same checks.
- Rector applies the PHP 8.5 and Laravel 13 rule sets; CI fails when it would
  still change something.
- Every Action has a unit test and every class has a docblock. Architecture
  tests keep it that way (with shrink-only allowlists) and print the coverage
  on every run.
- CI uses a pinned PostgreSQL image and needs no secrets, so it also runs for
  pull requests from forks. The widget has its own CI workflow.
- One `docker-compose.yml` for local development: PostgreSQL 18 with the
  vector and BM25 search extensions, Redis and Mailpit, with health checks and
  an empty `testing` database for the test suite.
- Editor and AI-assistant settings, generated IDE helper files and the
  generated `resources/js/ziggy.js` are no longer in the repository.

### Upgrade notes

- Run `php artisan migrate --force` and `php artisan app:sync-builtin-mcp`,
  then restart Horizon and Reverb.
- API keys and OAuth apps move from `.env` to the admin panel. A migration
  copies the values that are still in `.env`; after that, the old lines can be
  removed.
- Long ingestion and export jobs use a new `redis-long` queue connection (see
  `config/horizon.php`), so they are no longer started twice.
- Laravel Octane is no longer supported. Run the app with PHP-FPM.
- Queued jobs and events that were serialized under old class names keep
  working for one release.
- Restart Horizon right after deploying: workers that still run the old code
  send internal tool calls without the new one-time value, and those calls are
  refused. To avoid any refused call during the deploy, set
  `BUILTIN_MCP_ALLOW_MISSING_NONCE=true` for that one deploy, then remove it.
- The Postmark mailer and the Slack notification channel were removed; they
  were not used. Use SMTP or Resend for mail.
- ESLint is configured with `typescript-eslint` and `eslint-plugin-vue`
  directly; `@vue/eslint-config-typescript` was removed (its file scanner
  pulled in a vulnerable `braces`). The lint rules are unchanged.
