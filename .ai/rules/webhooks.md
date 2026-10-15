---
paths:
  - 'src/App/Http/Controllers/Webhooks/**'
  - 'src/Domain/Channel/**'
  - 'src/Support/Http/**'
  - routes/slack.php
  - routes/telegram.php
  - routes/whatsapp.php
  - routes/messenger.php
  - routes/instagram.php
  - routes/msteams.php
---

# Webhooks

## Give each provider its own route file
Inbound webhook routes live in `routes/{channel}.php`, loaded as a bare group in `bootstrap/app.php`: no web middleware, so no session and no CSRF. Name the route `{channel}.webhook` and throttle it per IP with a prefix (`throttle:1200,1,{channel}-webhook`).

## Verify before anything else
An invokable `{Channel}WebhookController` first finds the tenant by its secret path segment, then checks the signature over the raw body (`$request->getContent()`) with `hash_equals()`. When the provider signs a timestamp, reject anything more than five minutes off. Meta channels share `InteractsWithMetaWebhooks`; Teams checks the Bot Framework JWT.

## Always answer 200
Reply 200 `{ok: true}` and quietly ignore a bad signature or an unknown tenant. A non-2xx answer makes the provider retry and, in the end, switch the subscription off.

## Answer fast; work on the queue
Controller → `Receive{Channel}…Action` (filter, dedupe, dispatch) → `Handle{Channel}…Job` → Action or `ChannelReplyRunner`. Drop bot echoes, subtypes and messages not meant for the agent in the Receive Action, before anything is queued.

## Process each event once
Before dispatching, claim the event with `InboundDeliveryLog::firstDelivery("{channel}:{kind}:{tenantId}:{eventId}", $until)`, where `$until` outlasts the provider's retry window. Inbound reply jobs get one try: an agent turn bills a message and sends a reply, so it must never run twice.

## Mirror the web chat's gates
`ChannelReplyRunner` applies the same per-turn gates as `ChatStreamService`. When you add or change a gate in one, change the other. Agent protection is checked only by the web middleware, so a channel needs its own check.

## Send outgoing webhooks through one sender
Every webhook to a customer goes through `Support\Http\SignedWebhookSender` (HMAC of the raw JSON, `SafeUrl` pinning, no redirects, short timeout, throws on a non-2xx). Send it from a queued job or listener with `afterCommit`, tries and backoff, and a `failed()` that records the failure; switch the endpoint off after repeated final failures. Never rename the `X-Chatvia-*` headers.

## Test the security paths
Test bad, missing and stale signatures (ignored, still 200) and duplicate events (processed once).
