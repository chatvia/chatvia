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
  - **Branding** ($199, one time): hides the "Powered by ChatVia.ai" credit.
    Without a key, the credit must stay under every chat.
  - **Enterprise** ($1,490 a year): also turns on the Enterprise features
    (SIEM delivery, the network allowlist and country rules, audit-log export
    and data retention), with priority email support.
  - **SaaS** ($9,999 a year): Enterprise, plus the right to sell ChatVia.ai to
    other people and companies as a hosted service. Running it for your own
    company does not need one.
