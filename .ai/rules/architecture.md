---
paths:
  - 'src/Domain/**'
  - 'src/App/Identity/**'
  - 'src/Support/**'
---

# Architecture (domain first)

## Organize by business context
Business logic lives in `src/Domain/{Context}/` (Agent, Analytics, Channel, Conversation, Evaluation, Form, Guardrail, Integration, Knowledge, Llm, Membership, Security, Widget). Each context owns its `Actions`, `Data`, `Models`, `Enums`, `Events`, `Listeners`, `Jobs`, `Services`, `Queries`, `QueryBuilders`, `Policies`, `Observers`, `Contracts`, `Concerns` and `Exceptions`. New code goes inside its context or macro-namespace (`Knowledge\Source\Web`, `Integration\Mcp`), never as a sibling. Accounts (`Workspace`, `Chatter`, `ApiKey`) are the one exception: they live in `src/App/Identity/` with the same folders.

## Grow folders only when needed
Create a folder when its first class arrives; no empty or `Misc/` folders. Split a folder into feature subfolders (`Actions/Handoff`, `Data/Handoff`) once it passes about seven files. `Models`, `Policies`, `Observers`, `Events`, `Jobs`, `Enums`, `Exceptions`, `Contracts` and `QueryBuilders` stay flat.

## Actions do, services are used
Something the app does (create, publish, connect, delete) is an Action. Something the app uses (an adapter, a client, a stateless calculator) is a service. Name a service for its exact job (`TelegramClient`, `AgentChatRateLimiter`) with no `Service` suffix; if you cannot name it precisely, split it. No `Helper`, `Util` or `Manager` grab-bags.

## Services don't run use cases
Clients, adapters and utilities never call Actions. Only an orchestrator that runs a whole turn or resolves a channel conversation (`ChatTurnStreamer`, `ChannelReplyRunner`, the channel resolvers) may call Actions. A service never takes a `Request` and never returns an HTTP response.

## Wrap every outside system in one class
Each third-party API or SDK is called from one service (`{Vendor}Client`); Actions and controllers never call `Http::` or a vendor SDK directly. Credentials come from `config()` (filled from admin Settings), never from `env()` or a literal.

## Add an interface only for a real seam
Write an interface only for a second implementation, a test fake, or a dependency across a context boundary. Put it in `{Context}/Contracts/`, name it as a noun or capability without an `Interface` suffix (`Reranker`, `ChannelAdapter`), and bind it in `DomainServiceProvider::register()`. A driver chosen by a setting is a `bind(fn ($app): Reranker => match (…) {…})` closure.

## Keep contexts loosely coupled
A context may use another context's models, DTOs and services; calling another context's Action should be rare. `Domain\Integration` never references `Domain\Conversation`. Domain code never imports `App\…` (except `App\Identity`) or `Illuminate\Http\Request`.

## Model a status change as an Action
A state change (hand off, take over, publish, mark failed) is its own `{Verb}{Noun}Action`: check a guard predicate on the model (`isHandedOff()`), update the enum column and its timestamp, then dispatch an event. Never write a status column from a controller or a job. Use an enum for the states, not a state-machine package or one class per state.

## Key data to the workspace
Workspace data is keyed to `workspace_id` (`$agent->workspace_id`, `$user->workspace()->id`), never to the acting person. Child rows take `workspace_id` from their parent. Storage paths start with the entity: `workspaces/{ext}/[agents/{ext}/]…`, a person's own files `users/{ext}/…`, a chatter's photo `chatters/{ext}/…`.

## Gate usage in a shared service
A usage limit inside application code is a Domain service that every surface shares (`AgentChatRateLimiter`, `ToolRateLimiter`, `MessageAllowance`), so the web chat, channels and voice apply the same gate. Never call `RateLimiter::hit()` from a controller.

## Use a pipeline only for many reorderable steps
Use `Illuminate\Pipeline` only for about five or more steps that share state and may be reordered or switched by config (the PII filters in `PiiDetector`). The state is a `final` class that owns its mutation helpers; steps implement `handle($state, Closure $next)` on a shared base class and live in the context. Fewer steps are plain method calls or Actions.

## Traits are Concerns
A trait lives in a `Concerns/` folder next to the classes that use it (`Support/Concerns` when generic), is named for one capability (`HasSettings`, `ResolvesWorkspaceAgents`, `InteractsWithMetaWebhooks`), and has no `Trait` suffix. If describing it needs "and", split it.

## Support is generic
`src/Support/` holds only pieces with no business knowledge: `Concerns` (`HasExternalId`), `Casts`, shared `Exceptions` (`DomainValidationException`), `Http` (`SafeUrl`, `SignedWebhookSender`), `Text`, TypeScript transformers and the single `helpers.php`. Support never references Domain or App. A helper that grows becomes a class; never add a second helpers file.
