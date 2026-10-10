# Core concepts

A short tour of the objects your team will see across the dashboard and the API.

## Workspaces, teammates, chatters

Chatvia is built around three roles. Keep them straight and the rest of the product makes sense.

- **Workspace** — the company account. One per customer. The workspace owns secrets, agents, sources, functions, and connectors. Every workspace can use every feature. A workspace can have a [monthly message limit](/guide/account/message-limits).
- **Teammates** — the people inside the workspace who *manage* agents. They sign in to the dashboard. Each teammate has a role that grants access to specific agents and specific actions (read, write, delete). Invite them from **Settings → Members**.
- **Chatters** — the end-users who *talk* to agents. They sign in at **/chat** and only see the agents they have been granted access to. Chatters never see the dashboard. Invite them workspace-wide from **Settings → Members**, or per-agent from each agent's **Members** tab.

The signing secret used for identity verification is **workspace-level** (one per workspace, used across every agent the workspace owns).

## Agents

An **agent** is one chatbot. There is no agent "type" — every agent can do every job. It has:

- A **name**.
- A **persona** — the system instructions the model follows.
- A **model** — which LLM(s) the agent is allowed to use, and generation parameters.
- An **appearance** — colors, avatar, text labels.
- A set of **sources** — its knowledge.
- Optional **tools** — workspace **functions** and **MCP connectors** the agent is allowed to call.
- Three **surfaces** — the ways chatters reach it.

## Surfaces

Every agent is exposed through three surfaces at once. A single unified **Setup** page configures appearance, persona, starters, model, and branding once — those choices apply across all three surfaces. Behavior and Embed settings swap per active surface tab.

- **Hosted Chat** — the standalone chat at `{agent}.chatvia.ai` (or a custom domain via CNAME). Looks like the unified chat, but locked to one agent. Useful for embed, iframe, or SSO scenarios.
- **Widget** — an embeddable floating chat bubble. One `<script>` tag drops it onto any host page.
- **Bar** — a search-style trigger that opens a full-screen chat modal. A first-class surface, configured per agent.

The unified **/chat** hub is **not** one of an agent's surfaces. It is the chatter lobby — a ChatGPT-style interface where a chatter switches between every agent they have been granted access to.

## Sources

Anything an agent can answer from. Sources live **per agent** — there is no workspace-wide knowledge base page. Five kinds:

| Type | What goes in | Used for |
| --- | --- | --- |
| **Files** | PDF, Word, Markdown, audio, video | Long-form documents |
| **Web** | A URL (Chatvia crawls child pages) | Public sites, docs portals |
| **YouTube** | A video URL | Tutorials, talks — uses the transcript |
| **Text** | Pasted plain text | Short policies, FAQs |
| **Q&A** | Explicit question + answer | Direct overrides, canonical replies |

All sources are parsed into chunks, embedded into vectors, and indexed. Retrieval is semantic — chatters don't need to use the exact wording in the source.

## Tools (Functions and Connectors)

Tools are first-class in Chatvia. The workspace owns the catalog; each agent picks which tools it is allowed to call.

- **Functions** — webhook-backed custom tools. The workspace defines them once in **Settings → Functions**, then attaches them per agent.
- **MCP Connectors** — branded one-click integrations (GitHub, Slack, Jira, Linear, Notion, Stripe, Atlassian, ...) plus custom MCP servers added by URL. Authored at workspace level in **Settings → Connectors**, attached per agent.

Each tool on an agent has a tri-state permission: **allow**, **require approval**, or **blocked**. Approval-required tools pause the turn at a checkpoint until a teammate or chatter resolves it.

## Conversations

Every chat thread creates a **conversation** between a chatter and an agent. Each conversation:

- Belongs to one **chatter**.
- Holds a stream of **messages** (`user`, `assistant`, `admin` for human-handoff replies).
- Can be tagged with a **topic**, **sentiment**, and **tone**.
- Has a **handoff status** — `ai` (default), `pending` (the chatter requested a human), or `human` (a teammate took over).

Teammates browse them in **Chat logs**, with filters for date, topic, sentiment, country.

## Tokens and keys

Three distinct credentials the workspace can issue, easy to mix up — keep them straight:

| Credential | Where it's made | Who holds it | What it does |
| --- | --- | --- | --- |
| **API key** | Settings → API keys | Your servers | Authenticates calls to the [public API](https://chatvia.ai/docs/api) |
| **Signing secret** | Settings → Identity verification | Your servers | Mints sign-in JWTs for protected agents |
| **Sign-in token** | Signed by your server with the secret | Your end-users (chatters) | Logs a chatter into one specific agent for up to 1 hour |

API keys and the signing secret never leave your backend. Sign-in tokens are short-lived and may be embedded in the browser (widget `data-auth`, chat `?token=`).

## Templates and prompts

Two reusable bundles at the workspace level:

- **Prompts** — a named system-instruction block. Attach one to multiple agents instead of pasting instructions everywhere.
- **Templates** — a full agent-configuration bundle (appearance, model, starters, prompt). Attach it to an agent and the agent reads its config from the template at runtime.

Both live under **Settings**.
