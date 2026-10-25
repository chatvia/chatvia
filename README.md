<p align="center">
  <img src=".github/banner.svg" alt="ChatVia.ai: self-hosted AI chat agents that answer from your own knowledge" width="100%">
</p>

<p align="center">
  <a href="https://chatvia.ai/docs/"><strong>Documentation</strong></a> ·
  <a href="https://chatvia.ai/docs/guide/installation">Install</a> ·
  <a href="https://github.com/chatvia/shakomako">Chat widget</a> ·
  <a href="https://chatvia.ai/license">License keys</a>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-ChatVia.ai-blue.svg" alt="ChatVia.ai License"></a>
  <img src="https://img.shields.io/badge/PHP-8.5-777bb4.svg" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/Laravel-13-ff2d20.svg" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PostgreSQL-18-336791.svg" alt="PostgreSQL 18">
</p>

---

ChatVia.ai is a free, self-hosted platform for AI chat agents that answer from
your own knowledge base. A team creates agents, gives them knowledge (files,
web pages, text and Q&A pairs), connects them to tools, and puts them in front
of people: on a hosted chat page, in a widget on any website, in a command
bar, through messaging apps, or through a REST API. Answers come from
retrieval-augmented generation (RAG) with hybrid search (vectors + keywords)
inside PostgreSQL. It is designed to work with open-weight models.

## Documentation

Everything you need to install, run and use ChatVia.ai is at
**[chatvia.ai/docs](https://chatvia.ai/docs/)**.

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
  snippets and Q&A pairs.
- Ingestion runs in background queues and reports progress live.
- Hybrid search: vector search ([pgvector](https://github.com/pgvector/pgvector) HNSW)
  and keyword search ([pg_textsearch](https://github.com/timescale/pg_textsearch) BM25,
  falling back to PostgreSQL's own full-text search), merged and reranked.

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
- Public REST API with bearer tokens and OpenAPI docs.

**Conversations**
- Chat logs, leads, analytics and topics, user feedback, human handoff,
  forms inside the chat, and export to PDF.

**Workspaces**
- Teams with roles and permissions, sign-in with two-factor authentication,
  passkeys or social login, and workspace security settings (for example an
  IP allowlist).
- Every workspace gets every feature. Optional monthly message limits: per
  workspace and per chatter.
- An admin panel and a dashboard translated into many languages, including
  right-to-left languages.

## Built with

| Part | Technology |
|---|---|
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Inertia, Vue 3, TypeScript, Tailwind CSS 4, shadcn-vue, Vite |
| Database | PostgreSQL 18 with pgvector; `pg_textsearch` for BM25 keyword search where available |
| Queues and cache | Redis, Laravel Horizon |
| Real-time updates | Laravel Reverb (WebSockets) and server-sent events for streamed answers |
| Admin panel | Filament |
| Tests | Pest |

[ARCHITECTURE.md](ARCHITECTURE.md) explains how the code is organised.
[CONTRIBUTING.md](CONTRIBUTING.md) explains how to set up a development copy
and check a change.

## Contributing, security and license

- Bug reports and feature requests are welcome as
  [issues](https://github.com/chatvia/chatvia/issues/new/choose). We do not
  accept pull requests: a maintainer makes every change. See
  [CONTRIBUTING.md](CONTRIBUTING.md) and our [Code of Conduct](CODE_OF_CONDUCT.md).
- Found a security problem? Please report it privately; see
  [SECURITY.md](SECURITY.md).
- Changes are listed in [CHANGELOG.md](CHANGELOG.md).
- ChatVia.ai is free and source-available under the [ChatVia.ai License](LICENSE),
  for individuals and companies. You may use, change and run it. Three
  [license keys](https://chatvia.ai/license) add more:
  - **Branding** ($199, one time): hides the "Powered by ChatVia.ai" credit.
    Without a key, the credit must stay under every chat.
  - **Enterprise** ($1,490 a year): also turns on the Enterprise features
    (SIEM delivery, the network allowlist and country rules, audit-log export
    and data retention), with priority email support.
  - **SaaS** ($9,999 a year): Enterprise, plus the right to sell ChatVia.ai to
    other people and companies as a hosted service. Running it for your own
    company does not need one.
