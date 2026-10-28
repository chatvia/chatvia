# Changelog

All notable changes to ChatVia.ai are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

This is the first public release. The list below groups the changes by
theme.

### Search runs on plain PostgreSQL (VectorChord replaced)

- **The VectorChord extensions are gone** (`vchord`, `vchord_bm25`,
  `pg_tokenizer`; the project is no longer maintained). Vector search now uses
  pgvector's HNSW indexes, and keyword search has two drivers: Timescale's
  actively maintained [pg_textsearch](https://github.com/timescale/pg_textsearch)
  (true BM25 ranking; bundled in the Docker image and installed by the Ubuntu
  installer), and PostgreSQL's built-in full-text search as the fallback.
- **ChatVia.ai now runs on managed PostgreSQL** (for example RDS or Cloud SQL):
  only the pgvector extension is required. `SEARCH_LEXICAL_DRIVER=auto`
  (the default) uses pg_textsearch when it is installed and the built-in
  full-text search when it is not; `SEARCH_TEXT_CONFIG` picks the text search
  configuration (default `simple`, which works for every language). After
  changing either, run `php artisan chatvia:search:lexical-indexes`.
- The Postgres Docker image is now `ghcr.io/chatvia/postgres`
  (pgvector + pg_textsearch), replacing `tensorchord/vchord-suite`. Its tags
  are `18-r<revision>` (our build number; the extension versions are image
  labels, see `docker inspect`) and the moving `18`.
- Embedding dimensions are capped at what the HNSW index accepts: 4000 for
  `halfvec`, 2000 for `vector` (providers with larger native sizes support
  shortening).

### Admin panel: simpler AI setup and Settings

- **Each AI key is entered once**, on a new **Settings → AI providers** page:
  Scaleway AI, OpenRouter, OpenAI, Anthropic, Mistral, Google Gemini, Groq,
  Cohere, Jina, Voyage AI and Azure Document Intelligence, plus servers of your own
  (OpenAI-compatible). Each provider shows what it does here (chat,
  embeddings, speech to text, documents, reranking), with what it is used for
  marked. A key is checked before it is saved. Before, a chat key was saved
  twice (setup and Settings → AI) and the copies could differ.
- A new **Settings → AI models** page: add chat models from a connected
  provider's list, remove one (its agents move to the model you pick), the
  default for new agents, the embedding service, the reranker, document
  reading and speech to text. Before, models could only be added by running
  the setup again.
- **The embedding model can be changed after sources are trained.** Settings
  → AI models warns that agents cannot find documents until everything is
  embedded again, and asks before it saves. Saving starts a **re-index** in
  the background queue: every document, Q&A and tool gets new vectors from the
  stored text (files are not read again), then partitioned vector indexes are
  rebuilt. The Embeddings section shows its progress, and **Re-index
  documents** starts one by hand. Before, the embedding model was locked once
  a source was trained.
- **Azure Document Intelligence** reads PDFs, Word, slides and images, as
  Mistral OCR does. Without one of them, PDF, Word, slides and images cannot
  be uploaded; text, Markdown, spreadsheets and ebooks always work.
- **Each reader gets only the formats it documents.** Mistral OCR: PDF,
  .docx, .pptx and common images; Azure: PDF, .docx, .pptx and JPEG, PNG,
  BMP, TIFF, HEIF. Other images are sent as PNG (and fitted to Azure's 50 to
  10,000 pixels); Excel, CSV, .doc, .ppt and RTF are read by the app (Excel
  keeps its tables, which Azure would lose). Settings → Knowledge → File
  types shows what reads each type now, and greys out the types nothing can
  read.
- **Settings → AI models → Documents is clearer.** Document reading is Mistral
  OCR, Azure Document Intelligence or Off, and "Whole-PDF reading" is now
  **Summaries and PDF chat** (Mistral or Off). There is no "Automatic": each
  list shows the service in use, marks each one connected or not, and greys
  out the ones that are not. A new pick is checked with a real call as it is
  saved (Mistral OCR reads a small test image) and refused if the call
  fails. With only Azure connected, the hosted chat and the widget no longer
  fail on a PDF model that Azure does not have.
- **Reranker, Speech to text and Voice conversations work the same way.**
  There is no "Automatic" and no "Default: …" line: each list shows the
  service in use by its product name (OpenRouter, Cohere Rerank, Scaleway AI
  Whisper, OpenAI Whisper, OpenAI Realtime), marks each provider connected or
  not, and greys out the ones that are not. Self-hosted and Off stay. A new
  pick is checked with a real call as it is saved, and refused if the call
  fails: the reranker reranks two test texts (also when only its model or
  your server's address changes), speech to text and voice check the
  provider's key.
- **Azure's free tier is caught.** It reads only the first 2 pages of a
  file, without an error: the connection check warns about a free (F0)
  resource, and a PDF that comes back with 2 of more pages fails with that
  reason instead of training part of it. Azure's own reason for refusing a
  file (such as the 4 MB free-tier limit) is shown as the failure reason.
- **PDFs locked with a password** are refused at upload (knowledge files,
  the API and chat attachments) with that reason; no reader can open them.
- The **setup** of a new install is its own page, with every step listed on
  the left. Its first step checks the server: the database, Redis, the
  queue (Horizon), the scheduler and live updates (Reverb), each with what
  to do when it does not run, and keyword search. pg_textsearch is
  recommended there, with why it ranks better and how to install and load
  it; when the database has it loaded, one button turns it on and rebuilds
  the search indexes. It then asks for everything the app needs: the AI provider and its
  key, one or more chat models and the default, the embedding model (checked
  with a test request; with OpenRouter, picked from its embedding models,
  OpenAI text-embedding-3-small by default, with the same key), the document
  reader, website import (Firecrawl), speech to text, storage (this server or
  S3-compatible, checked), and the mailer (with a clear warning that nobody
  gets emails without it). The reader, Firecrawl, speech to text and the
  mailer start on "Not now", which turns them off; storage starts on this
  server. Every key is checked, and entered once: a later step uses a key
  typed in an earlier step or saved already. With Mistral OCR and files on
  this server, the setup explains that Mistral reads each file through a
  signed link to the site, and warns when the site's address cannot be
  reached from the internet. Going on without a document reader asks
  first, as PDF, Word, slides and images then cannot be uploaded. A last
  step shows "Complete", the choices
  made, and where the rest (chunking, search, the reranker…), which keeps
  its default values, is changed in Settings. It adds the built-in tools
  when it finishes. The admin panel stays closed until it is done or skipped; skipping
  warns what does not work yet, and a skipped setup can be finished later
  from Settings ("Finish the setup"). How it ended is kept as
  `setup_status` (`completed` or `skipped`).
- **Agents pick their model again** (dashboard → agent → Setup → Model),
  from the models an admin made available, with the default marked. An
  agent without a model answers with the default one (web chat, channels
  and evals), and the setup gives the default model to agents made before
  any model existed, such as the demo agents.
- **Settings → Knowledge**, **Splitting** and **Search** replace Ingestion,
  Chunking and Search. Knowledge holds what agents learn from: file types,
  website import and AI enhancement. Splitting holds the chunk options and
  their preview. Search holds hybrid search, results per question and
  follow-up questions, with the fine-tuning under **Advanced**.
- Technical options on every page sit under a closed **Advanced** section:
  addresses, rate limits, queue workers, security headers, redirect URLs and
  the like. Each page has one **Save changes** button that stays in view.
- **Settings → Platform → Queue workers** says "Restart needed" while the
  queue (Horizon) runs other worker numbers than the saved ones, and has a
  **Restart queue** button. It works in Docker too, where Horizon runs in its
  own container. The running jobs finish first, then Docker (or Supervisor,
  or systemd) starts Horizon again with the new numbers.
- The overview's statuses are honest: "Optional" for sign-in buttons not set
  up, the connected providers, "Website import off", and so on.
- The prompt editor (Settings → Prompts) is removed; prompts live in
  `resources/prompts`.
- A new **Logs** page shows the log files in `storage/logs`, with a button
  to clear one. Only admins signed in to the admin panel can open it. Docker
  installs now write logs to a file per day in `storage/logs` (the newest 14
  are kept) as well as to the container output.

### Notification center

- **A bell in the dashboard header** shows a red badge with the number of
  unread notifications, and the browser tab shows it too ("(3) Agents").
  Its list opens what each notification is about, with "Mark all as read"
  and "Clear all"; a full page lists them all, with an Unread tab.
- Notifications arrive live, without reloading the page, and reads in one tab
  clear the badge in the others.
- What sends one: a visitor asking for a person (to the people who can take
  over; it is marked read for everyone once someone does), a file, text or
  website that finished training or failed, a new lead, a blocked prompt
  injection or prompt leak, a safety check that could not run, security
  changes (a new API token, security policy, IP allowlist, retention, the
  audit webhook turning off), a sign-in from a new device, 80% and 100% of
  the monthly message limit, an agent's rate limit turning messages away, a
  finished eval run and a ready data export. Repeats gather into one line
  while unread ("5 sources finished training").
- **Account → Notifications**: each person chooses, per category, whether it
  rings the bell, shows a desktop alert and plays a sound. Desktop alerts and
  sounds come from one open tab only.
- Notifications are kept 7 days and deleted every night. Admins can change
  the number of days in **Settings → Workspaces → Notifications**.

### Inbox

- **A new Inbox link in the dashboard header** lists everyone chatting with
  the workspace's agents right now, across all agents: who is online, who is
  waiting for a person (first in the list, with a red badge on the link),
  and the chats you are in. It refreshes on its own.
- Open a chat to read it and reply. Sending takes the chat over from the AI;
  **Give back to AI** hands it back. Typing, read marks and new messages
  show live.
- Live replies work in the Chat Widget only. Chats on other surfaces open
  read-only, with a note. A chat a colleague is in shows their name, and the
  reply box stays locked.
- The Inbox follows the Chat Log permissions: people see the agents whose
  chats they may view, and reply where they may take over.

### Voice conversations

- **Visitors can talk with an agent out loud** in the hosted chat and the chat
  widget. A new button next to the microphone opens a voice view with a
  glowing orb that moves with the voice, live captions, Mute and End. It runs
  on the OpenAI Realtime API: the audio goes straight from the browser to
  OpenAI, and the OpenAI key never reaches the browser.
- The agent keeps its instructions. Every answer starts with a search of its
  knowledge (as typed chats do), and it can use its tools that need no
  approval. While it searches, it says a short "Let me check, one moment" in
  the visitor's language.
  Approval-gated tools, forms and charts stay text-only.
- **Both sides are saved as messages**: in the thread, in Chat Logs (marked
  "Voice") and in analytics. Each spoken answer counts against the message
  limits, which are checked before every answer.
- Each call holds one queue worker for its whole length (a new `voice` Horizon
  supervisor; Settings → Platform → Queue workers → Live voice calls), so that
  number is also the most calls at once.
- Admin: **Settings → AI models → Voice conversations**: on or off, the
  default voice, the longest call (at most 25 minutes) and the model. Each
  agent turns it on in Setup → Settings → Composer options, and may pick its
  own voice. It is off by default: a call costs much more than a typed chat.
- The hosted-chat embed code now has `allow="microphone"`, so voice input
  and voice calls work when the chat is embedded on another site.

### Knowledge search

- The reranker is a real one now, picked in **Settings → AI models →
  Reranker** from a list of the strongest: Cohere Rerank 4 Pro (the default),
  Voyage rerank-3, Qwen3 Reranker 8B and more, through OpenRouter, Cohere
  directly, or your own server with the common `/rerank` API.
  Before, the Scaleway "reranker" only compared embeddings again. Without a
  reranker, a basic similarity check keeps greetings and off-topic questions
  from pulling in unrelated text. Each model has its own minimum score.
- **Reasoning, with the thinking shown live.** An admin can let agents
  reason (**Settings → AI models → Let agents reason**, off by default) and
  marks which models reason (a new **Reasons** switch per model, read from
  models.dev for the models it lists). Each agent then picks how hard its model thinks: Off,
  Low, Medium or High (**Setup → Model**). Off by default. In the hosted
  chat and the playground, the thinking streams in grey above the answer, in
  a block the chatter can fold away. It is never saved, never shown while
  answers are held for the guardrails, and never sent to the API or channels.
  With OpenAI's own API, a summary of the thinking is shown (OpenAI may
  leave it out on some answers, and sends none until the organization is
  verified). Thinking that a server puts inline as `<think>…</think>`
  (LM Studio, Ollama) no longer leaks into the answer.
- **OpenAI's newer models work.** Their API refuses `top_k`, `max_tokens`
  and, for reasoning models, any temperature or top-p but the default; the
  app now sends what each model takes. Chats with OpenAI models now use
  OpenAI's Responses API.
- **Anthropic is a new AI provider**: the Claude models, with one key.
  Chats use Anthropic's own API, so tools, the thinking (shown when the
  agent shows it) and prompt caching all work; a repeated system prompt and
  tool list cost less on later turns. Anthropic makes no embeddings: you
  need a second provider for them.
- **Each model's details come from models.dev**, an open list of AI models:
  whether it reasons, how hard it can think, whether it takes a
  temperature, whether it can use tools and follow a JSON schema, and the
  longest answer it can write. Each call sends a model only what it takes:
  no temperature, top-p or top-k to a model that refuses them, never a longer
  answer than it can write, and no tools to a model that cannot use them (it
  answers without them). A model that cannot follow a JSON schema gets the
  schema in its instructions instead. Chat with a PDF follows the same
  rules. The details are kept with each model you add, refreshed once a day,
  on the setup's first page, and with **Refresh model details** in Settings →
  AI models (switch: **Look up model details on models.dev**). Nothing is
  guessed from a model's name any more. A model that is not asked to reason
  (the app's own checks, or an agent at Off) thinks as little as it can, so
  answers and guardrail checks start sooner.
- **A model keeps only what is needed**: its provider, its name at that
  provider, its details from models.dev and whether it is on. Agents,
  settings, messages and analytics point at it by its id, so the same model
  can come from two providers. Its description, icon, context size,
  features and location are gone; the pickers show its name and provider.
- **Voyage AI** can make the embeddings (voyage-4 by default; 256, 512,
  1024 or 2048 dimensions). Documents and questions are sent with Voyage's
  own `document` and `query` types, which Voyage tunes for search.
- Agents get up to 5 parts of their sources per question (was 3), within a
  token budget (**Settings → Search**).
- The text of a search result keeps its line breaks and the words inside
  HTML tags (before, `<span>Price: €10</span>` was removed completely).
- Follow-up questions ("and how much is it?") are understood: an AI step
  writes the full question from the chat for the search, on by default
  (**Settings → Search**). The agent still answers what the person wrote.
- A saved Q&A answer is used when the message is one of its questions as a
  whole (ignoring case and punctuation), or very close to one in meaning.
  Before, one word such as "price" could trigger it.
- Answers show their sources in the Hosted Chat and the Chat Widget: the file
  name (with the page of a PDF), the website page title with its link, or the
  start of a text. The model also gets each source's name, so it can tell
  documents apart.
- Each answer's sources (which part of which file, page or text, how relevant)
  are stored with the answer. The Chat Log shows the answer's real confidence.
- When the knowledge does not cover a question, the agent says so politely and
  offers the closest next step.
- Website pages keep their title, to name them as sources.
- A website can refresh on its own: daily, weekly or monthly (Sources → Web,
  on each trained website). Only new and changed pages are trained again.
- Chunk enrichment (a short summary and the questions each part likely
  answers, written by the Smart model) is on by default. Turn it off in
  **Settings → Knowledge** to save model calls.
- Long document pages are split into parts of the chunk size. Before, a long
  PDF page was one large part that matched questions badly.
- PDF pages read by Mistral OCR get the right page numbers.
- A new offline eval measures knowledge search (`tests/Eval`).

### Security

- Fixed many security problems found in an internal audit and during the
  refactor. They cover sign-in and account checks (deactivated accounts,
  two-factor authentication, a separate sign-in for the admin panel),
  access control between workspaces, agents and team roles, connector OAuth,
  signing of internal tool calls and outgoing webhooks, protection against
  server-side request forgery, CSRF protection on the hosted chat, rate limits
  on public endpoints, and secrets in logs. Each fix has a regression test.
  Self-hosters should update to this version.
- A content security policy, set in the admin panel (**Settings → Security**:
  off, report only or enforce; the default is report only).
- Chat lookups (stream, feedback, rename) are scoped to the caller's own
  session, and malformed widget requests are answered with a client error
  instead of a server error.
- **Guardrails are on by default** for new agents.
- **Personal-data masking no longer hides normal support data.** Order,
  invoice and tracking numbers, dates, times and IP addresses are no longer
  taken for phone numbers. The email and phone in the business profile are
  never masked, and a new **Never mask** list (in Manage filters) allows
  more values or a whole email domain.
- The refusal message is translated into the agent's languages when you save
  it, and a blocked visitor sees it in their own language.
- The prompt-injection check reads a message together with the chat before
  it, and the message is marked as data so it cannot instruct the check.
- Knowledge chunks are dropped only for clear injection text. Before, help
  text such as "You are now logged in" removed the chunk from answers.
- When the safety check cannot run, the Activity log shows **Skipped**, so
  you can see the message was not checked. Activity can be filtered by guard
  and outcome.
- The Chat Widget now shows the checked answer. Before, it kept showing the
  streamed text when a guardrail masked or replaced the answer.
- **Tool and MCP results are checked for prompt injection** before the agent
  reads them. A result with instructions aimed at the agent is withheld.
- **Answers are checked too**: an answer about a blocked topic or outside the
  allowed scope is replaced with the refusal, and an answer that repeats the
  agent's hidden instructions is stopped (a secret marker at the top of the
  system prompt shows the leak).
- **Blocked topics** have a description, example questions and a response:
  the refusal message, a reply of their own (translated), or a hand-off to a
  person from the team (Chat Widget with live chat on).
- **Personal data**: new filters for bank accounts (IBAN), UK National
  Insurance numbers and IP addresses, your own patterns (regular
  expressions), and a choice per type to mask the data or block the message.
- Each guard can **log only** instead of blocking, and prompt injection and
  toxicity have a **sensitivity** setting (low, medium, high).
- **Test a message** on the Guardrails page shows what a visitor would get
  with the settings on the page, before you save.
- **Check the agent's answers**: while showing them (the default; an
  answer that fails is replaced), before showing them (the visitor sees an
  answer only after it passed, all at once), or not at all (one AI call
  less per answer; personal data in answers is still masked).
- **Email alerts** about blocked attacks and checks that could not run (at
  most one an hour), and blocked attacks are written to the security audit
  log (and so to the SIEM webhook).
- The Activity log shows what a guard found in plain words and links to the
  conversation.
- **A rate limit per visitor** (Security → Rate limiting), so one person
  cannot use up the agent's limit for everyone. The default is 8 messages a
  minute; it also applies on the messaging channels.

### License

- ChatVia.ai is free and source-available under the [ChatVia.ai License](LICENSE):
  the MIT terms plus one condition. The "Powered by ChatVia.ai" credit must
  stay under every chat unless the install has a license key.
- Community files: `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`
  and issue forms (bug report, feature request, documentation). The project
  does not accept pull requests: a workflow closes those from outside the
  project and points to the issue forms.
- New `ARCHITECTURE.md`: how the code is organised, the conventions and the
  glossary. The README was rewritten for new contributors and self-hosters.
- The product name and links come from the admin panel (**Settings → Brand**). There
  are no hard-coded links, personal e-mail addresses or vendor account ids
  any more.
- Fonts are served by the app itself. For page-view analytics, paste the
  script of any analytics service in the **tracking code** box
  (**Settings → Platform**). Nothing is added while it is empty.
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

- **One command prepares the database.** `php artisan chatvia:deploy:migrate`
  creates the analytics, Pulse and Spectra databases when they are missing,
  migrates, checks the embedding settings, rebuilds the vector indexes when
  `EMBEDDING_INDEX` changed and adds the built-in tools (only a warning
  without an embedding API key). The Docker image runs it on every start,
  and the installer's `deploy.php` runs it in place of the recipe's
  `artisan:migrate`, so both installs run the same steps. A Deployer deploy
  now rebuilds the vector indexes too.
- **Complete installation without Docker.** On Linux, `install.sh` now asks
  how to install: with Docker, as before, or everything directly on a fresh
  Ubuntu 24.04 LTS (or newer) server (`--complete`). On releases older than
  26.04 it takes PostgreSQL 18 from the PostgreSQL archive
  (apt.postgresql.org) and Composer from getcomposer.org. It installs and sets
  up PostgreSQL 18 with the search extensions and the Pulse and Spectra
  databases, Redis with a password, PHP-FPM behind nginx, Node.js 22, a Let's
  Encrypt certificate, Horizon, Reverb and Pulse under Supervisor, the
  scheduler in cron, and the firewall (the SSH ports stay open), creates the
  `chatvia` user, and deploys the release with Deployer into its own folder
  under `/var/www/chatvia`. It asks you to point the domain at the server
  first, checks its A record (check again, use another address, go on anyway
  or stop), warns that it takes over the whole server, checks at the end that
  every part runs, and runs again to upgrade (after a database backup, without
  downtime) or to finish an install that stopped.
- **Automatic updates on the complete installation.** The installer asks
  (yes by default; `--no-auto-update` or `CHATVIA_AUTO_UPDATE=no` to skip)
  and installs `chatvia-auto-update`: once a night it looks for a newer
  release of the installed major version and runs the installer's upgrade
  (database backup, then a Deployer deploy that switches only when every
  step worked). A new major version is only logged, never installed by
  itself. `sudo chatvia-auto-update status|run|on|off`; log in
  `/var/log/chatvia-auto-update.log`.
- **Deployer keeps older widget versions.** Every release's
  `public/shakomako` links to `shared/shakomako`, which keeps the folder of
  each widget version, so a page that loaded the widget before a deploy
  still finds its files afterwards. `embed.js` and `version.json` switch to
  the new version right after the new release goes live, and back on a
  rollback.
- `npm run shakomako:build` keeps its copy of the widget in
  `storage/framework/shakomako` instead of `node_modules/.cache`: Node does
  not run TypeScript files under `node_modules`, so the TypeScript widget
  did not build there.
- **Pulse** shows the app's health at `/pulse`: slow requests, queries and
  jobs, exceptions, queues, cache, Reverb connections and messages, and the
  servers' CPU, memory and disk. It opens for admins signed in to the admin
  panel, like Horizon. Pulse keeps its data in a database of its own
  (`DB_PULSE_DATABASE`, by default `pulse`), so its many small writes stay
  out of the app's database and its backups; the Docker stack creates it,
  and runs `pulse:check` in a new `pulse` container (`./manage status`,
  `restart` and `logs` include it). Data older than 7 days is removed.
- **Spectra** logs every AI call at `/spectra`: the model, the tokens, the
  cost and the time it took, also for streamed replies. It opens for the
  same admins, and keeps its log in a database of its own
  (`DB_SPECTRA_DATABASE`, by default `spectra`). `chatvia:db:create-tool-databases`
  creates the Pulse and Spectra databases (it replaces
  `app:create-pulse-database`).
- **Every AI call in Spectra has a tag** that says what it is for, so you can
  filter the requests by it: `chat-reply`, `follow-up-rewrite`,
  `guardrails`, `tool-routing`, `knowledge-search`, `search-embedding`,
  `rerank`, `conversation-title`, `conversation-analysis`, `embedding`,
  `chunk-enhancement`, `document-summary`, `transcription`, `evaluation`,
  `service-check` and more. The full list is `Domain\Llm\Enums\AiCallTag`.
  A chat turn's search embedding and rerank also carry `tool-routing` or
  `knowledge-search`, so you can see which search made them.
- **Settings → Platform** turns Pulse and Spectra on and off. Pulse records
  from the start; Spectra records only once an admin turns it on, because it
  keeps what is sent to the AI. The Spectra card can also keep only counts,
  costs and times (without prompts and replies), and sets how many days a
  call is kept (30 by default). **Tools** links to Spectra next to Horizon
  and Pulse.
- **`./manage postgres tune` sizes PostgreSQL to its machine.** It reads the
  memory and processors the database container actually gets (a container
  limit counts), computes the usual sizes for a web app on SSD storage —
  `shared_buffers`, `effective_cache_size`, `maintenance_work_mem` (what the
  search index builds use), `work_mem`, the WAL sizes, the planner's SSD
  costs and the parallel workers — shows every change next to the running
  value, asks, then checks and applies the file (and offers the restart some
  settings need). Its lines in `postgres/postgresql.conf` are marked `# set
  by Chatvia tune`: a new run replaces them and never your own edits. It
  warns when `shared_buffers` outgrows the container's shared memory
  (`shm_size`). The complete installation tunes the same way from the
  server's memory and processors, into PostgreSQL's `conf.d/chatvia.conf`.
- **The queue workers size themselves to the machine.** `php artisan
  chatvia:workers:tune` reads the memory and processors (a container limit
  counts) and saves a worker count per pool — chat, files and websites,
  analytics, audio and video, voice — as the Settings → Platform values,
  where you change them later. `--dry-run` only shows the numbers,
  `--restart` restarts a running Horizon so they apply. Both installers run
  it once at the end of an install, so a new install starts sized to its
  machine instead of the smallest server's defaults (in Docker:
  `./manage artisan chatvia:workers:tune --restart`).
- **Redis 8.** The Docker stack runs `redis:8.10-alpine`, and the complete
  installation takes the newest Redis from packages.redis.io when it carries
  that Ubuntu release (Ubuntu 26.04's own Redis 8 otherwise).
- **PHP 8.5.12 or newer.** The complete installation takes PHP from Ondřej
  Surý's PPA (`ppa:ondrej/php`), which carries each PHP release soon after it
  is out, and stops before it changes anything when the PPA has no packages
  for the server's Ubuntu release. An upgrade brings PHP to the newest 8.5
  release first. The Docker image is built on `serversideup/php` 8.5.12.
- **fail2ban protects SSH** on the complete installation: repeated failed
  sign-ins ban the address, read from the systemd journal.
- Docker upgrades: `.env` names the exact version an install runs (the
  installer turns `latest` into the release number). `./manage upgrade`
  shows the change and the release notes, backs up, downloads everything
  before anything stops, and goes back on its own when the new version does
  not start. `./manage upgrade --version 1.4.2` picks a version, and
  `./manage rollback` goes back to the version before an upgrade with the
  backup made before it.
- Almost every setting moved from `.env` to the admin panel, under
  **Settings**, so an install can be set up without editing files. One page
  per topic: AI providers, AI models, Knowledge, Workspaces, Sign-in,
  Security, Email, Channels, Connectors, Brand, Platform and Storage. Each
  log's retention is set next to what it records (Security for the
  guardrail, tool-call and security logs; Workspaces for conversations), and
  the services behind the built-in Perplexity, Weather and Currency tools are
  under Connectors. This covers API keys and OAuth apps, the AI services, models and
  addresses, e-mail (SMTP or Resend, with a test e-mail button), file
  storage, brand, default language, the content security policy, the chat
  widget, the tracking code, Horizon alerts, log retention and the tool rate limit.
  Secrets are encrypted and never sent back to the browser. A blank field
  keeps its default, which `.env` can still preset (optional). `.env` keeps
  only what the app needs to start (database, Redis, drivers, Reverb and so
  on), plus the embedding storage, the
  built-in tools' secret and address (`BUILTIN_MCP_*`) and
  `TRUSTED_PROXIES`.
- **Program paths are set in the admin panel** (**Settings → Platform →
  Programs**): pdfinfo, pdftotext, pandoc, catdoc, catppt, ffmpeg, ffprobe
  and WeasyPrint. Each shows whether the server finds it, and suggests the
  path when the program is in a usual folder (such as Homebrew's) that the
  PATH misses. A path must end in the program's own name, so the panel
  cannot be used to run another program. The `*_BIN_PATH` and
  `FFPROBE_PATH` variables are no longer read.
- **EPUB and FB2 books need no pandoc.** Without pandoc, the app reads them
  itself. FB2 books are always read by the app: pandoc garbles books saved in
  windows-1251 (common for Russian books) and leaves out their tables. Their
  page count now comes from the number of characters, so books in every
  language are counted.
- **No document format needs a program.** The app reads RTF files, old Word
  files (`.doc`, back to Word 6) and old PowerPoint files (`.ppt`) itself,
  and Word (`.docx`) files when pandoc is missing, with their headings,
  lists, tables and footnotes. pandoc, catdoc and catppt are now only a
  backup for RTF, `.doc` and `.ppt` files: on sample files the app kept every
  word they found, while pandoc left out the footnotes and tables of RTF
  files, and catdoc and catppt could show text that a later save of the file
  had replaced.
- `MAIL_URL` is no longer read. Set up e-mail in the admin panel
  (**Settings → Email**).
- Sign-in buttons (Google, Microsoft, GitHub, Slack) only show for the
  providers that are set up in the admin panel (**Settings → Sign-in**).
- Settings opens on an overview of tiles, one per topic; each topic shows
  its cards (a service or a group of options), technical ones last under
  **Advanced**.
- Text is split into chunks inside ChatVia.ai; no separate service is needed.
  **Settings → Knowledge** holds the chunk size and overlap, measured
  in tokens or in characters, the strategy for each kind of content
  (Markdown, text and transcripts), all under **Advanced**, and a **Try
  splitting** preview that splits a sample before you save. Markdown (web pages, .md
  files, ebooks) is split by its headings: code blocks and tables stay whole
  when they fit, and every chunk starts with the headings above it. Text is
  split at paragraphs, then sentences and words; transcripts by whole
  sentences; a fixed size is also on offer. Arabic letters, accents and
  emoji are never cut in two.
- The **tokenizer** is a setting. It counts the chunk size when it is in
  tokens, and the size of every stored chunk.
- `php artisan chatvia:settings:list` lists the admin-panel settings (never secrets);
  `php artisan chatvia:settings:set group.property value` sets one, for installers
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
  (Mistral OCR or Azure Document Intelligence), reranking, speech to text and
  whole-PDF reading. Each one uses the first connected provider that can do
  it, and "off" when none is. Keys are set in the admin panel (**Settings →
  AI providers**), services and models in **Settings → AI models**.
- ChatVia.ai stops with a clear error when the embedding model does not return
  the stored vector size (`EMBEDDING_DIMENSIONS`, 1024 by default).
- API keys and OAuth apps for outside services are managed in the admin panel
  under **Settings** and stored encrypted.
- File storage is set in the admin panel (**Settings → Storage**): this
  server or S3-compatible storage. Knowledge files follow it too: the default
  disk is always `private`, and `FILESYSTEM_DISK` is no longer read. With
  nothing set, files stay on the local disk.
- S3-compatible storage takes up to three buckets, each with its own
  endpoint, region and keys: a private bucket (required), a public bucket
  with its public URL for profile photos and agent logos (else they stay on
  this server, and the page says so), and a backups bucket (else backups go
  to the private bucket). Bunny
  CDN support and the `BUNNYCDN_*` / `FILESYSTEM_ASSETS_DRIVER` keys are
  removed: put a CDN in front of the public bucket instead.
- `php artisan chatvia:admin:create-user` creates an admin with its own workspace,
  the usual way to create the first account.
- `.env.example` was rewritten: a fresh copy starts without paid services.
- An installation guide in the documentation
  (<https://chatvia.ai/docs/guide/installation>).
- The widget can be pointed at any server at runtime with `data-api-url` and
  `data-reverb-*` attributes on the embed tag.

### Database

- **Faster pages and chats on large installs.** A full review of the schema
  and the queries it serves:
  - New or reshaped indexes for the chat log, waiting handoffs, the API's
    message pages, the chat log's confidence filter, the Q&A match on every
    chat turn, the knowledge checks on an agent's Setup page, the website
    refresh and the account analytics.
  - Lists that show the first or latest message of each conversation now read
    one message per conversation instead of all of them.
  - Unused and duplicate indexes were removed, so every write costs less.
- **Fewer writes on busy chats.** A widget page load and the widget's
  heartbeat write at most once a minute, and never move a conversation in the
  chat log. A new message still does.
- **Permission checks no longer load every role of every workspace** on each
  request.
- **Large deletes run in batches**: a knowledge source's chunks, a website's
  links, a form's leads, an agent's removal and a workspace's audit rows.
  Deleting a form now says how many leads are deleted with it.
- **Tables no longer grow without end.** New daily prunes remove devices not
  seen for 180 days (`chatvia:prune:known-devices`), invitations 30 days after
  they expire (`chatvia:prune:invitations`), expired password reset links, and
  per-conversation analytics older than about 13 months. Expired data exports
  are now deleted. A daily sweep (`chatvia:agent:purge-deleted`) finishes
  removing deleted agents whose first attempt failed.
- **Staff emails are stored in lowercase.** Sign-in, sign-up, password reset
  and invitations ignore the case of what was typed, and two accounts can no
  longer differ only in case.
- **Migrations are dated on one timeline** from July 2025, in the same order
  as before. A development database migrated before this change needs
  `php artisan migrate:fresh --seed`.

### Analytics at scale

- **Analytics have a database of their own** (`DB_ANALYTICS_DATABASE`,
  default `analytics`), next to the Pulse and Spectra databases.
  `chatvia:db:create-tool-databases`, the Docker stack and the Ubuntu
  installer create it. `./manage backup`, the app's own backups and the
  Ubuntu installer's pre-upgrade backup include it.
- **Every analytics number reads a small table, however long the history.**
  The hourly rollup is one row per agent and hour (it no longer splits by
  country and model), a new daily rollup serves ranges over 92 days and
  lifetime totals, and countries have a daily table of their own. On 7.5
  million test messages, the admin panel's all-time overview went from 7
  seconds to 8 ms, and the dashboard and agent pages to a few milliseconds.
- **Reply-time percentiles come from a histogram** on each rollup row, so the
  median and p95 cover any range (they were limited to the raw events'
  13 months) within about 15%.
- **The raw message log is split by month** when pg_partman is installed (it
  is in the `ghcr.io/chatvia/postgres` image, and the Ubuntu installer adds
  it): the daily prune drops an expired month in one step. Without pg_partman
  the log is one table, pruned in batches as before.
- **Thumbs feedback has its own table** (`analytics_message_feedback`), and
  the channel and device numbers read the analytics tables instead of the
  chat sessions and conversations.
- **New agents analyze sentiment, tone and topics from their first chats**
  (on by default; each costs extra AI calls and can be turned off under
  Setup → Settings → Analytics).
- **Quick database writes have their own queue.** Analytics counters and
  audit rows run on `records`, with a **Records** option in Settings →
  Platform → Queue workers, so they never wait behind the AI analysis on
  `analytics`.

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
- **Chat titles at once on every web surface.** The Chat Widget and the
  Command Bar now ask for the conversation's title right after the first
  reply (`POST /widget/conversations/title`), as Hosted Chat does, so it is
  there when the visitor opens the history. For them a queued job a minute
  later names a conversation the browser did not (an older widget, a closed
  page), and does nothing once it has a title. Channels and the API still
  get their title from the queue at once.
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
- Text sources always got the extra AI step during ingestion, even when it was
  turned off. They now follow their own setting, **Enhance text sources** in
  the admin panel (**Settings → Knowledge**; on by default, as before).
- The default OAuth callback URLs (sign-in and the Meta channels) are built
  from `APP_URL`, so they stay https behind a proxy that ends TLS.
- The tool-call and security audit pages failed to load when a recorded
  payload had been cut to the size limit. The cut text is now shown, marked
  as cut off.
- Deleting a team that still had teammates failed with a server error. It is
  now refused with a message, like a team that still has agents. A team
  whose deleted agents are still being removed can no longer be deleted.
- Two operators who took over the same chat at the same moment could both
  succeed. The second one is now refused.
- A Facebook Page or Instagram account could not be connected again while a
  deleted agent that used it was still being removed.
- Two sign-ins from a new device at the same moment could fail.
- The security webhook's failure count could miss failures that happened at
  the same time.
- Using a knowledge source in an answer no longer changes its "updated" time.
- The chat log's message pages could repeat or skip messages sent in the same
  second.

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
  vector and BM25 search extensions, Redis and Mailpit, with
  health checks and an empty `testing` database for the test suite. Its
  `sail` profile runs the app, Horizon, the scheduler and Reverb too.
- Editor and AI-assistant settings, generated IDE helper files and the
  generated `resources/js/ziggy.js` are no longer in the repository.
- ESLint is configured with `typescript-eslint` and `eslint-plugin-vue`
  directly; `@vue/eslint-config-typescript` was removed (its file scanner
  pulled in a vulnerable `braces`).
- The unused Postmark mailer and Slack notification channel were removed. Use
  SMTP or Resend for mail.
- One create migration per table holds the whole schema; there are no upgrade
  or data-fix migrations.
