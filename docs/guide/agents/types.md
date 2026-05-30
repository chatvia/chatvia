# Agent types

Chatvia ships two agent types. Pick the one that matches how your visitors will reach the chatbot.

![SCREENSHOT — agents index showing the two types](/screenshots/agents-index.png)

## Knowledge base

A **hosted chat page**, ChatGPT-style: a sidebar with conversation history, a main thread, and an input box at the bottom.

- Lives at `your-agent.chatvia.ai` (or a subdomain you pick).
- Multiple conversations per visitor.
- Conversation starters appear on the empty state.
- Optionally protected — only signed-in users can access it.

Best for: internal knowledge bases, customer self-service portals, "talk to our docs" pages.

## Widget

An **embeddable chat bubble**: a small button on a host page that opens a panel with the conversation.

- Embedded with a single `<script>` tag.
- Customizable color, position, language, icon, size.
- Domain whitelist — limit where the widget is allowed to run.
- Optional **handoff** — visitor can request a human.
- Optional **lead capture** — collect a name/email/phone before chatting.

Best for: marketing sites, support pages, in-app help.

::: tip API-created agents
The public API can only create knowledge-base agents (`type: "knowledge_base"`). Widget agents are dashboard-only because they require interactive appearance/behavior configuration.
:::

## Switching types

You can't change an agent's type after creation — the data model, default settings, and exposed surface differ enough that it's safer to create a new agent and migrate sources over. You **can** attach the same [template](/guide/account/templates) to a knowledge-base agent and a widget agent if you want consistent branding.

## What every agent has

Regardless of type, every agent has:

- A **name** and **subdomain**.
- A **persona** (system instructions). See [Persona & instructions](/guide/agents/persona).
- A **model** assignment. See [Model & generation](/guide/agents/models).
- An **appearance** (colors, avatar, copy). See [Appearance & branding](/guide/agents/appearance).
- **Sources** — at least one is required before the agent can answer usefully. See [Sources overview](/guide/sources/overview).
- A **Playground** for testing without going through the visitor surface.
- **Chat logs**, **Leads**, **Analytics**.

## What only widgets have

- An **embed snippet** with `data-agent-id` / `data-auth` / `data-language` attributes.
- A **live preview** panel beside every setting so you see changes immediately.
- **Behavior** controls: open delay, show-on-load, theme toggle, sound.
- **Sites** (domain whitelist).
- **Bubbles** — the chip-shaped suggestion prompts visible on the empty state.
