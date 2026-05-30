# API keys

An API key is a bearer token that lets your servers call the [Chatvia public API](https://chatvia.ai/docs/api) on behalf of your account. One key works for everything — managing agents, sources, functions, MCP servers, teams, and conversations.

![SCREENSHOT — API keys page](/screenshots/api-keys.png)

## Creating a key

Open **Settings → API keys** and click **Create key**. Give it a name that describes where you'll use it:

- `production-server`
- `ci-deploy-pipeline`
- `analytics-export`

Click **Create**. The plaintext token is shown **once**, in a dialog with a copy button:

![SCREENSHOT — API key created dialog](/screenshots/api-key-created.png)

::: warning Copy it now
Chatvia stores only a hashed version. Once you close the dialog, the plaintext is gone forever. If you lose it, revoke the key and create a new one.
:::

## Using a key

Pass the key as a `Bearer` token:

```bash
curl https://chatvia.ai/api/v1/agents \
  -H "Authorization: Bearer YOUR_API_KEY"
```

Or in code:

```javascript
const response = await fetch('https://chatvia.ai/api/v1/agents', {
  headers: { Authorization: `Bearer ${process.env.CHATVIA_API_KEY}` },
})
```

## What a key can do

A key inherits your **team-owner** permissions on every team you own. It can:

- Read and modify any agent your account owns.
- Add, update, and delete sources.
- Manage account-level functions and MCP servers.
- Create teams, roles, and members.
- Read and delete conversation history.

It **cannot**:

- Sign in to the dashboard UI.
- Change your password or delete your account.
- Access agents owned by teams you don't belong to.

## Revoking a key

Click the trash icon next to a key in **Settings → API keys** to revoke it immediately. Any in-flight request using that key fails with `401 Unauthorized`.

Revoking is the right response to a leaked key — it's instant and doesn't affect other keys.

## Listing and auditing

The keys table shows:

- **Name** — what you called it.
- **Last used** — timestamp of the most recent successful request (refreshed asynchronously).
- **Created** — when you minted it.

Use **Last used** to spot keys no one's using anymore. Rotate stale keys regularly.

## Best practices

- **One key per service.** Don't share a single key across CI, the production app, and your laptop. If one needs to be rotated, the others keep working.
- **Don't ship keys to the browser.** Anything the user can view-source on can be extracted. Use keys only on backends.
- **Store in env vars, not source control.** A leaked key in a repo is a key in someone else's repo too.
- **Rotate periodically.** Even with good hygiene, key compromise is a thing. A 90-day rotation is a sensible default.

## Rate limits

API requests are throttled per key. You'll get `429 Too Many Requests` if you exceed the limit; back off and retry. Bulk operations (re-ingesting all sources, importing a thousand functions) are best paced with a small concurrency limit on your side.

## Sign-in tokens vs. API keys

Don't confuse these:

- **API keys** authenticate your servers. Long-lived, secret, server-only.
- **Sign-in tokens** authenticate your **end-users** into a specific agent. Short-lived, signed with your signing secret, may be embedded in the browser.

See [Identity verification](/guide/account/identity) for sign-in tokens.
