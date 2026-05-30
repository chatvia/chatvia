# Full-page embed

A full-page embed is the **ChatGPT-style** chat experience hosted inside your own app — sidebar with conversation history, main message thread, input box at the bottom. Useful when chat is the whole feature, not a sidekick.

![SCREENSHOT — full-page embed inside a host app](/screenshots/full-page-embed.png)

## When to use this vs. the widget

| | Widget | Full-page |
| --- | --- | --- |
| **Surface** | Floating bubble on existing pages | Dedicated page in your app |
| **Conversation persistence** | localStorage, per-domain | Server-side, per-user |
| **History view** | Inside the bubble | Sidebar with all conversations |
| **Use cases** | Support, sales, marketing widgets | Internal knowledge assistants, in-app help, AI-first products |

If chat is a *feature* of your product (not just a help overlay), use full-page embed.

## Two delivery options

### Option 1 — iframe the hosted page

Every agent has a hosted chat URL: `https://YOUR_AGENT.chatvia.ai`. Iframe it:

```html
<iframe
  src="https://YOUR_AGENT.chatvia.ai"
  style="width:100%; height:100vh; border:0;"
  title="Chatvia chat"></iframe>
```

For protected agents, append `?token=THE_TOKEN_YOU_SIGNED`. See [Protected agents](/embed/protected).

Pros: zero setup, works in every framework.
Cons: less flexible (Chatvia controls the chrome).

### Option 2 — the embed.js script in fullpage mode

Drop the same script tag you'd use for the widget, but configure the agent's **type** as `search` and the embed flips to a centered modal mode:

```html
<script
  id="chatbot-embed-script"
  src="https://chatvia.ai/embed.js"
  data-agent-id="YOUR_AGENT_ID"
  data-type="search"
  data-language="auto"
  defer></script>
```

This puts a search-style trigger button on your page; clicking it opens the chat as a full-screen modal. Useful when chat is opt-in but you want it to feel native.

## Configuring the experience

From the agent's **Embed** page, configure (similar to Widget):

- **Text** — chat title, welcome message, input placeholder, starters.
- **Persona** — system instructions or saved prompt.
- **Model** — primary and fallback.
- **Colors** — theme color, shade.
- **Avatar / logo.**
- **Behavior** — open delay, sound, theme.
- **Security** — domain whitelist, protected access.

A live preview is shown alongside the editor.

## Authenticated full-page embeds

For an internal-tool use case (employees only, customers in a logged-in app), turn the agent **protected** and sign visitors in:

- Redirect them to `https://YOUR_AGENT.chatvia.ai/?token=<JWT>` from your own session-checking route.
- Or pass `data-auth="<JWT>"` to the embed script.

Chatvia validates the JWT and serves the chat with the visitor's identity. See [Protected agents](/embed/protected) and [Identity verification](/guide/account/identity).

## SSO / SAML

If you've integrated SSO with your app, mint a Chatvia JWT immediately after the SSO callback completes — it's just another short-lived token. Chatvia doesn't need to know about SAML or OIDC; it trusts your signature.

## Custom domain (CNAME)

By default the hosted chat lives on `*.chatvia.ai`. Enterprise plans support hosting on your own domain via CNAME (e.g. `chat.yourcompany.com → YOUR_AGENT.chatvia.ai`). Contact support to set up the certificate.

This is purely cosmetic — the agent and all its settings stay the same.
