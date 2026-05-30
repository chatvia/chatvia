# Conversation starters

Starters are the suggested prompts visitors see on the empty state of a fresh conversation. They're one of the highest-leverage things you can configure: most visitors will click a starter instead of typing.

![SCREENSHOT — starters editor](/screenshots/agent-starters.png)

## Where to configure them

- **Widget agents** — Widget editor → **Starters** tab.
- **Knowledge-base agents** — Agent header → **Starters** menu.

Each starter is one short prompt (recommend < 60 characters). You can have up to a handful — past ~5 the UI gets cluttered.

## Writing good starters

The starters that work best:

- Sound like questions a real visitor would ask.
- Match topics your sources actually cover.
- Lead the visitor toward where you can help, away from where you can't.

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

Starters can live on a [template](/guide/account/templates) — useful when you have many agents that should share the same opening prompts (e.g. all your country-specific support bots).

When a template is attached, the starters list is locked and reads from the template.

## Reordering and editing

Drag a starter to reorder. Click it to edit. Click the **×** to delete. Changes save when you click **Save** at the top of the page.

## Bubbles vs. starters

**Bubbles** (widget agents) and **starters** look similar but behave differently:

- **Bubbles** — chip-shaped prompts inside the widget panel that *send a message* when clicked.
- **Starters** — the same idea, but a higher-impact variant used on the chat page's empty state.

In practice you set the same content in both for consistency.
