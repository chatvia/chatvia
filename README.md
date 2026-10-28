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
  <a href="https://github.com/chatvia/chatvia/actions/workflows/tests.yml"><img src="https://github.com/chatvia/chatvia/actions/workflows/tests.yml/badge.svg?branch=main" alt="Tests"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-ChatVia.ai-blue.svg" alt="ChatVia.ai License"></a>
  <br>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-8.5-777bb4.svg?logo=php&logoColor=white" alt="PHP 8.5"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13-ff2d20.svg?logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://filamentphp.com"><img src="https://img.shields.io/badge/Filament-5-fdae4b.svg?logo=filament&logoColor=white" alt="Filament 5"></a>
  <a href="https://www.postgresql.org"><img src="https://img.shields.io/badge/PostgreSQL-18-336791.svg?logo=postgresql&logoColor=white" alt="PostgreSQL 18"></a>
  <a href="https://github.com/pgvector/pgvector"><img src="https://img.shields.io/badge/pgvector-0.8-336791.svg" alt="pgvector 0.8"></a>
  <a href="https://github.com/timescale/pg_textsearch"><img src="https://img.shields.io/badge/pg__textsearch-1.5-336791.svg" alt="pg_textsearch 1.5"></a>
  <a href="https://github.com/pgpartman/pg_partman"><img src="https://img.shields.io/badge/pg__partman-5.5-336791.svg" alt="pg_partman 5.5"></a>
  <a href="https://redis.io"><img src="https://img.shields.io/badge/Redis-8-ff4438.svg?logo=redis&logoColor=white" alt="Redis 8"></a>
  <br>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia.js-3-9553e9.svg?logo=inertia&logoColor=white" alt="Inertia.js 3"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue-3.5-4fc08d.svg?logo=vuedotjs&logoColor=white" alt="Vue 3.5"></a>
  <a href="https://www.typescriptlang.org"><img src="https://img.shields.io/badge/TypeScript-6-3178c6.svg?logo=typescript&logoColor=white" alt="TypeScript 6"></a>
  <a href="https://tailwindcss.com"><img src="https://img.shields.io/badge/Tailwind_CSS-4-06b6d4.svg?logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"></a>
  <a href="https://vite.dev"><img src="https://img.shields.io/badge/Vite-8-646cff.svg?logo=vite&logoColor=white" alt="Vite 8"></a>
  <a href="https://nodejs.org"><img src="https://img.shields.io/badge/Node.js-22-5fa04e.svg?logo=nodedotjs&logoColor=white" alt="Node.js 22"></a>
</p>

<p align="center">
  <a href="https://chatvia.ai/#hero-video"><img src=".github/demo-video.jpg" alt="Watch how ChatVia.ai works" width="100%"></a>
  <br>
  <sub><a href="https://chatvia.ai/#hero-video">Watch how ChatVia.ai works</a></sub>
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
**[chatvia.ai/docs](https://chatvia.ai/docs/)**. See all features at
[chatvia.ai/features](https://chatvia.ai/features).

- [Introduction](https://chatvia.ai/docs/guide/introduction) and [quick start](https://chatvia.ai/docs/guide/quick-start)
- [Installation](https://chatvia.ai/docs/guide/installation): with [Docker](https://chatvia.ai/docs/guide/installation/docker), on [Ubuntu](https://chatvia.ai/docs/guide/installation/ubuntu) or [from source](https://chatvia.ai/docs/guide/installation/from-source)
- [Core concepts](https://chatvia.ai/docs/guide/core-concepts) and [how it works](https://chatvia.ai/docs/guide/how-it-works)
- [Agents](https://chatvia.ai/docs/guide/agents/create): [persona](https://chatvia.ai/docs/guide/agents/persona), [models](https://chatvia.ai/docs/guide/agents/models) and the [playground](https://chatvia.ai/docs/guide/agents/playground)
- [Knowledge sources](https://chatvia.ai/docs/guide/sources/overview) and [search](https://chatvia.ai/docs/guide/how-it-works/search)
- Tools: [MCP connectors](https://chatvia.ai/docs/guide/tools/connectors) and [functions](https://chatvia.ai/docs/guide/tools/functions)
- Where people chat: the [widget](https://chatvia.ai/docs/embed/widget), a [full page](https://chatvia.ai/docs/embed/full-page), [Microsoft Teams](https://chatvia.ai/docs/guide/channels/msteams), [WhatsApp](https://chatvia.ai/docs/guide/channels/whatsapp) and [Instagram](https://chatvia.ai/docs/guide/channels/instagram)
- Conversations: [chat logs](https://chatvia.ai/docs/guide/chat/logs), [leads](https://chatvia.ai/docs/guide/chat/leads), [analytics](https://chatvia.ai/docs/guide/chat/analytics) and [human handoff](https://chatvia.ai/docs/guide/chat/handoff)
- Your account: [teams](https://chatvia.ai/docs/guide/account/teams), [API keys](https://chatvia.ai/docs/guide/account/api-keys) and [message limits](https://chatvia.ai/docs/guide/account/message-limits)

To work on the code, read [ARCHITECTURE.md](ARCHITECTURE.md) and
[CONTRIBUTING.md](CONTRIBUTING.md).

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
  - **Pro** ($399, one time): lifts the free-use limits (one workspace, ten
    agents, 10,000 agent replies a month, no API, no capabilities or forms)
    and hides the "Powered by ChatVia.ai" credit. Without a key, the limits apply and the credit must
    stay under every chat.
  - **Enterprise** ($1,490 a year): also turns on the Enterprise features
    (SIEM delivery, the network allowlist and country rules, audit-log export
    and data retention), with priority email support.
  - **SaaS** ($9,999 a year): Enterprise, plus the right to sell ChatVia.ai to
    other people and companies as a hosted service. Running it for your own
    company does not need one.
