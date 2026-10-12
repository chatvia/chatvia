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
- A content security policy, set in the admin panel (**Settings → Platform**:
  off, report only or enforce; the default is report only).
- Chat lookups (stream, feedback, rename) are scoped to the caller's own
  session, and malformed widget requests are answered with a client error
  instead of a server error.

### Open source

- Chatvia is open source under the [MIT License](LICENSE).
- Community files: `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`,
  issue templates and a pull request template.
- New `ARCHITECTURE.md`: how the code is organised, the conventions and the
  glossary. The README was rewritten for new contributors and self-hosters.
- The product name and links come from the admin panel (**Settings → Brand**). There
  are no hard-coded links, personal e-mail addresses or vendor account ids
  any more.
- Fonts are served by the app itself. Page-view analytics (Plausible) only
  load when a Plausible domain is set (**Settings → Platform**).
- Seeders and factories no longer depend on a personal account, and demo data
  is never created in production.
- The public website (landing, pricing, blog, changelog, contact, legal and
  DPA pages) was removed. `/` opens the login page, or the dashboard when
  signed in.

### Billing removed, message limits added

- Billing was removed from the whole product: Stripe and Laravel Cashier,
  plans, message credits and top-ups, auto-recharge, add-ons, quotas, free
  trials and the trial checkout, the password step for trial accounts, the
  founder welcome e-mail, the billing settings and the plan and add-on pages
  of the admin panel, and the Pennant feature flags. The `user_usages` token
  counter and the "billing cycle" analytics periods were removed too. Removed
  packages: `laravel/cashier`, `laravel/pennant` and `@stripe/stripe-js`.
- Every workspace gets every feature: human handoff, sentiment, tone and
  topics, pulse, evals, guardrails, the audit log, API access, the Security &
  Compliance settings (network access rules, retention, security audit log),
  the SIEM webhook, custom domains (no limit on how many) and turning off the
  "Powered by" badge per agent.
- New optional **monthly message limits**. A platform admin sets the limit of
  a workspace in the admin panel (**Workspaces** → a workspace → **Set message
  limit**). It counts every agent reply of the workspace: web chat, widget,
  hosted chat, messaging channels and each eval case that produced an answer.
  A guardrail refusal is not counted. A workspace sets the limit of a chatter
  in the dashboard (**Chatters** → a chatter → **Limits**); it counts the
  chatter's own messages. The count starts again on the first day of each
  month. When a limit is reached, the chat ends with the error
  `message_limit_reached` (or `chatter_message_limit_reached`), messaging
  channels reply with a short "unavailable" message, and eval cases are
  skipped.
- The dashboard's **Messages This Month** card counts this month's agent
  replies, and shows "X of LIMIT" when the workspace has a limit.
- "Ask your data" and its account-analytics tools use `this_month` by
  default. The overview tool reports `messages_used_this_month` and
  `messages_limit_this_month` (`null` without a limit).
- The account data export has `message-limit.json` instead of `billing.json`.

### Self-hosting

- Almost every setting moved from `.env` to the admin panel, under
  **Settings**, so an install can be set up without editing files. One page
  per topic: Sign-in, Channels, AI, Knowledge, Connectors, Email, Brand,
  Workspaces, Platform, and Retention & limits. This covers API keys and
  OAuth apps, the AI services, models and addresses, e-mail (SMTP or Resend,
  with a test e-mail button), brand, default language, the content security
  policy, the chat widget, Plausible, Horizon alerts, log retention and the
  tool rate limit. Secrets are encrypted and never sent back to the browser.
  A blank field keeps its default, which `.env` can still preset. `.env`
  keeps only what the app needs to start, plus storage and program paths.
- Settings opens on an overview of tiles, one per topic; each topic shows a
  tile per service or group of options, and a tile opens its own page.
- A **Settings → Chunking** page: the splitter (the chunker or the built-in
  one), the chunker's address and key, the library and strategy (LangChain,
  LlamaIndex, Chonkie: recursive, sentences, fixed tokens, Markdown headings,
  semantic, ...) with only that strategy's options, and a **Try it** preview
  that splits a sample before you save. The chunker client moved to the
  chunker's new API (`/v1/chunk`, `/v1/engines`, `/v1/tokens`).
- The **tokenizer** is a setting. Token counts come from the chunker with that
  tokenizer, and from Chatvia's own tokenizer when the chunker cannot count.
- `php artisan app:settings` lists the admin-panel settings (never secrets);
  `php artisan app:setting group.property value` sets one, for installers
  and scripts. An API key is read from standard input when the value is left
  out.
- The embedding provider and its model are locked in the admin panel once a
  source is trained, and a provider that cannot return the stored vector size
  is refused.
- A **Settings → Workspaces** page in the admin panel: **Open sign-up** (opens
  `/register`, on by default), **Monthly message limit of new workspaces**
  (applied at sign-up; 0, the default, means no limit) and **Days to keep
  conversations** (366 by default; a workspace can choose fewer days in its
  security settings, never more).
- AI services sit behind contracts with `auto` drivers: document reading
  (Mistral, or local: `pdftotext`, pandoc and catdoc, with no office suite),
  text splitting (chunk.fit or local), reranking, speech to text and
  whole-PDF reading. Each one uses the
  hosted service when its API key is set and a local or "off" option when it
  is not. Services, endpoints and models are set in the admin panel
  (**Settings → AI**).
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
  turn, message storage, the messaging channels).
- Helper functions that hid logic were replaced by request-scoped resolvers
  and constructor injection.
- PHP 8.5 and `declare(strict_types=1)` across the codebase; every class has a
  docblock.
- Dead code, unused routes and broken endpoints were removed.
- Bugs fixed along the way include: deleting the original PDF after
  summarising it, chunks left behind after a bulk URL delete, unlinking a
  shared knowledge file, cached tool lists after a connector or function was
  deleted, and several analytics queries.

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
- ESLint is configured with `typescript-eslint` and `eslint-plugin-vue`
  directly; `@vue/eslint-config-typescript` was removed (its file scanner
  pulled in a vulnerable `braces`).
- The unused Postmark mailer and Slack notification channel were removed. Use
  SMTP or Resend for mail.
- One create migration per table holds the whole schema; there are no upgrade
  or data-fix migrations.
