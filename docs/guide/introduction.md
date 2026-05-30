# Introduction

Chatvia turns your own content into an AI chatbot.

You upload files, point at URLs, paste in text, or import YouTube videos. Chatvia parses them, breaks them into chunks, builds vector embeddings, and stores them in a search index. When a visitor asks a question, your agent retrieves the most relevant chunks and uses them to ground its answer — so it stays on-topic and on-brand.

You can then put that agent in front of users in three ways:

- **Hosted chat page** — every agent gets a shareable URL on your subdomain.
- **Embeddable widget** — a floating chat bubble you drop on any website with one `<script>` tag.
- **Full-page embed** — a ChatGPT-style interface you can host inside your own app.

This documentation covers the dashboard (creating agents, adding sources, customizing the widget, viewing analytics) and the developer surface (REST API, sign-in tokens, functions, MCP connectors).

![SCREENSHOT — dashboard overview](/screenshots/dashboard.png)

## What's in this guide

- **[Quick start](/guide/quick-start)** — create an agent and put it on a page in 5 minutes.
- **[Core concepts](/guide/core-concepts)** — the mental model: accounts, teams, agents, sources, conversations.
- **[Agents](/guide/agents/types)** — agent types, persona, model, appearance, behavior.
- **[Knowledge sources](/guide/sources/overview)** — files, web, YouTube, text, Q&A pairs.
- **[Tools & extensions](/guide/tools/functions)** — let your agent call your APIs (functions) or remote MCP servers.
- **[Identity verification](/guide/account/identity)** — sign your own users in to protected agents.
- **[Public API](https://chatvia.ai/docs/api)** — auto-generated REST reference for programmatic access.
- **[Embed](/embed/widget)** — drop the widget into any site.

## What Chatvia is not

- **Not a general-purpose LLM playground.** Agents are scoped to the knowledge you give them.
- **Not a CMS.** Sources are a search index — re-uploading is cheaper than editing.
- **Not a multi-region cloud.** Chatvia is hosted in the EU, optimized for GDPR.

## Need help?

- Reach support from the **Support** link in the top nav of the dashboard.
- For API questions, the OpenAPI schema is auto-generated at [`/docs/api`](https://chatvia.ai/docs/api) on your Chatvia instance.
