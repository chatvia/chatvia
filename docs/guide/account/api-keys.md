# API keys

An API key is a bearer token that lets your backend services call the [Chatvia public API](https://chatvia.ai/docs/api) on behalf of your workspace. One key works for everything your team needs — managing agents, sources, functions, MCP connectors, members, and conversations.

![SCREENSHOT — API keys page](/screenshots/api-keys.png)

## Creating a key

Open **Settings → API keys** and click **Create key**. Give it a name that describes where your team will use it:

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

A key acts on behalf of your workspace with full workspace-owner permissions. It can:

- Read and modify any agent in your workspace.
- Add, update, and delete sources.
- Manage workspace-level functions and MCP connectors.
- Invite teammates, assign roles, and manage chatter access.
- Read and delete conversation history.

It **cannot**:

- Sign in to the dashboard UI.
- Change a teammate's password or delete the workspace.
- Access agents owned by other workspaces.

## Revoking a key

Click the trash icon next to a key in **Settings → API keys** to revoke it immediately. Any in-flight request using that key fails with `401 Unauthorized`.

Revoking is the right response to a leaked key — it's instant and doesn't affect other keys.

## Listing and auditing

The keys table shows:

- **Name** — what your team called it.
- **Last used** — timestamp of the most recent successful request (refreshed asynchronously).
- **Created** — when the key was minted.

Use **Last used** to spot keys no service is using anymore. Rotate stale keys regularly.

## Best practices

- **One key per service.** Don't share a single key across CI, the production app, and a teammate's laptop. If one needs to be rotated, the others keep working.
- **Don't ship keys to the browser.** Anything a customer can view-source on can be extracted. Use keys only on backends.
- **Store in env vars, not source control.** A leaked key in a repo is a key in someone else's repo too.
- **Rotate periodically.** Even with good hygiene, key compromise is a thing. A 90-day rotation is a sensible default.

## Rate limits

API requests are throttled per key. Your backend will get `429 Too Many Requests` if it exceeds the limit; back off and retry. Bulk operations (re-ingesting all sources, importing a thousand functions) are best paced with a small concurrency limit on your side.

## Sign-in tokens vs. API keys

Don't confuse these:

- **API keys** authenticate your backend services to Chatvia. Long-lived, secret, server-only.
- **Sign-in tokens** authenticate your **chatters** into a specific agent. Short-lived, signed with your workspace signing secret, may be embedded in the browser.

See [Identity verification](/guide/account/identity) for sign-in tokens.
