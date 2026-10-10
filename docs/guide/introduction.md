# Introduction

Chatvia is a B2B AI workspace. Your business deploys AI agents to your team and to your customers - grounded in the content you own.

Your workspace uploads files, points at URLs, pastes text, or connect to MCP/Functions from your server.

Every agent has three surfaces at once:

- **Hosted Chat**: a standalone chat page at `chatvia.ai/chat/{agent}`, or a custom domain via CNAME.
- **Widget**: an embeddable floating chat bubble that drops onto any host page with one `<script>` tag.
- **Bar**: a search-style trigger that opens a full-screen chat modal inside your own app.

This documentation covers the dashboard (creating agents, adding sources, configuring surfaces, managing members, viewing analytics) and the developer surface (REST API, sign-in tokens, functions, MCP connectors).

![SCREENSHOT — dashboard overview](/screenshots/dashboard.png)

## What's in this guide

- **[Quick start](/guide/quick-start)**: create an agent and put it in front of your team in 5 minutes.
- **[Core concepts](/guide/core-concepts)**: the mental model: workspace, teammates, chatters, agents, sources, conversations.
- **[Agents](/guide/agents/types)**: persona, model, appearance, behavior, and the three surfaces.
- **[Knowledge sources](/guide/sources/overview)**: files, web, YouTube, text, Q&A pairs.
- **[Tools & extensions](/guide/tools/functions)**: let your agent call your APIs (functions) or branded MCP connectors (GitHub, Slack, Jira, Linear, Notion, Stripe, ...) with per-tool allow / require approval / blocked permissions.
- **[Identity & access](/guide/core-concepts)**: workspace, teammates (dashboard users), chatters (end-users in `/chat`), and role-based access to agents.
- **[Identity verification](/guide/account/identity)**: sign your own users in to protected agents.
- **[Public API](https://chatvia.ai/docs/api)**: auto-generated REST reference for programmatic access.
- **[Embed](/embed/widget)**: drop the widget or the bar into any site.

## What Chatvia is not

- **Not a general-purpose LLM playground.** Agents are scoped to the sources your workspace gives them.
- **Not a CMS.** Sources are a search index — re-uploading is cheaper than editing.
- **Not a consumer chatbot service.** Chatvia is built for businesses — workspaces, teammates, chatters, and governance over who can talk to which agent.
- **Not a multi-region cloud.** Chatvia is hosted in the EU, optimized for GDPR.

## Need help?

- Reach support from the **Support** link in the top nav of the dashboard.
- For API questions, the OpenAPI schema is auto-generated at [`/docs/api`](https://chatvia.ai/docs/api) on your Chatvia instance.
