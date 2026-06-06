# Full-page embed

A full-page embed is the **ChatGPT-style** chat experience hosted inside your business app — sidebar with conversation history, main message thread, input box at the bottom. Useful when chat is the whole feature for your team or customers, not a sidekick.

![SCREENSHOT — full-page embed inside a host app](/screenshots/full-page-embed.png)

## When to use this vs. the widget

| | Widget | Full-page |
| --- | --- | --- |
| **Surface** | Floating bubble on existing pages | Dedicated page in your app |
| **Conversation persistence** | localStorage, per-domain | Server-side, per chatter |
| **History view** | Inside the bubble | Sidebar with all conversations |
| **Use cases** | Support, sales, marketing widgets | Internal knowledge assistants, in-app help, AI-first products |

If chat is a *feature* of your product (not just a help overlay), use a full-page embed.

## Two delivery options

### Option 1 — iframe the Hosted Chat surface

Every agent has a Hosted Chat URL: `https://YOUR_AGENT.chatvia.ai`. It is one of the three surfaces of the agent (Hosted Chat, Widget, Bar). Iframe it into your app:

```html
<iframe
  src="https://YOUR_AGENT.chatvia.ai"
  style="width:100%; height:100vh; border:0;"
  title="Chatvia chat"></iframe>
```

For protected agents, append `?token=THE_TOKEN_YOU_SIGNED`. See [Protected agents](/embed/protected).

Pros: zero setup, works in every framework.
Cons: less flexible (Chatvia controls the chrome).

### Option 2 — the Bar surface

The **Bar** is a first-class surface configured per agent. It drops a search-style trigger button on your page; clicking it opens the agent's chat as a full-screen modal. Useful when chat is opt-in for your team or customers but you want it to feel native.

Drop the script tag on the host page:

```html
<script
  id="chatbot-embed-script"
  src="https://chatvia.ai/embed.js"
  data-agent-id="YOUR_AGENT_ID"
  data-language="auto"
  defer></script>
```

Configure the trigger, placeholder, and modal behavior under **Setup → (preview: Bar) → Embed**.

## Configuring the experience

Appearance, persona, starters, model, and branding are configured ONCE in the agent's unified **Setup** page — those settings apply across all three surfaces (Hosted Chat, Widget, Bar). Behavior and Embed settings are per-surface; switch the surface tab in Setup to edit them:

- **Text** — chat title, welcome message, input placeholder, starters.
- **Persona** — system instructions or saved prompt.
- **Model** — primary and fallback.
- **Colors** — theme color, shade.
- **Avatar / logo.**
- **Behavior** — open delay, sound, theme.
- **Security** — domain whitelist, protected access.

A live preview is shown alongside the editor.

## Authenticated full-page embeds

For internal-tool use cases (teammates only, customers inside a logged-in app), turn the agent **protected** and sign chatters in:

- Redirect them to `https://YOUR_AGENT.chatvia.ai/?token=<JWT>` from your own session-checking route.
- Or pass `data-auth="<JWT>"` to the embed script.

Chatvia validates the JWT and serves the chat with the chatter's identity. See [Protected agents](/embed/protected) and [Identity verification](/guide/account/identity).

## SSO / SAML

If your workspace has integrated SSO with your app, mint a Chatvia JWT immediately after the SSO callback completes — it's just another short-lived token (max 1h). Chatvia doesn't need to know about SAML or OIDC; it trusts your workspace's signature.

## Custom domain (CNAME)

By default the Hosted Chat surface lives on `*.chatvia.ai`. Enterprise plans support hosting it on your own domain via CNAME (e.g. `chat.yourcompany.com → YOUR_AGENT.chatvia.ai`). Contact support to set up the certificate.

This is purely cosmetic — the agent and all its settings stay the same.
