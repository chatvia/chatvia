# MCP connectors

Connectors are how your agents talk to the rest of your business stack. They expose third-party tools — GitHub, Slack, Jira, Linear, Notion, Stripe, Atlassian, plus any custom MCP server — through the Model Context Protocol (MCP), an open standard for exposing structured tools to LLMs.

Connectors are authored once at the **workspace level** (Settings → Connectors) by a teammate, then attached to the agents that need them. The same GitHub connection can power a support agent, an engineering agent, and an internal ops agent without re-authenticating.

![SCREENSHOT — connectors list](/screenshots/connectors-list.png)

## Functions vs. connectors

If you already read about [functions](/guide/tools/functions), here's the difference:

| | Functions | Connectors |
| --- | --- | --- |
| **Origin** | Your team writes the webhook | Existing MCP server (catalog or custom) |
| **Protocol** | Plain HTTP + JSON | MCP (stdio or SSE) |
| **Tool surface** | One tool per function | Many tools per connector |
| **Auth** | Bearer / Basic / custom header | OAuth, token, or none |
| **Best for** | Your own internal APIs | Third-party SaaS your agent should read or write |

You can use both in one agent.

## Adding a catalog connector

Open **Settings → Connectors → Add**. Pick from the branded catalog — GitHub, Slack, Jira, Linear, Notion, Stripe, Atlassian, and more:

![SCREENSHOT — connector catalog](/screenshots/connector-catalog.png)

For OAuth-based connectors (Slack, GitHub, Atlassian, Stripe…), clicking **Connect** opens an OAuth flow in a new tab. After a teammate approves, the connector lands in **Connected** with a green dot.

For token-based connectors (Notion, Linear…), the workspace owner is prompted for an API token.

## Adding a custom MCP server

If a tool your team needs isn't in the catalog, click **Add custom** and supply:

- **Name** — display label.
- **URL** — your MCP endpoint (SSE or HTTP).
- **Auth type** — none, Bearer, Basic, or custom header.
- **Auth config** — credentials, if any.

Chatvia hits the server's MCP introspection endpoint to discover its tools and caches them. The connector shows up just like a catalog one.

## Tool selection and per-tool permissions

Each connector exposes one or more **tools** — `slack.send_message`, `github.create_issue`, etc. Every tool has a tri-state permission, set per agent:

- **Allow** — the agent can call the tool freely. Use this for safe reads.
- **Require approval** — the agent can request the tool, but the turn pauses at a `ToolApprovalCheckpoint` until a teammate (or, depending on configuration, the chatter themselves) approves or rejects the call. Use this for writes, money movement, or destructive actions.
- **Blocked** — the tool is hidden from the model. Use this to narrow the surface (e.g. Slack-read-only).

Approval-required calls do not block the rest of the conversation — the turn simply waits, and the chatter sees a clear "waiting for approval" state until it resolves.

## Attaching to agents

Connectors live at the workspace level. Attach them per agent from:

- **Settings → Connectors → Agents button** on the connector, or
- **Agent → Sources → Connectors**.

An agent only sees the tools you've allowed on a connector you've attached.

## How the model invokes a tool

The flow is the same as for functions:

1. A chatter asks a question.
2. The model sees the agent's available tools (function names + descriptions, MCP tool names + schemas).
3. The model emits a tool call.
4. Chatvia checks the per-tool permission. If the tool requires approval, the turn pauses at a `ToolApprovalCheckpoint` and a teammate (or the chatter) is notified.
5. Once approved (or if the tool is set to **allow**), Chatvia forwards the call to the MCP server (or the function webhook).
6. The response is fed back to the model, which composes its reply.

## OAuth token refresh

For OAuth connectors, Chatvia stores both the access token and the refresh token. When the access token expires, Chatvia refreshes it automatically using the refresh token — chatters and tools keep working.

If the refresh token itself expires (typically after weeks or months of inactivity), the connector lights up red and a teammate needs to reconnect it.

## Removing a connector

Disconnect from **Settings → Connectors → ⋯ → Disconnect**. This revokes the OAuth token, drops the cached tool catalog, and detaches the connector from every agent in the workspace.

## Per-agent connectors

If you only need a connector for one agent (rare but possible — say, a private Slack workspace for a single internal agent), add it from the agent's **Sources → Connectors** page directly. It still lives at the workspace level but won't be attached to other agents by default.
