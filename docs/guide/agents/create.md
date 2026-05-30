# Creating an agent

Go to **Dashboard → Agents** and click **Create agent**.

![SCREENSHOT — agents index with the Create agent button](/screenshots/agents-index.png)

## The create dialog

You'll be asked for:

| Field | Notes |
| --- | --- |
| **Name** | Shown in the dashboard and (for knowledge-base agents) at the top of the chat page. |
| **Type** | [Knowledge base or Widget](/guide/agents/types). Not changeable later. |
| **Subdomain** | The hosted URL — `your-name.chatvia.ai`. A random name is generated; the **Regenerate** button rolls a new one. Must be unique across Chatvia. |
| **Template** *(optional)* | Pre-fill all appearance/model/persona settings from a [template](/guide/account/templates). |

![SCREENSHOT — create agent modal](/screenshots/agent-create-modal.png)

::: tip Subdomain rules
Subdomains are lowercase, alphanumeric with hyphens, 3–63 characters, and must be unique. The "Regenerate" button always proposes a free name.
:::

## What happens after creation

You land on the agent's **Home** page, with:

- A short tour banner (dismissible).
- Cards for the sections you'll visit next: **Sources**, **Persona**, **Widget** or **Settings**, **Playground**.
- A copy-link button for the hosted URL (knowledge-base only).

Behind the scenes:

- An empty agent is created on your current team.
- A default model assignment is wired up (the first available model in your plan).
- Default system instructions ("You are a helpful assistant…") are set — change these before sharing.
- No sources yet — the agent will refuse to answer until you add at least one.

## Permissions

Only **team owners** can create agents. Members can be granted **edit** access on specific agents through [roles](/guide/account/teams).

## Creating agents via API

You can also create agents programmatically. See the [API reference](https://chatvia.ai/docs/api) under **Agents** for the full request schema. Note that the API can only create knowledge-base agents — widget agents must be created in the dashboard.
