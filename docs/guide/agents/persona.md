# Persona & instructions

The **persona** tells the model how to behave: tone, scope, what to refuse, how to format answers. It's the single most impactful setting on an agent, and it applies across all three surfaces — Hosted Chat, Widget, and Bar.

Open the agent and go to **Setup → Persona**.

![SCREENSHOT — persona editor](/screenshots/agent-persona.png)

## Two modes

Chatvia lets your team author the persona in one of two ways. They're mutually exclusive — the agent uses whichever was set last.

### Custom instructions

Type the instructions directly into the textarea. Good for a one-off agent with bespoke behavior.

```
You are the support assistant for Acme Inc.

Tone: warm, concise, slightly playful.
Always:
  - Refer to our product as "Acme Cloud" (never "AC").
  - Answer in the customer's language.
  - Cite the source title when you use one.
Never:
  - Discuss pricing — direct people to sales@acme.com instead.
  - Promise SLAs or response times.

If you don't have enough information, say so and offer to escalate.
```

### Saved prompt

Reference a [saved prompt](/guide/account/prompts) from the dropdown. Good when the workspace wants one consistent persona across many agents, or when non-developers should edit the persona separately from the agent.

When a saved prompt is selected, the instructions box is hidden and the agent reads the prompt by reference. Editing the prompt in **Settings → Prompts** updates every agent that uses it.

## What goes in a good persona

A useful persona usually includes:

1. **Identity.** "You are the support assistant for {your business}."
2. **Scope.** What is in/out of bounds.
3. **Tone.** Two or three adjectives go a long way — match your brand voice.
4. **Formatting rules.** Headings? Bullet points? Code blocks?
5. **Escape hatches.** What to do when the answer isn't in your sources.
6. **Refusals.** Topics to redirect (pricing, legal, medical, etc.).

::: tip Keep it under ~800 tokens
Long personas eat into the context budget reserved for retrieved sources. Aim for a few hundred words. If you need lots of policy text, put it in a Q&A source instead.
:::

## Ground the agent in your business

The persona is where your business voice lives. Pair it with sources that come from your own materials — product docs, help center articles, internal Q&A — so the agent answers in your tone and from your facts, not from generic web knowledge.

## Variables and placeholders

Chatvia doesn't interpolate the persona — it's passed verbatim. If you want per-chatter personalization (their name, their plan), use [identity verification](/guide/account/identity) to sign chatters in, and then expose the chatter object to the agent via your sign-in flow.

## Name and chat address

The **Persona** page is also where teammates edit the agent's **Name** and **Chat address**.

- **Name** is visible to chatters (chat header, browser tab title) on every surface.
- **Chat address** is the last part of the Hosted Chat URL (`/chat/{address}`). Old links stop working when it changes, so update shared links. Embed snippets use the agent ID and keep working.

Click **Save** to apply changes. There's a header indicator when there are unsaved edits.
