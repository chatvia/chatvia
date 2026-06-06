# Creating an agent

Go to **Dashboard → Agents** and click **Create agent**.

![SCREENSHOT — agents index with the Create agent button](/screenshots/agents-index.png)

## The create dialog

You'll be asked for:

| Field | Notes |
| --- | --- |
| **Name** | Shown in the dashboard and across all three surfaces of the agent. |
| **Template** *(optional)* | Pre-fill all appearance/model/persona settings from a [template](/guide/account/templates). |

![SCREENSHOT — create agent modal](/screenshots/agent-create-modal.png)

::: tip Renaming and subdomain
A slug is auto-generated from the name — no need to pick one in the modal. Rename it later in **Setup → Domain**, or attach a custom domain via CNAME.
:::

## What happens after creation

You land on the agent's **Setup** page, with cards pointing to the sections your team will visit next:

- **Sources** — add files, web pages, YouTube, text, or Q&A so the agent has something to answer from.
- **Persona** — set the system instructions, tone, and behaviour.
- **Setup** — configure the three surfaces (Hosted Chat, Widget, Bar), appearance, model, and branding.
- **Playground** — try the agent out before sharing it with chatters.

Behind the scenes:

- An empty agent is created in the current workspace.
- A default model assignment is wired up (the first available model in the workspace plan).
- Default system instructions ("You are a helpful assistant…") are set — change these before sharing.
- No sources yet — the agent will refuse to answer until your team adds at least one.

## Permissions

**Workspace owners** and **teammates with the `Agent.create` permission** can create agents. Other teammates can be granted edit access on specific agents through [role-based access](/guide/account/teams).

## Creating agents via API

Agents can also be created programmatically. See the [API reference](https://chatvia.ai/docs/api) under **Agents** for the full request schema.

::: info
Every new agent serves all three surfaces (Hosted Chat, Widget, Bar) — see [Agent surfaces](/guide/agents/types).
:::
