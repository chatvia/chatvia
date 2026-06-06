# Conversation starters

Starters are the suggested prompts chatters see on the empty state of a fresh conversation. They're one of the highest-leverage things your team can configure: most chatters will click a starter instead of typing.

![SCREENSHOT — starters editor](/screenshots/agent-starters.png)

Starters are configured once per agent and apply to all three surfaces — Hosted Chat, Widget, and Bar.

## Where to configure them

Open the agent in the dashboard and go to **Setup → Starters**. The list applies everywhere the agent is reachable.

Each starter is one short prompt (recommend < 60 characters). You can have up to a handful — past ~5 the UI gets cluttered.

## Writing good starters

The starters that work best:

- Sound like questions a real customer would ask.
- Match topics your sources actually cover.
- Lead the chatter toward where the agent can help, away from where it can't.

Examples for a SaaS support bot:

```
How do I reset my password?
Where do I find my invoices?
What's included in the Pro plan?
Can I connect Chatvia to Slack?
```

Examples for a docs-site bot:

```
How do I install the SDK?
What headers does the API expect?
Show me a curl example
What's the rate limit?
```

## Templates and starters

Starters can live on a [template](/guide/account/templates) — useful when your workspace runs many agents that should share the same opening prompts (e.g. all your country-specific support bots).

When a template is attached, the starters list is locked and reads from the template.

## Reordering and editing

Drag a starter to reorder. Click it to edit. Click the **×** to delete. Changes save when you click **Save** at the top of the page.

## Bubbles vs. starters

**Bubbles** and **starters** look similar but behave differently:

- **Bubbles** — chip-shaped prompts inside the Widget panel that *send a message* when clicked.
- **Starters** — the same idea, but a higher-impact variant used on the chat empty state across all surfaces.

In practice your team sets the same content in both for consistency.
