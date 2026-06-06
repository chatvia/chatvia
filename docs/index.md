---
layout: home

hero:
  name: "Chatvia"
  text: "The AI workspace for your business"
  tagline: A privacy-first, EU-hosted platform where your team builds, governs, and ships AI agents grounded in your business knowledge.
  image:
    src: /logo.svg
    alt: Chatvia
  actions:
    - theme: brand
      text: Get started
      link: /guide/introduction
    - theme: alt
      text: API reference
      link: https://chatvia.ai/docs/api
    - theme: alt
      text: Embed the widget
      link: /embed/widget

features:
  - icon: 🤖
    title: Knowledge-grounded RAG agents
    details: Upload files, point at URLs, paste text, or import YouTube videos. Chatvia handles parsing, chunking, embeddings, and retrieval for every agent in your workspace.
    link: /guide/agents/types
    linkText: Build an agent

  - icon: 💼
    title: Unified chat workspace
    details: One ChatGPT-style hub at /chat where your team's chatters switch between every agent they have been granted access to. No more hopping between tabs.
    link: /guide/core-concepts
    linkText: How the chat hub works

  - icon: 💬
    title: Embeddable widget
    details: One script tag drops a chat bubble on any customer-facing page. Configure colors, position, language, starters, and gating per agent.
    link: /embed/widget
    linkText: Embed the widget

  - icon: 🔐
    title: Identity verification
    details: Sign your customers in with short-lived JWTs. Protected agents only answer people your business has authenticated.
    link: /guide/account/identity
    linkText: Mint sign-in tokens

  - icon: 🧩
    title: Functions & MCP connectors
    details: Extend any agent with webhook-backed functions or one-click MCP connectors — GitHub, Slack, Jira, Linear, Notion, Stripe, Atlassian, and custom MCP servers. Per-tool approval keeps your team in control.
    link: /guide/tools/functions
    linkText: Add tools

  - icon: 📊
    title: Conversation analytics
    details: Topics, sentiment, tone, retrieval sources, and per-country breakdowns. See what your customers ask and how each agent answers.
    link: /guide/chat/analytics
    linkText: Open analytics

  - icon: 🙋
    title: Human handoff
    details: A customer can request a human at any point. Your teammates take over from the dashboard and hand back to the AI when the issue is resolved.
    link: /guide/chat/handoff
    linkText: How handoff works
---

<!-- ![SCREENSHOT — landing hero placeholder; save as docs/public/screenshots/hero.png](/screenshots/hero.png) -->

## Where to start

- **New to Chatvia?** Read the [Introduction](/guide/introduction) and follow the [Quick start](/guide/quick-start).
- **Embedding into a customer site?** Jump to the [Widget guide](/embed/widget).
- **Building an integration?** Browse the auto-generated [API reference](https://chatvia.ai/docs/api). Get a key in [API keys](/guide/account/api-keys).
- **Signing your customers in?** See [Identity verification](/guide/account/identity).
