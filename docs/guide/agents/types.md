# Agent surfaces

Every Chatvia agent has three surfaces at once: a **Hosted Chat** page, an embeddable **Widget**, and a **Bar** trigger. You configure the agent once in Setup, and the same persona, model, sources, and branding apply across all three.

![SCREENSHOT — agents index](/screenshots/agents-index.png)

::: tip TL;DR
You do not pick a surface upfront. A single agent serves all three at the same time. Disable any surface you don't want exposed under **Setup → Behavior**.
:::

## Hosted Chat

A standalone chat page, ChatGPT-style: a sidebar with conversation history, a main thread, and an input box at the bottom.

- Lives at `chatvia.ai/chat/{agent}` — the chat address is generated when the agent is created.
- Map a custom domain by pointing it at the Chatvia server. Configure it under **Setup → Domain**.
- Multiple conversations per chatter.
- Conversation starters appear on the empty state.
- Optionally protected — only signed-in chatters can access it.

Good for: customer self-service portals, single-agent embeds, "talk to our docs" pages.

## Widget

An embeddable chat bubble: a small button on a host page that opens a panel with the conversation.

- Embedded with a single `<script>` tag.
- Customizable color, position, language, icon, size.
- Domain whitelist — limit which sites are allowed to load the widget.
- Optional **handoff** — chatter can request a teammate.
- Optional **lead capture** — collect a name/email/phone before chatting.

Good for: marketing sites, support pages, in-app help.

## Bar

A search-style trigger that opens a full-screen chat modal — the kind of "ask anything" command bar your team expects from modern apps.

- Embedded with a single `<script>` tag, same as the Widget.
- Replaces the previous search-style mode; it is now a first-class surface configured per agent.
- Customizable placeholder, shortcut key, and modal appearance.

Good for: in-app command-bar assistants, internal tools, knowledge lookup from inside your product.

## Cross-surface behavior

The Setup page is split into shared and per-surface sections:

- **Shared across all three surfaces**: Info, Persona, Starters, Model, Branding. Edit once, applies everywhere.
- **Per-surface**: Behavior and Embed swap based on the active preview tab. Each surface gets its own behavior toggles and its own embed snippet.

This means a workspace can ship the same agent on a marketing site (Widget), inside its product (Bar), and as a public help portal (Hosted Chat) without re-authoring the agent three times.

## What every agent has

- A **name** and **chat address**.
- A **persona** (system instructions). See [Persona & instructions](/guide/agents/persona).
- A **model** assignment. See [Model & generation](/guide/agents/models).
- A **branding** configuration (colors, avatar, copy). See [Appearance & branding](/guide/agents/appearance).
- **Sources** — at least one is required before the agent can answer usefully. See [Sources overview](/guide/sources/overview).
- A **Playground** for testing without going through a public surface.
- **Chat logs**, **Leads**, **Analytics**.

## A note on the unified /chat hub

The unified `/chat` route is **not** a surface of any single agent. It is the chatter lobby — a ChatGPT-style hub scoped to all agents a chatter has been granted access to across the workspace. Chatters log in there to switch between every agent assigned to them.

Each agent's three surfaces (Hosted Chat, Widget, Bar) are about exposing **one** agent to the outside world. The `/chat` hub is about giving a chatter one place to talk to **all** their agents inside the workspace.
