# Templates

A **template** is a reusable bundle of agent configuration: appearance, models, starters, persona, surface behavior. Attach a template to many agents across your workspace and they all share the same config without re-entering anything.

![SCREENSHOT — templates list](/screenshots/templates-list.png)

## When templates are worth it

Templates pay off when your workspace has:

- **Many agents that should look identical** — country-specific support agents, multi-brand deployments.
- **A central brand team** that owns the look, separate from product teams that own the knowledge.
- **A consistent voice** across agents — same persona, same starters, same fallback model.

For one or two agents, inline configuration is fine.

## What a template carries

Templates apply to **any agent** — there is no agent type. The same template covers all three surfaces (Hosted Chat, Widget, Bar).

| Group | Fields |
| --- | --- |
| **Appearance** | Theme color, color shade, avatar, logo, chat title, welcome message, input placeholder. |
| **Starters / bubbles** | The list of suggested prompts. |
| **Model** | Primary model + fallback + generation parameters. |
| **Surface settings** | Position, size, language, domain whitelist, show-on-load delay (applied where each surface uses them). |
| **Prompt reference** | Optional link to a saved [prompt](/guide/account/prompts) — the persona. |

Anything **not** in a template stays per-agent: subdomain, sources, functions, MCP connectors, leads form, members.

## Creating a template

In **Settings → Templates**, click **Create**:

![SCREENSHOT — template editor](/screenshots/template-editor.png)

The editor mirrors an agent's Setup page, organized by tabs. Save when you're done.

::: tip Start from an existing agent
The fastest way to make a template is to configure a "reference" agent the way your team wants all agents to look, then create the template by copying its settings. The dashboard surfaces a **Save as template** shortcut from any agent's **Setup** page.
:::

## Attaching to an agent

Open the agent's **Setup** page. At the top of the panel is the **Template** banner:

- **Pick a template** to attach. Templated fields become read-only and a "Linked to template" notice appears.
- **Detach** to go back to per-agent configuration. The template's current values are copied onto the agent so nothing changes visually — your team just regains edit access.

While a template is attached, the agent **reads its config from the template at runtime**. Editing the template updates the agent immediately across all three surfaces.

## What happens on edit

Edit a template field, save, and every attached agent picks up the change on the next message.

There are two visible effects:

1. **Live config** — chatters talking to attached agents see the new values immediately.
2. **No cache invalidation needed** — Chatvia reads through, so the change applies instantly.

## What gets locked vs. overridable

When a template is attached, **all template-controlled fields are locked** on the agent's Setup page. To change a single value just for one agent, detach the template — there's no "partial override" model. This is intentional: it keeps the mental model simple for your team.

## Templates vs. prompts

| | Prompt | Template |
| --- | --- | --- |
| **Scope** | Persona only | Full agent configuration |
| **Granularity** | One block of text | Many fields |
| **Reuses prompts?** | — | Yes — a template can reference a prompt |

A template that references a prompt is the most reusable configuration in Chatvia — change the prompt to update behavior across many agents through one template.
