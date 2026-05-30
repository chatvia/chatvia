# Appearance & branding

Match the chat surface to your brand. Settings live on the agent's **Widget** page (for widget agents) or **Embed** page (for full-page embeds), in two tabs: **Text** and **Colors**.

![SCREENSHOT — appearance settings](/screenshots/agent-appearance.png)

## Text

Editable copy that visitors see:

| Field | Where it appears |
| --- | --- |
| **Chat title** | Top of the chat window — usually your product name. |
| **Welcome message** | First message the assistant says when the chat opens. |
| **Input placeholder** | Greyed text in the input box ("Ask anything…"). |
| **Bubbles** | Chip-style suggestion prompts on the empty state. Press Enter to add each one. |

These are localized: a multilingual visitor sees the language you set in **Behavior**.

## Colors

Two pieces:

- **Theme color** — the primary hue used for the bubble button, message highlights, send button.
- **Shade** — a light/dark variant of the theme color.

The picker shows a curated palette of safe colors. If you need an exact brand hex, switch the picker to **Custom**.

::: warning Use full color functions in CSS variables
If you ever override the theme via your own CSS, store full color functions (e.g. `hsl(220 90% 56%)`) in your variables and reference them as `var(--name)`. Don't wrap with `hsl(var(--name))` — that's invalid CSS and silently dropped.
:::

## Avatar and logo

Two image uploads:

- **Agent avatar** — shown beside assistant messages.
- **Logo** — shown in the chat header.

Both accept PNG, JPG, WebP, SVG, up to 2 MB. Square images at 256×256 work best for the avatar.

## Templates

If you've created a [template](/guide/account/templates), attaching it from the banner at the top of the page locks appearance fields and reads them from the template. While attached:

- A "Linked to template" banner appears.
- Editing template-controlled fields is disabled.
- Detaching copies the current template config onto the agent so nothing changes visually.

This is how you brand many agents consistently without re-entering colors and copy.

## Live preview

Widget and Embed editors include a live preview panel on the right. As you edit Text or Colors, the preview re-renders in real time so you can see exactly what visitors will see. The preview is iframed and represents the production widget — not a separate mock.
