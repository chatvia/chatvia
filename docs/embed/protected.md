# Protected agents

A **protected agent** only talks to authenticated chatters. Your backend mints a short-lived JWT signed with the workspace signing secret; Chatvia trusts the signature.

If your team hasn't read [Identity verification](/guide/account/identity) yet, start there — this page is the embed-specific recipe.

## Marking an agent as protected

Open the agent's **Setup → Settings** and toggle **Public access** off. The flag belongs to the agent, so it applies to all three surfaces at once — Hosted Chat, Widget, and Bar.

A protected agent without a token shows:

- **Hosted Chat** — a sign-in-required page.
- **Widget** — the panel is hidden; the bubble shows a lock icon.
- **Bar** — the trigger is inert; clicking it does nothing until a valid token is attached.

## Mint the token on your backend

```js
import jwt from 'jsonwebtoken'

const SIGNING_SECRET = process.env.CHATVIA_SIGNING_SECRET

export function signChatviaToken(userId, agentId, ttlSeconds = 3600) {
  return jwt.sign(
    { sub: userId, aud: agentId },
    SIGNING_SECRET,
    { algorithm: 'HS256', expiresIn: ttlSeconds },
  )
}
```

- `sub` — the chatter's ID in your system (any opaque string).
- `aud` — the agent ID (same as `data-agent-id`).
- `exp` — Unix expiry. Max 1 hour from now.

## Hand the token to the embed

### Widget — `data-auth`

Render the token into the script tag from your team's server-side template:

```html
<script
  id="chatbot-embed-script"
  src="https://chatvia.ai/embed.js"
  data-agent-id="YOUR_AGENT_ID"
  data-auth="<%= chatviaToken %>"
  defer></script>
```

Or attach the token before the script loads from JS:

```html
<script>
  window.__chatviaToken = '<%= chatviaToken %>'
</script>
<script
  id="chatbot-embed-script"
  src="https://chatvia.ai/embed.js"
  data-agent-id="YOUR_AGENT_ID"
  data-auth-from="window.__chatviaToken"
  defer></script>
```

On a session refresh, mint a new token and call `window.Chatvia.setAuth(newToken)` to swap it in without reloading the page.

### Hosted Chat — `?token=`

Redirect signed-in chatters from your app:

```js
res.redirect(`https://your-agent.chatvia.ai/?token=${chatviaToken}`)
```

Chatvia validates the token, sets a session cookie, and strips `?token=` from the URL. From then on the chatter can browse the chat as long as the session cookie is valid (it inherits the token's expiry).

## Refresh strategy

Tokens live up to 1 hour. Don't try to mint long-lived ones — Chatvia rejects them.

Options for keeping a long chatter session alive:

1. **Re-mint per page load.** Cheapest, fits server-rendered apps.
2. **Refresh from your session endpoint.** SPA apps: every 30 minutes, fetch `/api/chatvia-token` from your backend (which mints a fresh JWT) and call `window.Chatvia.setAuth(newToken)`.
3. **Let it expire.** If your team's sessions are short anyway (online banking, sensitive tools), letting the chat session end with the JWT is fine.

## What rejection looks like

A bad token (expired, wrong audience, wrong signature) resolves to "not signed in." The agent then behaves as if the request were unauthenticated, per surface:

- **Hosted Chat** — sign-in-required page.
- **Widget** — hidden panel; the bubble shows a lock icon.
- **Bar** — the trigger stays inert; opening the modal is blocked until a valid token arrives.

Chatvia never returns a partial response with a half-authenticated identity — it's signed-in or not, no in-between.

## Auditing access

The `sub` value from a successful sign-in is attached to the conversation. Your team will see it in **Chat logs** beside each conversation. Use it to:

- Link conversations to your business's user records.
- Filter the log by your own user IDs.
- Reconcile with your CRM or product analytics.

## Common mistakes

- **Wrong audience.** `aud` must be the agent's external ID, not its name or subdomain.
- **Wrong subject.** `sub` must map to the workspace owner, a teammate, or a chatter who has been granted access to this agent. Just any string isn't enough.
- **Token in URL hash.** Don't put the token in `#fragment` — it ends up in browser history and can leak. Use `?token=` (consumed and stripped) or `data-auth` (never in the URL).
- **Signing on the client.** Never ship the signing secret to the browser. If it's bundled in JS, anyone can mint sign-ins as anyone.
