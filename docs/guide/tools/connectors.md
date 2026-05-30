# MCP connectors

Connectors let an agent talk to **third-party tools** through the Model Context Protocol (MCP) — an open standard for exposing structured tools to LLMs. Chatvia ships a catalog of one-click connectors (GitHub, Slack, Google Drive, Notion, etc.) and lets you add custom MCP servers by URL.

![SCREENSHOT — connectors list](/screenshots/connectors-list.png)

## Functions vs. connectors

If you already read about [functions](/guide/tools/functions), here's the difference:

| | Functions | Connectors |
| --- | --- | --- |
| **Origin** | You write the webhook | Existing MCP server (catalog or custom) |
| **Protocol** | Plain HTTP + JSON | MCP (stdio or SSE) |
| **Tool surface** | One tool per function | Many tools per connector |
| **Auth** | Bearer / Basic / custom header | OAuth, token, or none |
| **Best for** | Your own internal APIs | Third-party SaaS your agent should read or write |

You can use both in one agent.

## Adding a catalog connector

Open **Settings → Connectors → Add**. Pick from the catalog:

![SCREENSHOT — connector catalog](/screenshots/connector-catalog.png)

For OAuth-based connectors (Slack, GitHub, Google Drive…), clicking **Connect** opens an OAuth flow in a new tab. After you approve, the connector lands in **Connected** with a green dot.

For token-based connectors (Notion, Linear…), you'll be prompted for an API token.

## Adding a custom MCP server

If a tool you want isn't in the catalog, click **Add custom** and supply:

- **Name** — display label.
- **URL** — your MCP endpoint (SSE or HTTP).
- **Auth type** — none, Bearer, Basic, or custom header.
- **Auth config** — credentials, if any.

Chatvia hits the server's MCP introspection endpoint to discover its tools and caches them. The connector shows up just like a catalog one.

## Tool selection

Each connector exposes one or more **tools** — `slack.send_message`, `github.create_issue`, etc. By default all tools are enabled. Open the connector to toggle individual tools off if you only want a subset (e.g. Slack-read-only).

## Attaching to agents

Like functions, connectors are account-level. Attach them per agent from:

- **Settings → Connectors → Agents button** on the connector, or
- **Agent → Sources → Connectors**.

An agent only sees the tools you've enabled on a connector you've attached.

## How the model invokes a tool

The flow is the same as for functions:

1. Visitor asks a question.
2. The model sees the agent's available tools (function names + descriptions, MCP tool names + schemas).
3. The model emits a tool call.
4. Chatvia forwards the call to the MCP server (or the function webhook).
5. The response is fed back to the model, which composes its reply.

## OAuth token refresh

For OAuth connectors, Chatvia stores both the access token and the refresh token. When the access token expires, Chatvia refreshes it automatically using the refresh token — visitors and tools keep working.

If the refresh token itself expires (typically after weeks or months of inactivity), the connector lights up red and you'll need to reconnect.

## Removing a connector

Disconnect from **Settings → Connectors → ⋯ → Disconnect**. This revokes the OAuth token, drops the cached tool catalog, and detaches the connector from every agent.

## Per-agent connectors

If you only need a connector for one agent (rare but possible — say, a private Slack workspace), add it from the agent's **Sources → Connectors** page directly. It still lives at the account level but won't be attached to other agents by default.
