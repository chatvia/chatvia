# Functions (webhooks)

Functions let an agent call **your business systems** during a conversation — your CRM, your order system, your internal microservices, anything behind an HTTP endpoint. The model decides when to call a function based on its description and the chatter's question, builds a JSON payload from the schema your team defined, Chatvia POSTs to your webhook, and the response goes back to the model to compose a final reply.

This is the way to do things like:

- Look up an order in your order system.
- Create a ticket in your support tool.
- Check inventory in your warehouse service.
- Trigger an internal workflow on your team's backend.

![SCREENSHOT — functions list](/screenshots/functions-list.png)

## Where to author functions

Functions live at the **workspace level** in **Settings → Functions**. Each function can be attached to one or more agents, so your team writes a function once and reuses it across the support agent, the sales agent, and the docs agent.

## Anatomy of a function

| Field | Purpose |
| --- | --- |
| **Name** | Snake-cased identifier the model sees (`lookup_order`). |
| **Description** | A sentence telling the model when to call this. Critical for triggering. |
| **Parameters schema** | JSON Schema describing the arguments. Used to validate the model's call. |
| **Webhook URL** | The endpoint Chatvia POSTs to. |
| **Method** | `POST` (default) or `PUT`. |
| **Timeout** | Max time to wait for a response (default 10 s, max 30 s). |
| **Max response bytes** | Cap on payload size returned to the model. |
| **Authentication** | None, custom header, Bearer token, or Basic auth. |
| **Approval** | Run automatically, or pause the turn for a human to approve. |

![SCREENSHOT — function editor](/screenshots/function-editor.png)

## A worked example

A function that looks up an order by ID:

```json
{
  "name": "lookup_order",
  "description": "Retrieve order details (status, items, shipping address) for a given order ID.",
  "parameters_schema": {
    "type": "object",
    "properties": {
      "order_id": {
        "type": "string",
        "description": "The order's external identifier, e.g. ORD-12345"
      }
    },
    "required": ["order_id"]
  },
  "webhook_url": "https://api.acme.com/internal/orders/lookup",
  "webhook_method": "POST",
  "auth_type": "bearer",
  "auth_config": { "token": "..." },
  "timeout_ms": 8000
}
```

When a chatter says "where's my order ORD-12345?", the model calls `lookup_order` with `{ "order_id": "ORD-12345" }`. Chatvia POSTs to your URL:

```http
POST /internal/orders/lookup HTTP/1.1
Authorization: Bearer …
Content-Type: application/json

{ "order_id": "ORD-12345" }
```

Your endpoint returns JSON:

```json
{
  "status": "shipped",
  "tracking_url": "https://parcel.example/track/9X3K",
  "shipped_at": "2026-05-28"
}
```

The model uses that JSON to compose its reply — usually summarizing it in natural language.

## Writing a good description

The model decides whether to call a function based mostly on its description. Be specific:

- ✅ "Retrieve order details (status, items, shipping address) for a given order ID."
- ❌ "Order helper."

If two functions sound similar, the model will get confused about which to call. Mention disambiguating words ("by order ID", "by customer email").

## Attaching to agents

From **Settings → Functions**, click the agents button on a function to attach or detach it from specific agents. Or do it the other way around from the agent's **Sources → Functions** tab. A function only runs for an agent it has been attached to — workspace-level authoring, per-agent activation.

## Approval flow

Some functions write to your business systems — refund an order, cancel a subscription, push to production. For those, your workspace owner can mark the function as **require approval**. When the agent decides to call it, the turn pauses at a **ToolApprovalCheckpoint** and a teammate or chatter sees the proposed call (function name + arguments) with **Allow** or **Deny** buttons.

- **Allow** — Chatvia sends the request, feeds the response back to the model, and the turn resumes.
- **Deny** — the model is told the call was rejected and writes its reply without invoking the function.

Read-only lookups (order status, inventory) are usually safe to leave on auto-run. Reserve approval for actions that change state.

::: tip
Approval is set per function in **Settings → Functions**. You can change it any time without editing the schema.
:::

## Security

- The webhook URL is called server-to-server from Chatvia. Chatters never see it.
- Use the **authentication** field — Bearer or Basic — to verify the request originated from Chatvia.
- For extra safety, allowlist Chatvia's outbound IPs in your firewall (see Chatvia support for the current list).
- Returning large responses inflates token cost and slows the conversation. Keep responses to what the model needs to answer.

## Observability

Each function tracks:

- **Invocation count** — total times called across all agents.
- **Last invoked at** — timestamp of the last call.
- **Last error** — most recent failure message, if any.

Use these to spot broken integrations before your customers notice.

## Disabling temporarily

Toggle a function off without deleting it. Disabled functions stop being attached to agents until your team re-enables them.

## Functions via API

Your team can manage functions programmatically — useful if you generate function definitions from your OpenAPI schema. See the [API reference](https://chatvia.ai/docs/api) under **Functions**.
