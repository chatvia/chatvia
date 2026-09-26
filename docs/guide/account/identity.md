# Identity verification

A **protected agent** is one only your business's authenticated end-users can talk to. Instead of building your own auth between your app and Chatvia, your backend signs a short-lived JWT, hands it to the chatter, and Chatvia trusts whoever you say they are.

This is how your team puts a private support bot inside a customer dashboard, or an internal assistant on the company intranet, without rebuilding sign-in.

![SCREENSHOT — identity verification settings page](/screenshots/identity-verification.png)

## The signing secret

There is **one signing secret per workspace**, in **Settings → Identity verification**. It signs tokens for every agent in the workspace — there is no per-agent secret.

The secret is shown in full (your team will need it on the server). Use the eye icon to reveal, the copy button to grab it. Store it as an environment variable on your backend — never ship it to the browser.

::: warning Treat it like a password
Anyone with the secret can sign in as **any chatter** in **any agent** of the workspace. Rotate it if you suspect a leak.
:::

### Regenerating

Clicking **Regenerate** mints a brand-new secret. The old one stops working immediately, which means **every JWT signed with the old secret is invalidated**, including ones already in flight. Your team will need to redeploy the backend with the new secret before chatters can sign in again.

Regenerate when:

- The secret may have been exposed (committed to a public repo, shipped in a bundle, screenshared).
- A teammate with access to backend secrets has left the company.
- The workspace hasn't rotated in 6–12 months.

## How to mint a token

The token is a standard **JWT signed with HS256** using your signing secret. It has three required claims:

| Claim | Value |
| --- | --- |
| `sub` | The chatter's **Chatvia ID** — the `id` returned by `POST /api/v1/chatters` when you provisioned them (or shown in **Settings → Members**). Not your own app's user ID: Chatvia can only sign in a chatter it already knows. |
| `aud` | The agent ID (the same value passed to the widget as `data-agent-id`). |
| `exp` | Unix expiry timestamp. **Max lifetime is 1 hour** (3600 seconds). |

Chatvia rejects tokens with `exp` more than 1 hour in the future, so don't try to mint long-lived tokens.

### Node.js

```js
const jwt = require('jsonwebtoken')

// Keep on the server — never ship to the browser.
const SIGNING_SECRET = process.env.CHATVIA_SIGNING_SECRET

function signChatviaToken(chatterId, agentId, ttlSeconds = 3600) {
  return jwt.sign(
    {
      sub: chatterId, // the chatter's Chatvia id (from POST /api/v1/chatters)
      aud: agentId,   // the agent ID (same as data-agent-id)
    },
    SIGNING_SECRET,
    { algorithm: 'HS256', expiresIn: ttlSeconds },
  )
}

// Pass it to the widget as data-auth.
const token = signChatviaToken(user.chatviaChatterId, 'YOUR_AGENT_ID')
```

### PHP

```php
<?php

use Firebase\JWT\JWT;

$secret = getenv('CHATVIA_SIGNING_SECRET');

function sign_chatvia_token(string $secret, string $chatterId, string $agentId, int $ttlSeconds = 3600): string
{
    return JWT::encode([
        'sub' => $chatterId,
        'aud' => $agentId,
        'exp' => time() + $ttlSeconds,
    ], $secret, 'HS256');
}

$token = sign_chatvia_token($secret, $user->chatvia_chatter_id, 'YOUR_AGENT_ID');
```

### Python

```python
import jwt, time, os

SIGNING_SECRET = os.environ['CHATVIA_SIGNING_SECRET']

def sign_chatvia_token(chatter_id: str, agent_id: str, ttl_seconds: int = 3600) -> str:
    return jwt.encode(
        {'sub': chatter_id, 'aud': agent_id, 'exp': int(time.time()) + ttl_seconds},
        SIGNING_SECRET,
        algorithm='HS256',
    )
```

Store the chatter ID your backend gets from `POST /api/v1/chatters` next to your own user record, and sign that. The three snippets above read it from the user (`chatviaChatterId` / `chatvia_chatter_id`).

## Where the token goes

Two delivery surfaces:

### Widget — `data-auth`

```html
<script
  id="chatbot-embed-script"
  src="https://chatvia.ai/embed.js"
  data-agent-id="YOUR_AGENT_ID"
  data-auth="THE_TOKEN_YOU_SIGNED"
  defer></script>
```

The widget reads `data-auth` on load, exchanges it for a session, and the chatter is signed in. If the token is missing or invalid the widget shows a "sign-in required" state.

### Hosted chat — `?token=`

```
https://your-agent.chatvia.ai/?token=THE_TOKEN_YOU_SIGNED
```

Redirect signed-in chatters from your app to this URL. Chatvia validates the token and establishes a session cookie. The `?token=` parameter is stripped from the URL on success.

## Verification rules

Chatvia rejects a token if:

- The signature doesn't match the current workspace secret.
- `exp` has passed.
- `exp` is more than 1 hour in the future.
- `aud` doesn't match the agent ID.
- The `sub` isn't a chatter granted access to the agent, the workspace owner, or a teammate.

A rejected token resolves to "not signed in" — for a protected agent that means access is denied.

## Lifetime guidance

A 1-hour TTL is generous for most apps. Refresh tokens by minting a new one on each page load — it's cheap. Don't try to bake in long-lived tokens for "remember me" — that's what your own sign-in is for; refresh Chatvia tokens from inside the chatter's session in your app.

## Marking an agent as protected

By default an agent is **public**. Protected status applies to the agent itself and covers all three surfaces — Hosted Chat, Widget, and Bar. Make it private:

- Open the agent's **Settings → Security**.
- Toggle **Public access** off.

A protected agent that receives a request without a valid token shows the sign-in-required page on Hosted Chat and Bar, and hides the panel on the Widget, until a valid token arrives.

## Common mistakes

- **Wrong audience.** `aud` must be the agent ID — the same value passed to the widget as `data-agent-id`. Not the name, not the subdomain.
- **Wrong subject.** `sub` should resolve to a chatter granted access to the agent, the workspace owner, or a teammate. Add chatters from **Settings → Members** (workspace-wide) or from each agent's **Members** tab. See [Teams → roles](/guide/account/teams) for teammate roles.
- **Algorithm confusion.** Use HS256. Chatvia rejects `none` and other algorithms.
- **Clock skew.** Your server clock should be within a few seconds of UTC, or short-lived tokens may be rejected.

## Sign-in links via the API

If your backend doesn't have a JWT library handy, the API can mint a sign-in token. See the [API reference](https://chatvia.ai/docs/api) under **Sign-in links**.
