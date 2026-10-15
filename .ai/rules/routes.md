---
paths:
  - 'routes/**'
  - src/App/Providers/RateLimitServiceProvider.php
---

# Routes and rate limits

## Name and shape routes consistently
URIs are plural kebab-case nouns (`/agents/{agent}/chat-logs`), never verbs or underscores. Route parameters are camelCase (`{agentSlug}`) or `{model:external_id}`. Every route has a dot-separated name with kebab-case words (`data-export.download`). The frontend builds URLs from these names through Wayfinder, so never rename one without a plan.

## Register routes one by one
Register each route explicitly and name it; don't use `Route::resource()` or `Route::apiResource()`.

## Gate groups with middleware
Permission checks shared by a group go on the route: `workspace.scope`, `can.access.agent`, `agent.permission:…`, `team.permission:…`. `.ai/rules/authorization.md` has the details.

## Throttle on the route
Rate limits are route middleware, never code in Form Requests or controllers. An inline `throttle:6,1,register` is fine for a one-off public or auth route; always pass the third prefix argument, so routes with the same numbers don't share a counter. Use a named limiter from `App\Providers\RateLimitServiceProvider` (grouped in `configure{Area}Limiters()` methods) when the key depends on the user, the agent or the workspace, or when limits are layered.

## Throttle what costs money or invites abuse
Throttle public and auth routes, expensive work (LLM calls, exports, OAuth) and webhooks. Authenticated CRUD shares the API's blanket `throttle:api`. Key LLM-cost limits on the server-resolved agent, never only on a session id the client can forge, and prefix keys as `{limiter}|{scope}|{id}`.
