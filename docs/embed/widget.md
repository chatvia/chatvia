# Widget (chat bubble)

The Chatvia widget is a floating chat bubble you can embed on any website with one `<script>` tag. It opens a chat panel that talks to your agent in real time.

![SCREENSHOT — widget bubble closed](/screenshots/widget-bubble.png)
![SCREENSHOT — widget open](/screenshots/widget-open.png)

## The embed snippet

In the dashboard, open a Widget agent and go to **Widget → Embed**. Copy the snippet:

```html
<script
  id="chatbot-embed-script"
  src="https://chatvia.ai/embed.js"
  data-agent-id="YOUR_AGENT_ID"
  data-language="auto"
  defer></script>
```

![SCREENSHOT — embed snippet](/screenshots/embed-snippet.png)

Paste it just before `</body>`. That's all — the widget loads asynchronously, doesn't block your page, and brings its own styles (isolated, won't clash with your CSS).

## Data attributes

| Attribute | Required | Notes |
| --- | --- | --- |
| `id` | yes | Must be `chatbot-embed-script` — used internally to find the tag. |
| `src` | yes | The Chatvia embed loader URL. |
| `data-agent-id` | yes | The agent ID. You'll find it in the dashboard URL and on the agent's **Embed** page. |
| `data-language` | no | Forces a language. `auto` (default) detects from the visitor's browser. ISO-639-1 codes for explicit (`en`, `de`, `fr`, …). |
| `data-auth` | conditionally | Required for [protected agents](/embed/protected). A JWT signed with your account's signing secret. |
| `data-type` | no | `search` switches to ChatGPT-style search mode (no chat bubble; full-page on click). |
| `defer` | recommended | Loads after parsing — faster page render. |

## What you get out of the box

- **Floating button** — bottom-right by default, position configurable.
- **Open / close animations.**
- **Conversation persistence** — visitors can reload the page and pick up where they left off (stored in `localStorage`).
- **Auto language detection.**
- **Markdown rendering** — assistant messages support bold, lists, code blocks.
- **Mobile responsive.**
- **A11y** — keyboard navigable, ARIA-labeled.

## Customizing appearance and behavior

Everything visible to the visitor — colors, position, size, text labels, starters — is configured in the dashboard:

- **Widget → Text** — copy.
- **Widget → Persona / Starters / Model** — the agent's behavior.
- **Widget → Colors** — branding.
- **Widget → Behavior** — open delay, sound, persistence, theme.
- **Widget → Security** — domain whitelist, protected access.

Changes apply on the next page load — there's no rebuild step.

## Domain whitelist

In **Widget → Security**, list the domains where this widget is allowed to run. Requests from any other origin are rejected. Use this to prevent someone copying your snippet and running it on their own site.

Patterns supported:

- `example.com` — exact match.
- `*.example.com` — any subdomain.
- `localhost` — for development.

Leave empty to allow everywhere (not recommended for production).

## Languages

The widget supports automatic and forced languages. When set to `auto`, it inspects `navigator.language` and matches against Chatvia's supported list. If your visitors are predominantly one language, **force it** with `data-language` — it skips the detection step and ensures the welcome message is right.

The list of supported languages depends on the model (frontier models cover ~50 languages; smaller models cover ~10). Check the agent's **Model** tab for compatibility.

## Programmatic control

The embed script exposes a global `window.Chatvia` once loaded:

```js
// Open the widget
window.Chatvia.open()

// Close it
window.Chatvia.close()

// Toggle
window.Chatvia.toggle()

// Send a message programmatically
window.Chatvia.sendMessage('How do I reset my password?')

// Listen for events
window.Chatvia.on('open', () => console.log('Widget opened'))
window.Chatvia.on('message', (msg) => console.log('New message', msg))
```

Useful for:

- Triggering the widget from a "Need help?" button elsewhere on the page.
- Pre-filling a question from a deep link.
- Closing the widget after a conversion event.

## Performance

The loader is < 5 KB. It pulls the main bundle (~80 KB gzipped) on first interaction — the bubble appears immediately, the chat panel hydrates lazily when the visitor clicks it.

No layout shift: the bubble is `position: fixed` and doesn't affect your page's flow.

## Common issues

- **"Widget doesn't appear."** Check that `data-agent-id` matches the agent's external ID exactly, and that the page domain is in the agent's whitelist (or the whitelist is empty).
- **"Wrong language."** Set `data-language` explicitly to override browser detection.
- **"Auth required" overlay.** The agent is protected. Mint a JWT and pass it as `data-auth`. See [Protected agents](/embed/protected).
- **Styles leaking in.** Chatvia's widget renders inside a shadow DOM — your page styles can't leak in or out. If you see drift, it's almost always a browser extension; reproduce in incognito.
