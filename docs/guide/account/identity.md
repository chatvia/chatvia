# Identity verification

A **protected agent** is one only your authenticated users can talk to. Instead of building your own auth between your app and Chatvia, you sign a short-lived JWT on your backend, hand it to the visitor, and Chatvia trusts whoever you say they are.

This is how you put a private support bot in your dashboard, or an internal assistant on your intranet, without rebuilding sign-in.

![SCREENSHOT — identity verification settings page](/screenshots/identity-verification.png)

## The signing secret

There is **one signing secret per account**, in **Settings → Identity verification**. It signs tokens for every agent you own — you don't need a per-agent secret.

The secret is shown in full (you'll need it on your server). Use the eye icon to reveal, the copy button to grab it. Store it as an environment variable on your backend — never ship it to the browser.

::: warning Treat it like a password
Anyone with the secret can sign in as **any of your users** to **any of your agents**. Rotate it if you ever suspect a leak.
:::

### Regenerating

Clicking **Regenerate** mints a brand-new secret. The old one stops working immediately, which means **every JWT signed with the old secret is invalidated**, including ones already in flight. You'll need to redeploy your backend with the new secret before users can sign in again.

Regenerate when:

- The secret may have been exposed (committed to a public repo, shipped in a bundle, screenshared).
- An employee with access to backend secrets has left.
- You haven't rotated in 6–12 months.

## How to mint a token

The token is a standard **JWT signed with HS256** using your signing secret. It has three required claims:

| Claim | Value |
| --- | --- |
| `sub` | The end-user's ID in *your* system (any opaque string). |
| `aud` | The agent ID (the same value you pass to the widget as `data-agent-id`). |
| `exp` | Unix expiry timestamp. **Max lifetime is 1 hour** (3600 seconds). |

Chatvia rejects tokens with `exp` more than 1 hour in the future, so don't try to mint long-lived tokens.

### Node.js

```js
const jwt = require('jsonwebtoken')

// Keep on the server — never ship to the browser.
const SIGNING_SECRET = process.env.CHATVIA_SIGNING_SECRET

function signChatviaToken(userId, agentId, ttlSeconds = 3600) {
  return jwt.sign(
    {
      sub: userId,  // the id of the user you are signing in
      aud: agentId, // the agent ID (same as data-agent-id)
    },
    SIGNING_SECRET,
    { algorithm: 'HS256', expiresIn: ttlSeconds },
  )
}

// Pass it to the widget as data-auth.
const token = signChatviaToken('user_123', 'YOUR_AGENT_ID')
```

### PHP

```php
<?php

use Firebase\JWT\JWT;

$secret = getenv('CHATVIA_SIGNING_SECRET');

function sign_chatvia_token(string $secret, string $userId, string $agentId, int $ttlSeconds = 3600): string
{
    return JWT::encode([
        'sub' => $userId,
        'aud' => $agentId,
        'exp' => time() + $ttlSeconds,
    ], $secret, 'HS256');
}

$token = sign_chatvia_token($secret, 'user_123', 'YOUR_AGENT_ID');
```

### Python

```python
import jwt, time, os

SIGNING_SECRET = os.environ['CHATVIA_SIGNING_SECRET']

def sign_chatvia_token(user_id: str, agent_id: str, ttl_seconds: int = 3600) -> str:
    return jwt.encode(
        {'sub': user_id, 'aud': agent_id, 'exp': int(time.time()) + ttl_seconds},
        SIGNING_SECRET,
        algorithm='HS256',
    )
```

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

The widget reads `data-auth` on load, exchanges it for a session, and the visitor is signed in. If the token is missing or invalid the widget shows a "sign-in required" state.

### Hosted chat — `?token=`

```
https://your-agent.chatvia.ai/?token=THE_TOKEN_YOU_SIGNED
```

Redirect signed-in users from your app to this URL. Chatvia validates the token and establishes a session cookie. The `?token=` parameter is stripped from the URL on success.

## Verification rules

Chatvia rejects a token if:

- The signature doesn't match your current secret.
- `exp` has passed.
- `exp` is more than 1 hour in the future.
- `aud` doesn't match the agent ID.
- The `sub` user isn't the agent's owner or isn't a user with role-based access to that specific agent.

A rejected token resolves to "not signed in" — for a protected agent that means access is denied.

## Lifetime guidance

A 1-hour TTL is generous for most apps. Refresh tokens by minting a new one on each page load — it's cheap. Don't try to bake in long-lived tokens for "remember me" — that's what your own sign-in is for; refresh Chatvia tokens from inside your session.

## Marking an agent as protected

By default a knowledge-base agent is **public**. Make it private:

- Open the agent's **Settings** (or **Widget → Security** for widgets).
- Toggle **Public access** off.

A protected agent that receives a request without a valid token shows the sign-in-required page (chat) or hides the widget panel (widget) until a token arrives.

## Common mistakes

- **Wrong audience.** `aud` must be the agent ID — the same value you pass to the widget as `data-agent-id`. Not the name, not the subdomain.
- **Wrong subject.** `sub` should match a user on the agent's account. The agent's owner always works; other subjects must be users you've added via [Teams → roles](/guide/account/teams) with access to that agent.
- **Algorithm confusion.** Use HS256. Chatvia rejects `none` and other algorithms.
- **Clock skew.** Your server clock should be within a few seconds of UTC, or short-lived tokens may be rejected.

## Sign-in links via the API

If your backend doesn't have a JWT library handy, the API can mint a sign-in token for you. See the [API reference](https://chatvia.ai/docs/api) under **Sign-in links**.
