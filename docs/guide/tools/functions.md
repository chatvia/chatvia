# Functions (webhooks)

Functions let an agent call **your business systems** during a conversation — your CRM, your order system, your internal microservices, anything behind an HTTP endpoint. The model decides when to call a function based on its description and the chatter's question, builds a JSON payload from the schema your team defined, Chatvia calls your webhook, and the response goes back to the model to compose a final reply.

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
| **Webhook URL** | The endpoint Chatvia calls. |
| **Method** | `POST` (default), `GET`, `PATCH`, or `DELETE`. `GET` sends the arguments as a query string. The other methods send them as a JSON body. |
| **Timeout** | Max time to wait for a response (default 10 s, max 30 s). |
| **Max response bytes** | Cap on payload size returned to the model. |
| **Authentication** | None, Bearer token, custom header, or HMAC signature. |
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

When a chatter says "where's my order ORD-12345?", the model calls `lookup_order` with `{ "order_id": "ORD-12345" }`. Chatvia sends a POST request to your URL:

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
- Use the **authentication** field to check that the request comes from Chatvia. A Bearer token or a custom header is simple. An **HMAC signature** is the strongest choice, because the secret is never sent over the network (see below).
- For extra safety, allowlist Chatvia's outbound IPs in your firewall (see Chatvia support for the current list).
- Returning large responses inflates token cost and slows the conversation. Keep responses to what the model needs to answer.

## Verifying HMAC signatures

When a function uses **HMAC signature** authentication, you choose a secret and save it in the function. Chatvia never sends the secret. Instead, it signs every request and adds two headers:

| Header | Value |
| --- | --- |
| `X-Chatvia-Timestamp` | The time the request was signed, in Unix seconds. |
| `X-Chatvia-Signature` | `sha256=` followed by a hex HMAC-SHA256 signature. |

Chatvia signs this text with your secret:

```text
{timestamp}.{payload}
```

- `{timestamp}` is the value of the `X-Chatvia-Timestamp` header.
- `{payload}` is the **raw request body** for `POST`, `PATCH` and `DELETE`.
- `{payload}` is the **raw query string** (the part after `?`) for `GET`. It is empty when there are no arguments.

To check a request on your server:

1. Read the raw body (or the raw query string for `GET`) **before** you parse it. Parsing and encoding it again can change the bytes.
2. Build `{timestamp}.{payload}` and compute the HMAC-SHA256 with your secret.
3. Compare your result with the signature in the header. Use a constant-time compare.
4. Reject the request if the timestamp is more than 5 minutes old. This stops someone from sending a captured request again later.

Example in Node.js (Express):

```js
const crypto = require('crypto');
const express = require('express');

const app = express();
const SECRET = process.env.CHATVIA_FUNCTION_SECRET;

// Keep the raw body so the signature can be checked.
app.use(express.raw({ type: 'application/json' }));

function isValidChatviaRequest(req) {
  const timestamp = req.get('X-Chatvia-Timestamp') || '';
  const signature = req.get('X-Chatvia-Signature') || '';

  const age = Math.abs(Date.now() / 1000 - Number(timestamp));
  if (!timestamp || Number.isNaN(age) || age > 300) return false;

  const payload = req.method === 'GET'
    ? (req.originalUrl.split('?')[1] || '')
    : (req.body.length ? req.body.toString('utf8') : '');

  const expected = 'sha256=' + crypto
    .createHmac('sha256', SECRET)
    .update(`${timestamp}.${payload}`)
    .digest('hex');

  const a = Buffer.from(expected);
  const b = Buffer.from(signature);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

app.post('/internal/orders/lookup', (req, res) => {
  if (!isValidChatviaRequest(req)) return res.status(401).end();
  const args = JSON.parse(req.body.toString('utf8'));
  // ... look up the order ...
  res.json({ status: 'shipped' });
});
```

Example in PHP:

```php
$secret = getenv('CHATVIA_FUNCTION_SECRET');
$timestamp = $_SERVER['HTTP_X_CHATVIA_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_CHATVIA_SIGNATURE'] ?? '';

$payload = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? ($_SERVER['QUERY_STRING'] ?? '')
    : file_get_contents('php://input');

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

if (abs(time() - (int) $timestamp) > 300 || ! hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}
```

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
