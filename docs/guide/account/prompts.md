# Prompts library

A **prompt** is a saved block of system instructions your workspace can attach to one or many agents. The library lives in **Settings → Prompts** and gives your business one source of truth for voice and behavior across every agent.

![SCREENSHOT — prompts list](/screenshots/prompts-list.png)

## Why use prompts instead of inline instructions

Inline instructions (on the agent's Persona page) are great for one-off agents. Saved prompts shine when:

- Your workspace runs **multiple agents** with similar behavior (different markets, different products, same business voice).
- A **non-developer teammate** owns the persona and shouldn't have to open each agent to change wording.
- Your team wants to **version control** persona changes outside of individual agent settings.

Update the prompt once, and every agent that references it picks up the change immediately — across every surface (Hosted Chat, Widget, Bar) and inside the unified `/chat` for your chatters.

## Creating a prompt

In **Settings → Prompts**, click **Create**:

- **Name** — internal label.
- **Body** — the system instructions.

The body has no template syntax — it's passed verbatim to the model. Write it the same way you'd write inline persona instructions ([Persona guide](/guide/agents/persona)).

## Attaching to agents

From an agent's **Persona** page, switch the mode toggle from **Custom** to **Saved** and pick a prompt. The instructions textbox is hidden — the agent reads from the prompt by reference.

You can also attach via the API (`agent_prompt_id` field) or via a [template](/guide/account/templates).

## What happens when you edit

Editing the prompt body updates **all attached agents at the next message** — there's no caching to invalidate. Chatters mid-conversation will see the new behavior on their next turn (existing messages stay grounded in whatever the model said with the old prompt).

::: tip Test before broadcasting
A prompt change can unintentionally shift behavior across dozens of agents your team owns. Test in the [Playground](/guide/agents/playground) of one or two agents before saving.
:::

## Deleting a prompt

You can't delete a prompt that's still attached to at least one agent — Chatvia would have no instructions for that agent. Detach (or switch agents to inline mode) first.

## Prompts and templates

A [template](/guide/account/templates) can reference a saved prompt via its own `agent_prompt_id`. Attaching the template to an agent then implies the prompt, too. Use this for fully reusable agent configurations across your workspace.
