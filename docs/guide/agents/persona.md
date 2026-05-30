# Persona & instructions

The **persona** is what tells the model how to behave: tone, scope, what to refuse, how to format answers. It's the single most impactful setting on an agent.

Open the agent and go to **Persona** (or, in a Widget agent, the **Persona** tab in the Widget editor).

![SCREENSHOT — persona editor](/screenshots/agent-persona.png)

## Two modes

Chatvia lets you author the persona in one of two ways. They're mutually exclusive — the agent uses whichever you set last.

### Custom instructions

Type the instructions directly into the textarea. Good for one-off agents with bespoke behavior.

```
You are the support assistant for Acme Inc.

Tone: warm, concise, slightly playful.
Always:
  - Refer to our product as "Acme Cloud" (never "AC").
  - Answer in the visitor's language.
  - Cite the source title when you use one.
Never:
  - Discuss pricing — direct people to sales@acme.com instead.
  - Promise SLAs or response times.

If you don't have enough information, say so and offer to escalate.
```

### Saved prompt

Reference a [saved prompt](/guide/account/prompts) from the dropdown. Good when you want one consistent persona across many agents, or when non-developers should edit the persona separately from the agent.

When a saved prompt is selected, the instructions box is hidden and the agent reads the prompt by reference. Editing the prompt in **Settings → Prompts** updates every agent that uses it.

## What goes in a good persona

A useful persona usually includes:

1. **Identity.** "You are the support assistant for {company}."
2. **Scope.** What is in/out of bounds.
3. **Tone.** Two or three adjectives go a long way.
4. **Formatting rules.** Headings? Bullet points? Code blocks?
5. **Escape hatches.** What to do when the answer isn't in the sources.
6. **Refusals.** Topics to redirect (pricing, legal, medical, etc.).

::: tip Keep it under ~800 tokens
Long personas eat into the context budget reserved for retrieved sources. Aim for a few hundred words. If you need lots of policy text, put it in a Q&A source instead.
:::

## Variables and placeholders

Chatvia doesn't interpolate the persona — it's passed verbatim. If you want per-visitor personalization (their name, their plan), use [identity verification](/guide/account/identity) to sign them in, and then expose the visitor object to the agent via your sign-in flow.

## Name and subdomain

The **Persona** page is also where you edit the agent's **Name** and **Subdomain**.

- **Name** is visible to visitors (chat header, browser tab title).
- **Subdomain** changes the hosted URL — old links will 404 once you change it, so update embeds and shared links.

Click **Save** to apply changes. There's a header indicator when you have unsaved edits.
