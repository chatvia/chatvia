# Core concepts

A short tour of the objects you'll see across the dashboard and the API.

## Accounts and teams

When you sign up you get a **user account** plus a personal **team**. Teams own everything else: agents, sources, functions, MCP connectors. You can:

- Invite other users to your team as members.
- Define **roles** that grant access to specific agents and specific actions (read, write, delete).
- Create additional teams if you want to silo data.

The currently-active team is shown in the top nav switcher. The signing secret used for identity verification is **account-level** (one per user, used across all your teams).

## Agents

An **agent** is one chatbot. It has:

- A **name** and a **subdomain** (`agent-name.chatvia.ai`) — the hosted chat page.
- A **persona** — the system instructions the model follows.
- A **model** — which LLM(s) the agent is allowed to use, and generation parameters.
- An **appearance** — colors, avatar, text labels.
- A set of **sources** — its knowledge base.
- Optional **functions** and **MCP connectors** — tools it can call.
- A **type** — see [Agent types](/guide/agents/types).

## Sources

Anything your agent can answer from. Five kinds:

| Type | What goes in | Used for |
| --- | --- | --- |
| **Files** | PDF, Word, Markdown, audio, video | Long-form documents |
| **Web** | A URL (Chatvia crawls child pages) | Public sites, docs portals |
| **YouTube** | A video URL | Tutorials, talks — uses the transcript |
| **Text** | Pasted plain text | Short policies, FAQs |
| **Q&A** | Explicit question + answer | Direct overrides, canonical replies |

All sources are parsed into chunks, embedded into vectors, and indexed. Retrieval is semantic — visitors don't need to use the exact wording in the source.

## Conversations

Every chat session creates a **conversation**. Each conversation:

- Belongs to one **chat session** (a visitor identity — IP, geo, browser, optional name/email).
- Holds a stream of **messages** (`user`, `assistant`, `admin` for human-handoff replies).
- Can be tagged with a **topic**, **sentiment**, and **tone**.
- Has a **handoff status** — `ai` (default), `pending` (visitor requested a human), or `human` (a teammate took over).

You'll see them in **Chat logs**, with filters for date, topic, sentiment, country.

## Tokens and keys

Three distinct credentials, easy to mix up — keep them straight:

| Credential | Where it's made | Who holds it | What it does |
| --- | --- | --- | --- |
| **API key** | Settings → API keys | Your servers | Authenticates calls to the [public API](https://chatvia.ai/docs/api) |
| **Signing secret** | Settings → Identity verification | Your servers | Mints sign-in JWTs for protected agents |
| **Sign-in token** | Signed by you with the secret | Your end-users | Logs an end-user into one specific agent for up to 1 hour |

API keys and the signing secret never leave your backend. Sign-in tokens are short-lived and may be embedded in the browser (widget `data-auth`, chat `?token=`).

## Templates and prompts

Two reusable bundles at the account level:

- **Prompts** — a named system-instruction block. Attach one to multiple agents instead of pasting instructions everywhere.
- **Templates** — a full agent-configuration bundle (appearance, model, starters, prompt). Attach to an agent and it reads its config from the template at runtime.

Both live under **Settings**.
