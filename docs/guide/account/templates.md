# Templates

A **template** is a reusable bundle of agent configuration: appearance, models, starters, persona, widget behavior. Attach a template to many agents and they all share the same config without re-entering anything.

![SCREENSHOT — templates list](/screenshots/templates-list.png)

## When templates are worth it

You'll feel the pain templates solve when you have:

- **Many agents that should look identical** — country-specific support bots, multi-brand widgets.
- **A central brand team** that owns the look, separate from product teams that own the knowledge.
- **A consistent voice** across agents — same persona, same starters, same fallback model.

For one or two agents, inline configuration is fine.

## What a template carries

| Group | Fields |
| --- | --- |
| **Type** | Knowledge base or Widget (templates can only be applied to matching types). |
| **Appearance** | Theme color, color shade, avatar, logo, chat title, welcome message, input placeholder. |
| **Starters / bubbles** | The list of suggested prompts. |
| **Model** | Primary model + fallback + generation parameters. |
| **Widget settings** | Position, size, language, domain whitelist, show-on-load delay (Widget templates only). |
| **Prompt reference** | Optional link to a saved [prompt](/guide/account/prompts) — the persona. |

Anything **not** in a template stays per-agent: subdomain, sources, functions, MCP connectors, leads form.

## Creating a template

In **Settings → Templates**, click **Create**:

![SCREENSHOT — template editor](/screenshots/template-editor.png)

The editor mirrors an agent's settings, organized by tabs. Save when you're done.

::: tip Start from an existing agent
The fastest way to make a template is to configure a "reference" agent the way you want all your agents to look, then create the template by copying its settings. The dashboard surfaces a "Save as template" shortcut from any agent's Widget editor.
:::

## Attaching to an agent

Open the agent's **Widget** (or **Embed**) page. At the top of the panel is the **Template** banner:

- **Pick a template** to attach. Templated fields become read-only and a "Linked to template" notice appears.
- **Detach** to go back to per-agent configuration. The template's current values are copied onto the agent so nothing changes visually — you just regain edit access.

While a template is attached, the agent **reads its config from the template at runtime**. Editing the template updates the agent immediately.

## What happens on edit

Edit a template field, save, and every attached agent picks up the change on the next message.

There are two visible effects:

1. **Live config** — visitors of attached agents see the new values immediately.
2. **No cache invalidation needed** — Chatvia reads through, so the change applies instantly.

## What gets locked vs. overridable

When a template is attached, **all template-controlled fields are locked** on the agent's editor. To change a single value just for one agent, detach the template — there's no "partial override" model. This is intentional: it keeps the mental model simple.

## Templates vs. prompts

| | Prompt | Template |
| --- | --- | --- |
| **Scope** | Persona only | Full agent configuration |
| **Granularity** | One block of text | Many fields |
| **Reuses prompts?** | — | Yes — a template can reference a prompt |

A template that references a prompt is the most reusable configuration in Chatvia — change the prompt to update behavior across many agents through one template.
