# Appearance & branding

Match every chat surface to your brand. Appearance is configured once in the agent's unified **Setup** page, in two tabs: **Text** and **Colors**. The same values apply to all three surfaces at once — Hosted Chat, Widget, and Bar.

![SCREENSHOT — appearance settings](/screenshots/agent-appearance.png)

## Text

Editable copy that your customers see:

| Field | Where it appears |
| --- | --- |
| **Chat title** | Top of the chat window — usually your product name. |
| **Welcome message** | First message the agent says when the chat opens. |
| **Input placeholder** | Greyed text in the input box ("Ask anything…"). |
| **Bubbles** | Chip-style suggestion prompts on the empty state. Press Enter to add each one. |

These are localized: a multilingual chatter sees the language your team sets in **Behavior**.

## Colors

Two pieces:

- **Theme color** — the primary hue used for the bubble button, message highlights, send button.
- **Shade** — a light/dark variant of the theme color.

The picker shows a curated palette of safe colors. If your brand needs an exact hex, switch the picker to **Custom**.

::: warning Use full color functions in CSS variables
If your team overrides the theme via custom CSS, store full color functions (e.g. `hsl(220 90% 56%)`) in your variables and reference them as `var(--name)`. Don't wrap with `hsl(var(--name))` — that's invalid CSS and silently dropped.
:::

## Avatar and logo

Two image uploads:

- **Agent avatar** — shown beside agent messages.
- **Logo** — shown in the chat header.

Both accept PNG, JPG, WebP, SVG, up to 2 MB. Square images at 256×256 work best for the avatar.

## Templates

If your workspace has created a [template](/guide/account/templates), attaching it from the banner at the top of the page locks appearance fields and reads them from the template. While attached:

- A "Linked to template" banner appears.
- Editing template-controlled fields is disabled.
- Detaching copies the current template config onto the agent so nothing changes visually.

This is how your team brands many agents consistently without re-entering colors and copy.

## Live preview

The Setup editor includes a live preview panel on the right. As your team edits Text or Colors, the preview re-renders in real time so you can see exactly what chatters will see. The preview is iframed and represents the production rendering — not a separate mock. Because appearance is shared, the same values flow into the Hosted Chat, the embeddable Widget, and the Bar surface without any extra configuration.
