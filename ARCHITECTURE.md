# ChatVia.ai architecture

This guide explains how the ChatVia.ai code is organised and why. Read it before
you make a bigger change. [CONTRIBUTING.md](CONTRIBUTING.md) explains how to report
a bug or ask for a feature, set up the project and run the checks.

Every rule in this guide matches the code today. Many rules are also checked by
tests in `tests/Architecture/`, so CI tells you when a change breaks one.

## Contents

1. [The big picture](#1-the-big-picture)
2. [Three root namespaces](#2-three-root-namespaces)
3. [The App layer](#3-the-app-layer)
4. [Domain bounded contexts](#4-domain-bounded-contexts)
5. [How a request flows](#5-how-a-request-flows)
6. [Conventions](#6-conventions)
7. [Glossary](#7-glossary)
8. [External services and installation switches](#8-external-services-and-installation-switches)
9. [Frontend](#9-frontend)
10. [The widget package](#10-the-widget-package)
11. [Tests](#11-tests)
12. [Where does new code go?](#12-where-does-new-code-go)

---

## 1. The big picture

ChatVia.ai is a Laravel 13 application (PHP 8.5) with an Inertia + Vue 3 +
TypeScript frontend. Workspaces build AI agents, give them knowledge and tools,
and put them in front of people on a hosted chat page, an embeddable widget, a
command bar, messaging apps and a REST API.

### Processes

| Process | Command | What it does |
|---|---|---|
| Web | PHP-FPM (or `php artisan serve`) | Dashboard, hosted chat, widget API, REST API, webhooks. Chat answers stream as server-sent events (SSE). |
| Queues | `php artisan horizon` | Ingestion, channel replies, analytics, exports, embeddings, and live voice calls (one worker per call). Several named queues (see `config/horizon.php`). |
| WebSockets | `php artisan reverb:start` | Live updates: ingestion progress, handoff messages, typing, eval progress. |
| Scheduler | `php artisan schedule:run` (cron, every minute) | Pruning, analysis, usage caches, backups (see `routes/console.php`). |
| Server stats | `php artisan pulse:check` | CPU, memory, disk and Reverb connections for Pulse's Servers card. |
| PostgreSQL 18 | | All data, plus vector search (pgvector HNSW) and keyword search (`pg_textsearch` BM25, or the built-in full-text search on databases that cannot preload it — `LexicalSearch` picks per install). The analytics tables, Pulse (`/pulse`) and Spectra (`/spectra`) each keep their data in a database of their own (the `analytics`, `pulse` and `spectra` connections, `DB_ANALYTICS_DATABASE`, `DB_PULSE_DATABASE` and `DB_SPECTRA_DATABASE`), which `chatvia:db:create-tool-databases` creates. |
| Redis | | Queues, cache, rate limits, the live chat memory, login sessions (their own database). |

### Repository map

| Path | What is there |
|---|---|
| `src/App/` | `App\` namespace: HTTP layer, framework wiring, accounts (`Identity`), built-in MCP servers, admin panel. |
| `src/Domain/` | `Domain\` namespace: business logic, one folder per bounded context. |
| `src/Support/` | `Support\` namespace: small helpers with no business rules. |
| `routes/` | One file per area (`agents.php`, `chat.php`, `widget.php`, `api.php`, `ai.php`, one per messaging channel, ...). |
| `config/` | Laravel config plus `chatvia.php` (product settings), `connectors.php` (the list of connector classes), `team-permissions.php`. |
| `database/` | Migrations, factories, seeders. |
| `resources/js/` | The Vue frontend (dashboard and hosted chat). |
| `resources/prompts/` | LLM prompt templates (Blade files, loaded with `prompt('folder/name')`). |
| `resources/views/` | Blade views: e-mails, PDF exports, the app shell. |
| `resources/types/generated.d.ts` | TypeScript types generated from PHP classes. |
| `lang/` | Server-side translations (e-mails, flash messages, validation). |
| `tests/` | Pest tests: `Unit`, `Feature`, `Architecture`, `Eval`. |

---

## 2. Three root namespaces

`composer.json` maps three root namespaces:

| Namespace | Folder | What goes there |
|---|---|---|
| `App\` | `src/App/` | Everything that knows about HTTP or the framework: controllers, form requests, middleware, responses, providers, console commands, the admin panel, the built-in MCP servers and the accounts (`App\Identity`). |
| `Domain\` | `src/Domain/` | The business logic: Actions, DTOs, models, services, events, listeners, jobs, policies, enums, queries. One folder per bounded context. |
| `Support\` | `src/Support/` | Small, generic helpers that any layer may use: external ids, SSRF-safe URLs, signed webhooks, MIME types, UTF-8 text, encrypted casts, the Country and Language reference models, the TypeScript transformers. |

There is exactly one global helpers file: `src/Support/helpers.php`. Its
functions stay small and generic (temporary files, file type checks,
`template()`, `prompt()`). Do not add domain lookups, authorization or service
locators there; use constructor injection instead.

### Dependency rules

```
        App ─────────► Domain ─────────► Support
         │               │                  ▲
         │               └─► App\Identity   │
         └──────────────────────────────────┘
```

- **Domain never imports from App**, with one exception: `App\Identity`
  (see below).
- **Support never references Domain or App**, not even in a type hint.
- **Integration never depends on Conversation.** Conversation runs chat turns
  and calls tools through `Domain\Integration\Tool`; the reverse edge would
  make a cycle. A tool that needs conversation data receives it as a value
  (`ToolCallData`).
- **Domain does not read the HTTP request.** Two files are the sanctioned
  bridges that turn a request into plain values:
  `Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory`
  and `Domain\Widget\Services\WidgetVerification`.

### Which tests enforce what

| Test | What it checks |
|---|---|
| `tests/Architecture/ConventionsTest.php` | Domain does not use `Illuminate\Http\Request` (except the two bridges); Domain uses App only through `App\Identity`; Integration does not use Conversation; Support has no code reference to Domain or App (these read the code, not comments, so a fully-qualified name counts); Actions (in every layer, the admin panel's too) use `execute()` (never `handle()`), never return `redirect`, never use the `Auth` facade, never send notifications; Jobs have no transactions or row locks; listeners have no `Listener` suffix; Domain models have no `...Request` names; controllers never call `$request->validate()`; `external_id` lookups of conversations and sessions, on one line or split over several, go through `WidgetVerification` (an allowlist names the others and why each is safe); form requests use `WorkspaceRule` for `exists`/`unique` (shrink-only allowlist for install-wide values); every listener is passed to `Event::listen()`/`Event::subscribe()` in a service provider; every test file starts with a docblock, and tests use `it()`, never `test('…')`. |
| `tests/Architecture/ActionsHaveTestsTest.php` | Every Action is named in the code of a test file (not a comment or a `use` line, not a helper in `tests/Pest.php` or `tests/Support/`), or is on a shrink-only allowlist. Prints the coverage. |
| `tests/Architecture/ClassesHaveDocblocksTest.php` | Every class, interface, trait and enum under `src/` has a docblock with at least one sentence of prose, or its file is on a shrink-only allowlist. Prints the coverage. |
| `tests/Architecture/FrontendConventionsTest.php` | Frontend rules: no `hsl(var(--x))`, no third-party icon CDN or Google Fonts, no `console.log`, `safeStorage` on the boot and hosted-chat paths, one shared Echo connection, `AlertDialog` for delete confirmations. |
| `tests/Architecture/Psr4PathCaseTest.php` | Every class file git tracks under `src/` has the exact case of its namespace and class name, as Linux autoloading needs (macOS forgives a wrong case). |
| `tests/Architecture/TestLayoutTest.php` | A test named after a class sits at that class's path with `src/` dropped: `src/Domain/X/Y.php` → `tests/Unit/Domain/X/YTest.php`, a controller `src/App/Http/Controllers/…/XController.php` → `tests/Feature/App/Http/Controllers/…/XControllerTest.php`. `tests/Unit` holds only `App`, `Domain`, `Support`, `Config` and `Database`; `tests/Feature` only `App`, `Bootstrap`, `Database` and `Routes`. Every feature test sends a request or runs a command (directly or through a helper in `tests/Pest.php` or `tests/Support`), and no unit test does. |

The Architecture suite runs without the Laravel container or a database, so it
takes about a second: `vendor/bin/pest tests/Architecture`.

---

## 3. The App layer

### `App\Identity`: the accounts carve-out

Accounts are tied closely to the framework: Fortify (sign-in, two-factor),
Sanctum (API tokens), Socialite (social sign-in), sessions and guards. So
`App\Identity` keeps its Actions, DTOs, models, services, events, listeners,
notifications and exceptions under `App\`, and Domain code may import it.

| Sub-namespace | Holds |
|---|---|
| `App\Identity\Workspace` | The `Workspace` model (the tenant) and its `workspace_user` membership (`WorkspaceMembership`, `WorkspaceRole`), the `User` model (staff accounts), sign-in flows, sessions and known devices, social accounts, data export, account deletion, monthly message limits (`MessageLimit`, `MessageAllowance`, `Actions/MessageLimits`), `AgentAccessPolicy`, `TeamPermissionPolicy`, widget sign-in tokens and widget session tokens. |
| `App\Identity\Chatter` | The `Chatter` model and chatter accounts: create, update, delete, password login on the hosted chat, password reset (own tokens), chatter auth tokens. |
| `App\Identity\ApiKey` | Creating and deleting API keys for the REST API. |

The tenant is a row in `workspaces`. Staff accounts are rows in `users`, and
each belongs to exactly one workspace through `workspace_user`, with the role
`owner` (one per workspace) or `member` (a teammate). Read it with
`$user->workspace()`, `isWorkspaceOwner()` and `isTeammate()`. The
`users.is_admin` flag marks platform staff, who may also open the admin panel;
on the dashboard and the API an admin is only the owner of their own
workspace. A workspace's end-users
are **chatters**, in their own `chatters` table: each belongs to one
workspace (`workspace_id`), and the email is unique per workspace,
so the same person can be a chatter of several workspaces and a staff account
at the same time. Chatters reach agents through the `agent_chatter` pivot.
Their ids and staff ids come from different tables, so anything that stores a
"who is chatting" id keeps the kind too: `chat_sessions` has `user_id` (staff)
and `chatter_id`; caches and tokens use `ChatViewerData` (`chatter:5` /
`user:5`).

#### Message limits

Every workspace gets every feature; there are no feature flags. The only usage
cap is an optional **monthly message limit**, one `message_limits` row per
limited account (no row means no limit):

- On a **workspace**, it counts every agent reply of the workspace: web chat,
  widget, hosted chat and messaging channels (the
  `CountReplyAgainstMessageLimit` listener on `AssistantReplyCompleted`, so a
  guardrail refusal is not counted), and each eval case that produced an
  answer (`ScoreEvalCaseAction`). A platform admin sets it in the admin panel
  (**Workspaces** → a workspace → **Set message limit**). A new workspace
  starts with `chatvia.message_limits.new_workspace` (admin panel, **Settings
  → Workspaces**; 0 = no limit).
- On a **chatter**, it counts the chatter's own messages
  (`StoreUserMessageAction`). The workspace sets it in the dashboard
  (**Chatters** → a chatter → **Limits**).

`MessageAllowance::canSend()` is the per-turn check (cached per account and
month). `ChatStreamService` ends a refused turn with the SSE error
`message_limit_reached` or `chatter_message_limit_reached`;
`ChannelReplyRunner` replies with a short "unavailable" message; an eval run
does not start, and a running one skips its remaining cases.
`RecordMessageUsageAction` counts one message and restarts the count when a
new calendar month has begun, so no scheduled job is needed.
`SetMessageLimitAction` sets or clears a limit. Messages are counted, never
tokens.

#### Deactivated workspaces

A workspace is switched off through its owner's account
(`DeactivateAccountAction`, also run when a deletion starts). Everyone in it is
signed out, and its agents stop answering. `WorkspaceActivation::isActive()` is
the check (cached per workspace; Deactivate and Reactivate drop it). The hosted
chat shows the "agent not found" page, the widget and Command Bar do not appear
(their settings report both off) and their chat calls get a 404
`widget_disabled`. `ChatStreamService` ends the turn with an error,
`ChannelAccessGate` and `ChannelReplyRunner` reply with a short "unavailable"
message, and `VoiceTurnGate` refuses a new call and ends an open one at the
visitor's next turn.

### `App\Http`

| Folder | Holds |
|---|---|
| `Controllers/{Area}/` | Thin controllers, grouped by screen area (`Agent`, `Chat`, `Widget`, `Api/V1`, `Settings`, `Webhooks`, ...). |
| `Requests/{Area}/` | Form requests with `rules()`, `authorize()` and `toData()`. Database rules go through `Rules/WorkspaceRule`. |
| `Middleware/{Access,Auth,Chat,Web,Widget}/` | Access checks (`workspace.scope`, `can.access.agent`, `agent.permission:…`, `team.permission:…`), authentication, hosted-chat resolution, web concerns (CSP, locale, Inertia), widget origin and access checks. Aliases are registered in `bootstrap/app.php`. |
| `Resources/` | Response DTOs for the REST API (`Api/V1/...Data`) and handoff endpoints. |
| `Responses/SseResponder.php` | The one place that owns the SSE transport: headers, flushing, sequence numbers, client disconnects. The domain only yields `StreamEvent` objects. |
| `Rules/` | Validation rules that need the request or app state (PDF page limits, attachment limits, "an LLM that reads PDFs is set up"). |
| `Chat/` | Request-scoped resolvers for the hosted chat: `#[HostedChatAgent]` (the agent a request is for, from the `/chat/{agentSlug}` path), `HostedChatSession` (the visitor's chat session, kept in the browser session), `HostedChatPageProps`. |
| `Widget/` | `#[WidgetAgent]`: the agent a widget API call is for, from the `X-AGENT-ID` header. |
| `Auth/` | Sign-in helpers outside Fortify's pipeline (magic links, social sign-in, two-factor-aware sign-in). |
| `Macros/` | Request macros: `country()`. |

`#[HostedChatAgent] Agent $agent` and `#[WidgetAgent] Agent $agent` are
contextual attributes. Put one on a controller or form request parameter to
receive the agent. The lookup runs once per request and is shared by the
middleware, the form request and the controller. Middleware calls
`HostedChatAgent::from($request)` or `WidgetAgent::from($request)`.

The hosted chat lives at `/chat/{agentSlug}` (the agent's slug, or its
external id for the permanent and embed addresses). `ResolveHostedChatAgent`
(alias `chat.agent`) finds the agent first, fills `agentSlug` into
`URL::defaults()` and drops it from the route parameters, so controllers keep
their positional `{conversationId}`. The chat runs on its own session and sign-in:
`UseHostedChatSession` (global middleware) gives `/chat/*` requests their own
cookie (`session.chat`, path `/chat`; table `chat_web_sessions` when an install
keeps sessions in the database instead of Redis) and the `chat`
guard (chatters). Staff who open a chat from the dashboard sign in on the
`chat_staff` guard instead; `UseSignedInChatGuard` makes whichever guard holds
the viewer the default, and `HostedChatViewer` reads, signs in and signs out
the viewer. Chat code uses the default guard, never `guard('web')`. Sign-ins that happen outside `/chat`
(magic links with two-factor, invitations, dashboard previews) reach the chat
with a one-time, agent-bound `?auth_token=` (`ChatterAuthTokenService`). An
agent's custom domain redirects to its chat (`RedirectCustomDomainToChat`).

### `App\Mcp`: built-in MCP servers

ChatVia.ai offers some tools of its own (charts, inline forms, a booking
calendar, weather, currency, web search, account analytics) as Laravel MCP
servers in `App\Mcp\Servers`, with their tools in
`App\Mcp\Tools\{Analytics,Calendar,Charts,Currency,Forms,Perplexity,Weather}`.
They are routed in `routes/ai.php` under `/mcp/*` and added to agents like any
other connector (a class in `Domain\Integration\Connector\Catalog\Connectors`,
listed in `config/connectors.php`).

- They are transport adapters, like controllers: validate the input, call a
  Domain Action, query or service, and map the result to the MCP response.
- `VerifyInternalMcpRequest` accepts only calls signed by ChatVia.ai itself
  (`BUILTIN_MCP_SECRET`) and resolves the caller once into an
  `McpCallerContext`, which tools receive by injection. Tools never read the
  `X-Chatvia-*` headers themselves.
- A tool's name, description, input schema and output are a contract with the
  LLM and the chat UI. Snapshot tests in `tests/Feature/App/Mcp/` and `tests/Unit/App/Mcp/` pin them. Tool
  names come from the class name and are stored in the database, so never
  rename a tool class.
- After you add or change a built-in tool, run `php artisan chatvia:mcp:sync-builtin`.
  It writes the tools into the shared catalog and embeds them for tool search.

### No public website

ChatVia.ai has no landing, pricing, blog or legal pages. `/` (route `home`,
`HomeController`) sends guests to the login form and signed-in users to their
dashboard.

### Other App folders

| Folder | Holds |
|---|---|
| `App\Admin` | The Filament admin panel at `/admin`. It signs in on its own `admin` guard; two-factor authentication is optional (an admin turns it on from the profile). Its Settings (credentials and install-wide options) are spatie/laravel-settings classes in `App\Admin\Settings`, edited on the pages of `App\Admin\Clusters\Settings`. Its dashboard (`App\Admin\Pages\Dashboard`, widgets in `App\Admin\Widgets`) shows activity across every workspace, filtered by period and workspace, read through `Domain\Analytics\Queries\Platform`. Its Logs page (saade/filament-laravel-log, registered in `AdminPanelProvider`) reads and clears the files in `storage/logs`, for the same staff as Horizon, Pulse and Spectra (`StaffToolsAccess`). Settings → Platform turns the recording of Pulse and Spectra on and off: both read their own `enabled` flag only while they boot, so `ToolRecording` (run after every settings apply) stops Pulse's recording and sets `spectra.enabled`, and `PULSE_ENABLED` / `SPECTRA_ENABLED` in `.env` stay the switch that leaves a tool out of the install. |
| `App\Console\Commands` | Artisan commands. Keep `handle()` small: read the options, call an Action, print the result. |
| `App\Providers` | Service providers. `DomainServiceProvider` binds contracts to drivers, registers observers and **every event listener**. `AppServiceProvider` holds framework settings (password policy, morph map, model strictness). Others: rate limiters, gates, filesystems, API docs, Horizon, Pulse, Spectra (its gate, and Spectra's Guzzle handler for the streamed replies), TypeScript transformer. Every AI call carries a tag in Spectra that says what it is for (`Domain\Llm\Enums\AiCallTag`, applied with `AiCallTags::during()`). A non-streaming LLM call cannot be built without one, because `ChatRequestData` requires a tag. A new kind of call needs a new case, and the values are never renamed. |
| `App\Rules` | Generic validation rules (for example `IpOrCidr`). |

---

## 4. Domain bounded contexts

Each folder under `src/Domain/` is one bounded context. A context that holds
several subdomains groups them in sub-namespaces (a "macro-namespace"), so the
folders match the mental model. New code goes inside the right context, not
next to it.

### Agent

The `Agent` model and everything about configuring one: settings
(`ModelSetting`), conversation starters, the agent-to-source pivot
(`AgentSource`), appearance texts and their translations, URLs and chat slugs,
visibility. `Agent\Prompt` is the prompt library (`AgentPrompt`), reusable
prompts a workspace attaches to many agents.

Main parts: `Actions/{Account,Analytics,ChatLog,Settings,Sources}`, `Prompt`,
`Services` (`AgentUrlBuilder`, `AgentSlugGenerator`, `AppearanceTextResolver`),
`ValueObjects/TranslatableText`.

Deleting an agent is quick for the person who asks: the agent is soft-deleted
(it disappears from every list, route and chat at once, and its slug and
domain are freed), and `PurgeAgentJob` then removes its chats, leads, sources
only it used and files in small batches before the final delete.

### Conversation

The chat engine: it runs one turn (a visitor message and the agent's reply)
for every surface, and keeps the chat log.

- **Models**: `Conversation`, `Message`, `Session`, `Attachment`,
  `ToolApprovalCheckpoint`, `AgentActivity` (the tool-call audit row).
- **`Services\ChatStreamService`**: decides how a web turn is answered
  (replay, human handoff, the send gate, then guardrail refusal, Q&A match,
  PDF chat or the LLM) and yields the events.
- **`Services\ChatService` and `Services\Turn\`**: prepare a turn the same way
  on every surface: `TurnContextFactory`, `KnowledgeEnricher`,
  `SystemPromptBuilder`, then `AssistantReplyRecorder` at the end.
  `Services\ChatContext` is the working state of one turn while it streams.
- **`Services\Streaming\`**: the answer sources (`LlmSource`, `PdfSource`,
  `QaSource`, `HandoffSource`, `GuardBlockSource`, `ReplaySource`), the parser
  for OpenAI-compatible streams, the SSE event types, request de-duplication.
  A reasoning model's thinking (`delta.reasoning_content`, `delta.reasoning`,
  OpenRouter's `reasoning_details`, or a leading inline `<think>` block) never
  joins the answer: `OpenAiCompatibleTransformer` sends it as `reasoning`
  events when `ChatContext::$showsReasoning` (a surface that shows it, an
  effort other than Off, answers not held for the guardrails) and drops it
  otherwise. It is never stored or replayed.
- **`Services\Tooling\`**: `ToolLoopRunner` (stream, run tool calls, repeat),
  the approval store and per-turn tool selection.
- **`Services\Memory\`**: `ChatMemory`, the live chat history in the cache
  (encrypted). The database copy is the chat log.
- **`Services\Voice\`**: voice calls (see below).
- **`Topic`**: conversation topics (`Topic`, `ConversationTopic`).
- Actions by feature: `Conversations`, `Messages`, `Sessions`, `Handoff`,
  `Attachments`, `Activity`, `Removal`, `Retention`, `Tools`, `Voice`.

#### Inbox

The dashboard's Inbox (`/my/inbox`, `App\Http\Controllers\Inbox`) lists live
chats across every agent a staff member may view: the visitor was active in
the last few minutes (`ConversationQueryBuilder::ONLINE_WINDOW_MINUTES`), the
chat waits for a person, or this person holds it
(`Queries\InboxConversationsQuery`). `Services\Inbox\InboxAgents` picks the
agents with the Chat Log's own rules (`agent.chat.view` to read,
`agent.chat.takeover` and the agent's handoff switch to join). The Inbox adds
no write routes: the page calls each chat's own `agents.logs.messages` and
`agents.handoff.*` routes, so their middleware still guards every action. The
list polls; the open chat is live on its `presence-chat.{id}` channel. The
header badge refreshes when the notification center hears about a handoff.

#### Voice calls

A visitor can talk with the agent out loud in the hosted chat and the chat
widget (the OpenAI Realtime API, speech to speech). The audio never passes
through ChatVia.ai:

1. The browser posts its WebRTC offer (`chat.voice.store` / `widget.voice.store`).
   `StartVoiceCallAction` checks the gates, takes a slot (`VoiceCallSlots`),
   and opens the call with the OpenAI key (`Llm\Contracts\RealtimeCalls`). The
   call starts with the agent's speaking instructions
   (`SystemPromptBuilder::voicePrompt()`), its voice, and its tools
   (`VoiceToolCatalog`: `search_knowledge` plus the tools that need no
   approval). Listening is off.
2. `JoinVoiceCallJob` (queue `voice`, its own Horizon supervisor) runs
   `RunVoiceCallAction`. It joins the call over OpenAI's server WebSocket
   ("sideband") and runs `VoiceCallSession` until the call ends, so one call
   holds one worker.
3. Once joined, the session turns listening on. The browser waits for that
   before it opens the microphone. For each spoken turn the session:
   - checks the message limits and the rate limit (`VoiceTurnGate`), then
     asks for the reply;
   - for an agent with knowledge, makes the reply start with a required
     `search_knowledge` call (the model writes the query, an empty one for
     small talk), as a typed turn always searches;
   - runs the tools (`VoiceToolRunner`, through the same access policy,
     dispatcher and audit as a typed turn). While a real search or tool
     runs, the agent says a short "let me check" (an out-of-band response,
     never saved), and the reply follows once it has been said. Each tool
     answer's item id carries how the call ended (`VoiceToolOutcome`:
     `tool_{done|failed|held|cancelled}_…`), and the start response names the
     tools (`VoiceCallStartedData::$tools`), so the call view shows the live
     transcript with a chip per tool;
   - saves both sides as messages (`VoiceTurnRecorder` →
     `AssistantReplyRecorder`). The reply counts against the limit and
     carries `metadata.voice`.
4. End (`chat.voice.destroy`), the time limit or a limit hangs up from the
   server.

The guardrails read what was written down. They can stop a reply and keep it
out of the history, but cannot un-hear audio. Admin settings live in
Settings → AI models → Voice conversations. The per-agent switch and voice
are in the `appearance` group (`allow_voice_conversation`, `voice`).

### Knowledge

The knowledge base behind the agents' answers (RAG).

| Sub-namespace | Holds |
|---|---|
| `Knowledge\Source` | The source types: `File` (`SourceFile`), `Web` (`SourceWebsite` and its pages, `AgentWebsiteUrl`), `Text` (`SourceText`), `Qa` (`AgentQuestionAnswer`), `Youtube` (behind `FEATURE_YOUTUBE_SOURCES`). Sources belong to the workspace and are attached to agents through `agent_sources`. |
| `Knowledge\Ingestion` | The pipeline from a source to searchable chunks: queued jobs per type, parsing (`DocumentParser`), splitting (`TextSplitter`), chunk enhancement, OCR, media. The `Chunk` model. |
| `Knowledge\Embedding` | Embedding providers (`Qwen3`, `OpenAi`, `OpenRouter`, `Gemini`, `Cohere`, `Mistral`, `Jina`, `Voyage`; OpenRouter's models listed by `OpenRouterEmbeddingModels`), rerankers (`CompatibleReranker` for any `/rerank` API, built by `RerankerFactory`; `NullReranker`), the reranking model list with per-model thresholds (`RerankModelCatalog`) and `RelevanceThresholds`. The re-index after a change of the embedding model (`StartEmbeddingReindexAction`: one job batch that embeds the stored chunk text and Q&A questions again, then rebuilds partitioned vector indexes; progress in `EmbeddingReindexStatus`). |
| `Knowledge\Retrieval` | Search: `HybridChunkSearch` (vector + keyword in one SQL query), `LexicalSearch` (the keyword driver: pg_textsearch or built-in full-text search), `KnowledgeBase` (embed, search, relevance check, context budget), `SourceLabeler` (names each chunk's source), retrieval stats. |
| `Knowledge\Document` | File conversion tools (`OfficeText` (office files; the built-in readers in `Conversion\Markdown` for RTF, DOC and PPT, and for DOCX and ODT when pandoc is missing), `EbookText` (EPUB and FB2 as Markdown; the built-in readers when pandoc is missing, and always for FB2), `OfficePdf`, `Pandoc`, `CatDoc` (only a backup for DOC and PPT), `PdfInfo`, and `BuiltinPdfInspector` + `PdfEncryption` for a server without pdfinfo), `ConversionPrograms` (which program a format needs, and whether it is missing; no format needs one now) and `HasStorageFile` for models whose file is in storage. |

### Integration

Tools that an agent can call.

| Sub-namespace | Holds |
|---|---|
| `Integration\Tool` | The shared tool layer: the registry of an agent's tools, routing and the relevance gate, hybrid tool search, dispatch, access, approval and rate-limit policies, the `ToolName` value object (`mcp__{server}__{tool}`), embedding jobs. |
| `Integration\Function` | Webhook tools (`AgentFunction` model), `HttpToolDispatcher`, `FunctionSchemaValidator`. |
| `Integration\Mcp` | MCP servers and their tools (`AgentMcpServer`, `McpTool`, the shared `ConnectorTool` catalog), the `McpClient`, per-tool permissions. |
| `Integration\Connector` | Catalog connectors that sign in with OAuth: the OAuth flow (PKCE, token refresh), catalog sync, per-workspace OAuth clients (for example Salesforce). |
| `Integration\Builtin` | Clients for the outside services behind ChatVia.ai's built-in tools: weather, currency, Perplexity web search. |

### Llm

Talking to language models. An admin enters each provider's key once
(Settings → AI providers): an `AiProvider` account (Scaleway AI, OpenRouter,
OpenAI, Mistral, Google Gemini, Groq, Anthropic, Cohere, Jina, Voyage AI,
Azure Document Intelligence), whose `capabilities()` say what it does here (chat,
embeddings, speech, documents, reranking). The key serves everything the
provider does. Chat models are added in Settings → AI models to the catalog
(`Llm`, `llms`: one model on one provider, named as the provider names it;
agents, settings, messages and analytics refer to it by its id). Their provider row (`LlmProvider`,
`llm_providers`) holds no key or address: it calls with its account's. Only
a server of your own (`custom`, several allowed) keeps its address, key and
headers on its row; a CHECK constraint keeps it that way. Each kind has one
adapter (`Providers\Contracts\ProviderAdapter`, in `Providers\Adapters`,
picked by `LlmProviderEnum::adapter()` through the `ProviderAdapters`
registry) that owns everything about it: label, address, key (`apiKey()`,
from its `account()`), chat client, and how its model list is read (with
what it knows about models the list says little about).
Nothing outside an adapter branches on the kind; adding a provider is an enum
case and an adapter class. Every call goes to its model's provider:
`LlmProviders::for($model)` asks the adapter for the client (`Scaleway`,
`Mistral`, `OpenRouter`, `OpenAi`, or `OpenAiCompatible`; all implement
`LlmProviderInterface`). Model facts come from the models.dev catalog
(`ModelsDevCatalog`: fetched when it changed, at most daily, never during a
chat): each added model keeps its entry in `llms.capabilities` (whether it
reasons, its `reasoning_options` effort values, whether it takes a
temperature, `tool_call`, `structured_output`, its `limit.output`), filled by
`AddLlmModelAction` and the daily
`chatvia:llm:sync-facts` (`SyncLlmFactsAction`, also run on the setup's first
page and by "Refresh model details"). Nothing is guessed from a model's name.
Reasoning: the admin lets agents reason (`chatvia.ai.reasoning.enabled`, off
by default) and `Llm::reasons()` (`capabilities.reasoning`: the catalog's
for a model it lists, the admin's **Reasons** switch for one it does not)
says which models agents may make think.
Each agent picks a `ReasoningEffort` (the `reasoning` settings group);
`Agent::reasoningEffort()` is Off unless both allow it. `Llm::requestSettings()`
turns it into a value the model takes (`effortValue()`: Off is its least,
`none` where it has it), drops sampling (`temperature`, `top_p`, `top_k`,
penalties) for a model that takes no temperature, caps `max_tokens` /
`max_completion_tokens` at `limit.output`, and drops `tools` / `tool_choice`
/ `parallel_tool_calls` for a model whose `tool_call` is false (the tool loop
and evals select no tools for it: `Llm::callsTools()`); the tool loop, the
app's own tasks (`ChatRequestData`), evals and the analytics chat all send
that. `ChatRequestData` sends `response_format` only when
`Llm::takesResponseSchema()`; otherwise the schema goes into the system
prompt (`ResponseSchemaData::instruction()`) and `LlmJson` reads the reply. Providers only move the value into
their own field (OpenRouter's `reasoning` object); a model the catalog does
not list gets no effort unless an agent asks for one. A provider
with a Responses API streams chat turns through it with the
`Providers\Responses\StreamsThroughResponses` trait (today only `OpenAi`,
whose Chat Completions API never sends the thinking): `ResponsesPayload`
rewrites the request (full history each time, nothing stored, a summary of
the thinking asked for when the turn shows it, `SHOW_REASONING`, and dropped
if the provider refuses it) and `ResponsesStream` reads the reply back as a Chat Completions
stream, so the transformer and the tool loop see no difference. Single calls
(`completion()`) stay on Chat Completions. `Anthropic` speaks only its own
Messages API, for streamed turns and single calls alike, through the same
kind of translation (`Providers\Messages`: `MessagesPayload` for the request,
system messages lifted out, tool calls and results as blocks, the effort as
adaptive thinking, prompt caching on; `MessagesStream` and `MessagesReply`
for the reply). A model from before adaptive thinking is asked again on a
thinking budget. The app's own calls use a model per `LlmTask` (one
case per call: follow-up rewriting, tool picking, guardrails, titles, image
descriptions…), picked in Settings → AI models and resolved by `TaskModels`:
the task's model, else the model for quick tasks (`chatvia.ai.tasks.default`),
else the chat model (`LlmTask::Chat`, the default for new agents). Each task
needs a large or a small model (`LlmTask::tier()`, `ModelTier`). Each provider
recommends chat models (`AiProvider::presetChatModels()`, one slot per model
maker), a small model for quick tasks (`presetQuickTaskModels()`) and a
model that reads images for image descriptions (`presetVisionModels()`;
without one, `chatvia.ai.chunker.describe_images` is turned off and
`PageVisualsDescriber` skips the pictures);
`ModelPresets` picks the first of each the provider lists now, and the setup
and "Use the recommended models" write them into the settings
(`TaskModelPreset`), so an update never changes an install's models.
Callers use typed helpers: `LlmChat` (one completion), `StructuredCompletion`
and `LlmJson` (JSON answers), `ChatFileModel` (the model for files a chatter
attaches) and `SourceSummaryModel` (the model that reads a whole source as a
PDF file, for its summary and analysis).

Main parts: `Operations` (summaries, tables, vision, speech to text, titles,
query rewriting, starters, translation, cost), `Usage` (`TokenUsageContext`
attributes each AI request to a workspace in the AI request log).

### Channel

Messaging apps: `Slack`, `Teams`, `Telegram`, `Whatsapp`, `Messenger`,
`Instagram`, one folder each (webhook Actions, jobs, models, API client,
formatter, adapter). Every channel implements the `ChannelAdapter` contract,
and `HandleChannelMessageAction` + `ChannelReplyRunner` run the same chat
pipeline as the web chat. A channel either shows a typing indicator
(`SendsTypingIndicator`) or edits a message as the reply grows
(`StreamsReplies`). Channels answer for public agents only
(`ChannelAccessGate`).

### Membership

Access grants.

- `Membership\Agent`: which chatters may use which agent (the `agent_chatter`
  pivot), chatter invitations (`AgentChatterInvitation`).
- `Membership\Team`: teams, roles and permissions for teammates (`Team`,
  `Role`, `Permission` on spatie/laravel-permission, the `team_user` pivot),
  team invitations. The permission codes live in `config/team-permissions.php`.

### Analytics

Event recording (`analytics_events` and rollups), dashboard and per-agent
statistics, conversation analysis (topics, sentiment, tone, with an LLM), IP to
country, user-agent parsing, and the query classes behind the account-analytics
MCP server. The tables live in a database of their own (the `analytics`
connection, `DB_ANALYTICS_DATABASE`), so no query joins them to the app's
tables: names are looked up on the main database afterwards. All access goes
through `AnalyticsDatabase`.

The tables, by how they are read:

- `analytics_agent_hourly_rollups` (one row per agent and hour) and
  `analytics_agent_daily` (per agent and day) hold the same counters,
  including a 24-bucket reply-time histogram (`ResponseTimeHistogram`) and the
  slowest reply. `RollupGrain` sums ranges up to 92 days from the hourly
  table and longer ones (and lifetime totals) from the daily one, so no read
  grows with history. Percentiles come from the summed histograms.
- `analytics_agent_country_daily` counts conversation starts by country, and
  the `*_daily` tables count sentiment, tone, topics, sources, functions and
  MCP tools.
- `analytics_conversation_metrics` (per conversation, with its channel) and
  `analytics_session_firsts` (per visitor session, with its device) hold the
  per-conversation and per-visitor numbers.
- `analytics_message_feedback` holds each rated message's current reaction.
- `analytics_events` is the append-only log of messages, read only for
  per-chatter numbers and active sessions. It is partitioned by month on
  `occurred_at`; with pg_partman (in the Docker image) the daily
  `chatvia:prune:analytics-events` drops expired months and makes the coming
  ones, and without it the log has only a default partition and old rows go
  in batches.

`RecordAnalytics` writes them all from queued events, on the `records` queue
(quick database writes) apart from the slow AI analysis on `analytics`.

### Evaluation

Test sets for agents: cases (`EvalCase`), runs (`EvalRun`) and results
(`EvalResult`). `EvalAgentRunner` runs each case through the agent's real
pipeline, but tools are only recorded, never called, and nothing is saved to
the chat log. Scorers (exact match, contains, regex, JSON schema, tool call,
LLM judge) grade the answers.

### Guardrail

Checks on the visitor's message and on the agent's answer: personal data (PII)
is found with regex filters (plus the owner's own patterns) and masked or, if
the owner chose, the message is blocked (the business contacts and the agent's
"never mask" list excepted); prompt injection, off-topic and toxic content are
judged by one classifier call, which also sees the earlier messages and names
the language for the refusal. That call and the follow-up rewrite go out at
the same time (`inspectInputAsync()`, `QueryRewriter::rewriteAsync()` on the
masked text, both through `LlmChat::sendAsync()` on one shared HTTP handler,
`SharedHttpHandler`); a blocked message's rewrite is cancelled and never used.
Each guard blocks or only logs, with a
sensitivity for injection and toxicity. Knowledge chunks and tool results pass
a rules check for injection (`InjectionPatternDetector`); answers are checked
for topics, toxicity and a leaked system prompt (a marker the
`SystemPromptBuilder` puts first). A blocked topic can refuse, reply or hand
the chat to the team. Every decision is logged as a `GuardrailEvent`,
including a check that was skipped because the classifier failed; attacks
also go to the security audit log, and the owner can get alert emails.

### Notification

The dashboard's notification center: a bell with an unread badge in both
dashboard headers, a popover, a full page (`my/notifications`) and per-person
settings (`my/account/notifications`).

- **Storage.** `UserNotification` (`user_notifications`) is one person's
  inbox entry, with a foreign key to the user (deleting the account deletes
  it). It is only ever read through `$user->userNotifications()`, so another
  person's id is a 404. A row holds a `NotificationKind`, its
  `NotificationCategory`, and `NotificationContentData` (agent, a name, a
  code, who did it, the dashboard path): never the text, and never personal
  data such as lead answers or messages. The frontend writes the sentence by
  kind in the reader's language.
- **Sending.** Each context owns its notification classes, which extend
  `InboxNotification`. A hand-registered, queued listener picks the people
  with `NotificationRecipients` (workspace members whose team role holds a
  permission, on the agent when there is one) and calls
  `Notification::send`. `InboxNotification::via()` adds `InboxChannel` only
  when the person keeps the bell on for that category.
- **Gathering.** A notification with a group key (for example
  `training:{agent}`) gathers into the person's unread row with that key: one
  `INSERT … ON CONFLICT` on a partial unique index raises its count and moves
  it to the top. Once it is read, the next one starts a new row.
- **Live.** `PushUserNotificationAction` broadcasts `UserNotificationPushed`
  (with `is_new`), the read actions broadcast `UserNotificationsRead` and
  "Clear all" (`ClearNotificationsAction`) broadcasts
  `UserNotificationsCleared`, on the person's private channel
  `App.Identity.Workspace.Models.User.{id}`.
  The frontend store (`composables/notifications`) updates the badge, the tab
  title and open lists. Desktop alerts and the chime play in one tab only
  (a Web Locks lock), for the categories the person switched on.
- **Retention.** `chatvia:prune:notifications` deletes rows with no activity for
  `chatvia.notifications.retention_days` (7 by default; admin Settings →
  Workspaces → Notifications), every night.

### Security

Workspace security settings and audit: IP allowlist and country rules
(`NetworkAccessGuard`), retention windows (conversations, audit log, leads,
analytics, and clearing visitor details from old chat sessions — each
enforced by a daily, batched job), the security audit log and delivery to a
SIEM webhook. Every workspace has the full security feature set.

### Form

Forms inside the chat. A `FormDefinition` is a form an agent can show; a
`FormSubmission` stores the answers and is the single store for captured
contacts (leads). The Forms MCP server finds and renders forms; a submission
fires `FormSubmitted`, which can push it to a CRM webhook. A form a capability
owns (`managed_by` holds its slug, e.g. Calendar's "Meeting bookings") is never rendered by
the model and is read-only for the owner.

### Booking

The Calendar capability: a booking calendar inside the chat. `Contracts\Scheduler`
lists meeting types, free times and books; `Services\CalendlyScheduler` and
`Services\CalcomScheduler` implement it with the tools of the workspace's Calendly
or Cal.com connection (`McpClient::callTool` through `McpScheduler`, so the
connector need not be attached to the agent). `Contracts\Schedulers`
(`SchedulerRouter`) picks one: the agent's choice in its `capabilities` settings
(`CapabilityProvider`, from `Connector::$worksThrough`), else the first connected;
a shown calendar keeps the scheduler it was shown from (`provider` in the
pending row). A shown calendar is a pending
`FormSubmission` of the agent's managed "Meeting bookings" form
(`EnsureBookingFormAction`), so claim, expiry, session checks and lead storage
are the Form context's; `BookMeetingAction` books it and fires `FormSubmitted`
with source `calendar`. The Calendar MCP server (`find-meeting-types`,
`render-calendar`) returns a `calendar` artifact; the chat books through
`chat/{agentSlug}/calendar/*` and `/widget/calendar/*`.

### Widget

The domain side of the embeddable widget's API: starting a widget session,
widget settings, the visitor's conversation list, broadcast authorization.
`WidgetHeaders` names the HTTP headers the widget sends (a wire contract), and
`WidgetVerification` checks that every id in a widget call belongs to the
agent.

---

## 5. How a request flows

### A state-changing request

```
HTTP request
  → route (routes/*.php) + middleware
  → Controller
      → FormRequest: rules() + authorize() → toData() → DTO
      → Action::execute(DTO, models...) → model | DTO | Collection | void
      → (optional) Job::dispatch(DTO) → the Job's handle() calls the same Action
  → Controller returns a response (Inertia page, JSON, redirect)
```

A real example, creating an agent:

```php
// src/App/Http/Controllers/Agent/AgentController.php
public function store(CreateAgentRequest $request, CreateAgentAction $createAgent): RedirectResponse
{
    $agent = $createAgent->execute($request->toData());

    return to_route('agents.setup', ['agent' => $agent])
        ->with('success', __('Agent created successfully.'));
}
```

- The **controller** only does HTTP work: read the form request, call the
  Action, choose the response, flash a message. No database writes, no
  outside HTTP calls, no event dispatch in the controller body.
- A controller is a **resource controller** (`index`, `create`, `store`,
  `show`, `edit`, `update`, `destroy`) or a **single-action controller**
  (`__invoke`), nothing else. An endpoint that is not one of the seven
  resource verbs gets its own invokable controller named after what it does
  (`ExportFormSubmissionsController`, `RewriteFormDescriptionController`).
  `ConventionsTest` fails on any other public method.
- The **form request** validates, authorizes and builds the DTO with
  `toData()`. Permission checks that are the same for a whole route group go
  on the route as middleware (`can.access.agent`, `agent.permission:…`,
  `team.permission:…`), not inside the controller.
- The **Action** does the work. It takes DTOs and models, never a `Request`,
  the `Auth` facade or the session.
- Rate limits are route middleware: `->middleware('throttle:N,M')` or a named
  limiter from `RateLimitServiceProvider`.

### A read-only page

A pure `index` or `show` that only queries and renders does not need an
Action. Keep the query in the controller, or in a query class or Eloquent
query builder. Add an Action only when the read has real logic worth testing
on its own (for example `GetDashboardStatsAction`).

### Jobs

Jobs hold no logic. A job receives a DTO (or models) in its constructor and
calls an Action in `handle()`. A database transaction or row lock in a job is a
sign that logic belongs in an Action; `ConventionsTest` fails on it.

### Events and listeners

Actions dispatch events for side effects that are not the core operation:
e-mails, notifications, broadcasts, CRM pushes, message-limit counts, analysis jobs.
Listeners do the side effect.

- Events are named in the past tense (`MessagePosted`, `FormSubmitted`).
- Listeners are named by what they do (`SendTeamInvitationEmail`,
  `CountReplyAgainstMessageLimit`).
- **There is no event auto-discovery.** Every listener is registered by hand
  in `DomainServiceProvider::registerEventListeners()`. A listener that is not
  registered never runs, even when its own unit test passes. `ConventionsTest`
  checks that every listener is registered. (The Socialite extension in
  `AppServiceProvider` is framework wiring, not a domain listener.)

### Broadcasts

Live updates go through Reverb. Existing broadcast names are a contract with
the frontend and are frozen: the conversation stream uses short lowercase names
(`message`, `typing`, `handoff`), the ingestion and eval events use their class
name (`FileIngestionProgressed`, `EvalRunProgressed`). A new broadcast event
returns its class basename from `broadcastAs()`, unless it joins the lowercase
conversation-stream family. A staff member's own channel,
`App.Identity.Workspace.Models.User.{id}`, carries the notification center's
`UserNotificationPushed`, `UserNotificationsRead` and
`UserNotificationsCleared`.

### A chat turn

A chat answer does not fit the normal flow, because it streams.

```
Hosted chat / widget stream controller
  → StreamRequestDataFactory   (HTTP request → StreamRequestData)
  → ChatStreamService::stream()  (replay? handoff? send gate? rate limit?)
      → ChatService             (TurnContextFactory → KnowledgeEnricher → SystemPromptBuilder)
      → a Streaming source       (GuardBlock | Qa | Pdf | Llm)
          → ToolLoopRunner      (stream, run allowed tool calls, repeat)
      → AssistantReplyRecorder  (memory, chat log, AssistantReplyCompleted → message limit)
  → SseResponder                (events → text/event-stream)
```

Messaging channels take the same path without SSE: the webhook controller
verifies that the request really comes from the channel, a `Receive…Action`
filters the event and queues it; the job calls `HandleChannelMessageAction`, and
`ChannelReplyRunner` runs the same gates and sources and returns the reply
text. A per-turn rule added to the web stream must also be added to the
channel runner.

---

## 6. Conventions

### Class kinds

| Kind | Location | Name | Example |
|---|---|---|---|
| Action | `{Context}/Actions/{Feature}/` | `{Verb}{Noun}Action` | `CreateAgentAction` |
| DTO | `{Context}/Data/{Feature}/` | `{Noun}Data` | `CreateAgentData` |
| Form request | `App/Http/Requests/{Area}/{Feature}/` | `{Verb}{Noun}Request` | `CreateAgentRequest` |
| Controller | `App/Http/Controllers/{Area}/` | `…Controller` | `AgentController` |
| Event | `{Context}/Events/` | past tense, no suffix | `MessagePosted` |
| Listener | `{Context}/Listeners/` | what it does, no suffix | `SendTeamInvitationEmail` |
| Job | `{Context}/Jobs/` | `…Job` | `DocumentIngestionJob` |
| Notification | `{Context}/Notifications/` | `…Notification` | `TeamInvitationNotification` |
| Policy | next to its model, `{Context}/Policies/` | `…Policy` | `AgentPolicy` |
| Exception | `{Context}/Exceptions/` | `…Exception` | `HandoffUnavailableException` |
| Service | `{Context}/Services/` | a noun, no suffix | `KnowledgeBase`, `McpClient` |
| Model | `{Context}/Models/` | singular noun | `Conversation` |
| Enum | `{Context}/Enums/` | a noun | `HandoffStatus` |
| Query | `{Context}/Queries/` | `…Query` (most) | `PendingHandoffsQuery` |
| Query builder | `{Context}/QueryBuilders/` | `{Model}QueryBuilder` | `ConversationQueryBuilder` |
| Contract | `{Context}/Contracts/` | a noun or capability | `ChannelAdapter`, `StreamsReplies` |

Domain models never have HTTP-sounding names: `ToolApprovalCheckpoint`, not
`ToolApprovalRequest`.

### Actions

- One public method: `execute(...)`.
- Accept DTOs and domain models. Never a `Request`, `Auth`, the session or any
  HTTP concern.
- Return a model, a DTO, a Collection or `void`. Never an HTTP response.
- Inject services through the constructor.
- Wrap multi-step writes in `DB::transaction(...)`.
- Dispatch events for side effects; never send a notification from an Action.
- A trivial write (one Eloquent call with no rule, transaction, event or
  reuse, like `$model->update([...])`) does not need an Action. An Action that
  only forwards one call adds ceremony, not value.

### Actions or services?

Ask: is this something the app **does**, or something the app **uses**?

- **Does** → an Action (`CreateAgentAction`, `SubmitFormAction`).
- **Uses** → a Service: an adapter for an outside system (LLM providers,
  embeddings, MCP client, pandoc) or a stateless utility (`Chunker`,
  `RetrievalScoring`). A service may have several methods that share
  configuration.

### DTOs and value objects

- DTOs extend `Spatie\LaravelData\Data` and end in `Data`. A form request
  builds one with `toData()`; tests, jobs and commands build them directly.
- Actions, services, queries and listeners exchange typed objects: models,
  Collections of models, DTOs or small `readonly` value objects. Avoid
  `array{...}` shapes and `array<string, mixed>` as domain results. Arrays are
  fine for framework contracts (`rules()`, `casts()`, `toArray()`), config and
  simple lists.
- More than about three scalar parameters, or any associative array
  parameter, becomes a DTO. A boolean flag that changes behaviour becomes two
  methods or an enum.
- Response shapes for Inertia pages and JSON are DTOs too, often under
  `Data/Resources/`. Mark them `#[TypeScript]` so the frontend gets their type.

### Enums

Enum-like strings are backed enums in `{Context}/Enums/`, cast on the model,
and turned into `->value` only at the edges (HTTP, JSON, queue payloads). Some
enum columns also have a Postgres `CHECK` constraint: a new case must be added
to the column's `enum()` list in its create migration.

### Feature subfolders

When a folder of Actions, DTOs, form requests or controllers grows past about
seven files, split it into feature subfolders, by subdomain or operation, not
by file kind. Examples: `Conversation/Actions/{Conversations,Messages,Sessions,Handoff,...}`,
`Knowledge/Ingestion/Actions/{Chunks,Documents,Files,...}`,
`App/Identity/Workspace/Actions/{Account,Sessions,Social,DataExport}`. Do not
create a one-file `Misc/` folder; propose a real feature name.

These folders stay flat whatever their size: `Models/`, `Policies/`,
`Observers/`, `Events/`, `Jobs/`, `Enums/`, `Exceptions/`, `Contracts/`,
`QueryBuilders/`, `database/factories/`, `database/seeders/`.

### Naming

- Context folders are singular: `Domain/Agent`, `Domain/Knowledge`,
  `App/Identity`. `Domain/Analytics` is the one exception.
- Acronyms use Pascal case: `Llm`, `Http`, `Mcp`, `Api`, `Oauth`.
- Inside `Membership\Agent`, chatter access uses "Chatter" and "Membership"
  words (`InviteAgentChatterAction`, `GrantAgentMembershipAction`).
  `App\Identity\Chatter` is about the chatter's *account*;
  `Membership\Agent` is about their *access* to agents.

### Names that are stored somewhere

Some class names and strings are saved in the database, in queued jobs or in
the frontend. Do not rename or move them without a migration plan:

- Eloquent models, queued job classes and queued listener classes (serialized
  jobs keep the class name). When one must move, keep a `@deprecated` subclass
  at the old name for one release.
- Morph-map aliases (`AppServiceProvider::enforceMorphMap()`). Polymorphic
  columns store the alias (`source_file`), never the class name.
- Broadcast names, route names used by the frontend, built-in MCP tool class
  names, `ToolKind` values and the `mcp__{server}__{tool}` tool name format.
- `AgentFunction` and `AgentMcpServer` keep their historical names for this
  reason; the glossary calls them FunctionTool and McpServer.

### Data ownership

Key workspace data to the **workspace** (`workspace_id`: `$agent->workspace_id`,
`$user->workspace()->id`), never to the person who is acting. Otherwise
removing a teammate would delete workspace data. Storage follows the same
rule: workspace files live under `workspaces/{external_id}/…` (an agent's
under `workspaces/{external_id}/agents/{external_id}/…`; a knowledge source,
which several agents can share, under
`workspaces/{external_id}/sources/{type}/{external_id}/…`, where `{type}` is
its morph alias such as `source_file`), a person's own files under
`users/{external_id}/…` and a chatter's photo under
`chatters/{external_id}/…`, so deleting an entity is one prefix wipe.

### Keeping workspaces apart

Scoping is explicit (no global scopes), with layers that catch a missed check:

- **Routes.** Every dashboard and API group has `workspace.scope`
  (`ScopeRequestToWorkspace`). A record the route binds from the URL that
  belongs to another workspace answers 404, the same as an id that does not
  exist, so a guessed id never confirms a record. `can.access.agent` does the
  same for the agent and for any record named next to it (it must be the
  agent's, or its workspace's for shared sources and tools). A 403 is only for
  a teammate whose role does not allow the action.
- **Hosted chat and widget.** A visitor's own records (conversations, tool
  approvals, form instances, attachments) are looked up within the visitor's
  session or signed-in account, and anyone else's answers 404. One 403 is on
  purpose: a protected agent's chat address is public, so a signed-in account
  without access to that agent gets "You do not have access to this agent",
  which tells them to sign in with another account and reveals nothing the
  address did not.
- **Validation.** Form requests use `WorkspaceRule::exists()` /
  `WorkspaceRule::unique()` (`App\Http\Rules`), so an id or a name of another
  workspace is treated as unknown. A `ConventionsTest` ratchet refuses a bare
  `exists:`/`unique:` rule outside a short list of install-wide values.
- **Database.** `chat_sessions`, `chat_conversations`, `chat_messages`,
  `form_submissions` and `agent_chatter` carry `workspace_id`, and each key to
  a parent pairs it with the workspace (`(workspace_id, agent_id)` →
  `agents(workspace_id, id)`, and so on). Postgres refuses a row that links
  two workspaces. Writers copy `workspace_id` from the parent they already
  hold.
- **Jobs.** The dashboard, API, hosted chat and widget put the workspace id in
  hidden `Context`, so queued jobs carry it.
- **Tests.** `tests/Feature/App/Http/CrossWorkspaceRouteTest.php` walks the route table with two
  workspaces and checks every agent and workspace route answers 404 for the
  other workspace's records, and that the database refuses cross-workspace
  rows.

### Database

- Until the first release, each table has one create migration that holds its
  whole schema. A schema change edits that migration; there are no
  `add_*`/`alter_*` or data migrations. From the first release on, migrations
  only move forward.
- Do not set `protected $table` when Laravel's default name is right.
- Pivot tables and pivot models have no timestamps.
- Embedding columns use `$table->embedding()`: the type (`halfvec` or
  `vector`) and the dimensions are chosen at install (`EmbeddingStorage`).
  Never write them out by hand.
- The chunk HNSW indexes are built by `ConfigureVectorIndexesAction` in the
  kind `EMBEDDING_INDEX` names (`binary`: `binary_quantize()` + Hamming, then
  re-scored by the stored vectors; `full`). Their SQL shape lives in
  `ChunkVectorIndex`, which `HybridChunkSearch` also reads: an indexed ORDER BY
  must repeat the index expression exactly.
- Staff emails are stored in lowercase (`users_email_lowercase` CHECK), so the
  plain unique index is also unique ignoring case. Look one up with
  `email = Str::lower($input)`. Chatters keep the address as typed, with a
  unique index on `(workspace_id, lower(email))`.
- Every query that can grow with the data has an index that serves it. Name
  that index when you add a filter or a sort. Use a partial index for a column
  that is mostly NULL and for soft-deleted tables, put `id` last when pages
  are keyed by `(time, id)`, and use BRIN for install-wide time ranges on
  tables that only grow.
- `chat_conversations` has many indexes, and `updated_at` and
  `last_activity_at` are keys in them, so every update of a row rewrites
  every index. Activity pings (a widget load, the heartbeat) write at most
  once a minute and through the base query, so `updated_at` stays as it is.
  Only a new message moves `updated_at`, which is the chat log's order.
- Large deletes run in batches (`… WHERE id IN (SELECT id … LIMIT n)` in a
  loop), outside the request's transaction. Never leave a cascade to an
  unbounded child table: delete the large children first, then the parent.
- Every table that grows without end has a daily prune in
  `routes/console.php`, and the column the prune filters on is indexed.

### Comments and docblocks

- Every class, interface, trait and enum has a short docblock that says what
  it is and why it exists (`ClassesHaveDocblocksTest`).
- Comments explain **why**, in plain English. No history ("we used to ..."),
  ticket numbers or people.

---

## 7. Glossary

Use these words in code, UI and docs. Do not invent synonyms.

| Concept | Term | In code |
|---|---|---|
| Tenant: owns agents, knowledge, tools and settings | **Workspace** | `App\Identity\Workspace\Models\Workspace` (`workspaces`) |
| A staff account's place in its workspace | **Workspace membership** | `WorkspaceMembership` (`workspace_user`, `WorkspaceRole::Owner\|Member`) |
| The staff account that owns a workspace | **Owner** | `User` with an `owner` membership |
| Dashboard user invited into a workspace | **Teammate** | `User` with a `member` membership; role in `team_user` |
| Platform staff | **Admin** | `User` with `is_admin`; admin panel only |
| End user who signs in to chat with an agent | **Chatter** | `App\Identity\Chatter\Models\Chatter` (`chatters`, email unique per workspace) |
| Anonymous person in the chat (not signed in) | **Visitor** | a `Session` without a `user_id` |
| A visitor's identity with one agent, across conversations | **Session** | `Domain\Conversation\Models\Session` (`chat_sessions`) |
| One persistent chat between an agent and a visitor or chatter | **Conversation** | `Domain\Conversation\Models\Conversation` |
| One message in a conversation | **Message** | `Domain\Conversation\Models\Message` (`MessageRole`) |
| Working state of one turn while it streams | **ChatContext** | `Domain\Conversation\Services\ChatContext` |
| The live chat history in the cache | **Chat memory** | `Domain\Conversation\Services\Memory\ChatMemory` |
| Hosted-chat visitor session for the current request | **HostedChatSession** | `App\Http\Chat\HostedChatSession` |
| Widget sign-in credential for a protected agent | **Widget session token** | `WidgetAuthSessionService`, sent as `X-AUTH-TOKEN` (not related to `Session`) |
| Chatter's access to an agent | **AgentMembership** | the `agent_chatter` pivot (`GrantAgentMembershipAction`) |
| Teammate's role in a team | **TeamMembership** | the `team_user` pivot with `role_id` |
| A group of agents and teammates | **Team** | `Domain\Membership\Team\Models\Team` |
| Training material attached to an agent | **Source** ("Knowledge base" in the UI) | `SourceFile`, `SourceWebsite`, `SourceText`, `AgentQuestionAnswer`, `SourceYoutubeVideo`; attached through `agent_sources` |
| One parsed unit of a source (a file's text, one web page) | **Document** | not a model: the source row (`SourceFile`, `AgentWebsiteUrl`) |
| A searchable, embedded piece of a source | **Chunk** | `Domain\Knowledge\Ingestion\Models\Chunk` |
| Anything the LLM can call | **Tool** | `Domain\Integration\Tool` (`ToolKind`: `mcp` or `function`) |
| A webhook tool | **FunctionTool** | `Domain\Integration\Function\Models\AgentFunction` |
| A connected MCP server | **McpServer** | `Domain\Integration\Mcp\Models\AgentMcpServer` |
| A tool of a custom MCP server | **McpTool** | `Domain\Integration\Mcp\Models\McpTool` |
| A tool of a catalog connector (shared catalog) | **ConnectorTool** | `Domain\Integration\Mcp\Models\ConnectorTool` |
| A catalog MCP integration that signs in with OAuth | **Connector** | a `Connector` class (listed in `config/connectors.php`) + an `AgentMcpServer` row |
| A turn paused until a person approves a tool call | **ToolApprovalCheckpoint** | `Domain\Conversation\Models\ToolApprovalCheckpoint` |
| A human operator taking over a conversation | **Handoff** | `HandoffStatus` on `Conversation`, `Actions/Handoff` |
| Contact details captured in a conversation | **Lead** | a `FormSubmission` (`Domain\Form\Models\FormSubmission`) |
| Where an agent answers | **Surface**: Hosted Chat, Chat Widget, Command Bar | values `hosted_chat`, `widget`, `bar` |
| A messaging app connection | **Channel** | `Domain\Channel\*` |
| A monthly cap on a workspace's agent replies or a chatter's messages | **MessageLimit** | `App\Identity\Workspace\Models\MessageLimit` (`message_limits`) |
| A live spoken conversation with an agent | **Voice call** | `Domain\Conversation\Services\Voice\*`, `VoiceCallData`; no table (its turns are messages) |

---

## 8. External services and installation switches

Outside services sit behind contracts. `DomainServiceProvider` picks the driver
from config, so the rest of the code never knows which service runs. Most steps
have an `auto` value: the first connected provider that can do it
(`AiProvider::firstConnectedFor()`), otherwise "off". A self-hosted install
can run without most accounts. Keys are on **Settings → AI providers**;
unless a row names another page, its setting is on **Settings → AI models**.

| Step | Contract | Drivers | Config key (admin setting) |
|---|---|---|---|
| Chat answers | `LlmProviderInterface`, per model (`LlmProviders`, from the kind's `ProviderAdapter`) | the model's provider row: `openrouter`, `openai`, `anthropic`, `google`, `mistral`, `groq`, `scaleway`, `custom` | `llm_providers`, `llms`; tasks `chatvia.agent.default_model`, `chatvia.ai.tasks.*` |
| Embeddings | `EmbeddingsProviderInterface` | `qwen`, `openai`, `openrouter`, `google`, `cohere`, `mistral`, `jina`, `voyage` (`EmbeddingProvider`; must return `EMBEDDING_DIMENSIONS`) | `chatvia.ai.models.embedding` |
| Reranking | `Reranker` | `openrouter`, `cohere`, `custom` (a self-hosted `/rerank` server), `none` (a basic similarity check instead) | `chatvia.ai.reranker`, `chatvia.ai.rerank.*` |
| Reading documents | `DocumentParser` (driver chosen by `DocumentReaders`) | `mistral` (Mistral OCR), `azure` (Azure Document Intelligence), `none` (then PDF, Word, slides and images cannot be uploaded; spreadsheets and CSV are read locally) | `chatvia.ai.document_parser` |
| Splitting text | `TextSplitter` | built in only (`BuiltinTextSplitter`, in-process) | `chatvia.ai.chunking.*`, `chatvia.ai.tokenizer` (Settings → Splitting) |
| Speech to text | `Transcriber` | `scaleway`, `openai`, `none` | `chatvia.ai.transcriber` |
| Voice calls | `RealtimeCalls` | `openai` (the Realtime API), `none` (the voice button is hidden) | `chatvia.ai.voice`, `services.openai.realtime_*`, `chatvia.voice.*` |
| Source summaries (whole PDFs) | `SourceSummaryModel` | any catalog model that reads PDF files (models.dev inputs); Automatic, or off | `chatvia.ai.source_summaries.*` |
| Files | Laravel disks `private`, `assets` | local or S3-compatible (`private`; given the `s3` disk by `StorageDisks`), Bunny CDN or local (`assets`) | `filesystems.storage` + `filesystems.disks.s3` (Settings → Storage), `BUNNYCDN_STORAGE_ZONE` (`.env` only) |

Everything an admin may want to change lives in the admin panel under
**Settings**, not in `.env`: API keys and OAuth apps, the AI services, models
and endpoints, e-mail, file storage, brand, default language, CSP, the
widget, retention and limits. `.env` keeps only what the app needs before it
can reach or decrypt the database: APP_KEY, DB, Redis, drivers, Reverb,
`VITE_*`, the embedding storage, the Bunny CDN `assets` disk, `BUILTIN_MCP_SECRET`
and `BUILTIN_MCP_INTERNAL_URL` (a secret and a network address of each
install), `TRUSTED_PROXIES`, and `CHATVIA_URI` (ChatVia.ai's own site,
chatvia.ai by default, the base of the `chatvia.license.*` URLs: where license
keys are sold and activated; set it only to test against a local copy of the
site). The license public key stays fixed in
`config/chatvia.php`: whoever could set it could sign their own keys. Program paths (pdfinfo, pandoc, ffmpeg,
WeasyPrint…) are in **Settings → Platform → Programs** (`ServerProgram`). The
panel accepts only a program's own name or a full path that ends in it and
leads to a program on the server, so a stolen admin login cannot make the
server run another command. Every program is optional, for hosts that cannot
install them: `Support\Executable` finds one at runtime; without pdfinfo the
`PdfInspector` binding is the PHP reader, without ffprobe getID3 measures
media, without ffmpeg `TranscriptionAudio` sends only what the speech-to-text
service takes as it is, a PDF maker other than WeasyPrint (Cloudflare,
Gotenberg; Settings → Platform → PDF maker) makes PDFs, and the upload refuses
a format whose program is missing (`UnreadableSourceFileException`). Some config/*.php keys
behind a setting still read `env()`, as an optional preset (Docker and Sail
use a few, for example `MAIL_HOST` for Mailpit); a value saved in the admin
panel always wins.

The default disk is `private`, so knowledge files, chat attachments (and
anything else stored without naming a disk) follow **Settings → Storage**:
this server or S3-compatible storage. Nothing on it has a public address:
chunk images and chat attachments open through signed app routes.

How it works:

- **Storage.** spatie/laravel-settings: one class per Settings page in
  `App\Admin\Settings` (listed in `config/settings.php`), one row per property
  in the `settings` table, created by the settings migrations in
  `database/settings`. Secrets carry `#[ShouldBeEncrypted]` and are encrypted
  with APP_KEY.
- **Reading.** Code reads `config()`, never a settings class (Domain may not
  import `App`, and Socialite or the mailer read config anyway). Each property
  names its config key with `#[ConfigKey]`. `SettingsConfig` copies the saved
  values into config in `AdminSettingsServiceProvider::boot()` (first app
  provider), again before every queued job, and after every save. A blank
  property keeps the config/*.php value, which `.env` may still preset.
- **Caching.** Settings are cached (secrets stay encrypted in the cache); a
  save updates the cache, `migrate` clears it.
- **Admin UI.** `Settings` opens on an overview of tiles (one per page,
  grouped, with an honest status). Each page (`AdminSettingsPage`) shows its
  cards (`SettingsCard`: a service or a group of options) as collapsible
  sections with an outline beside them and one Save button that stays in
  view. Everyday cards come first; technical ones are `advanced()`: listed
  last under an "Advanced" heading, closed. A card can also keep its own
  technical fields (an address, a rate) in a closed "Advanced" part
  (`advancedFields()`).
- **Setup.** A new install opens on the setup wizard (`App\Admin\Pages\Setup`,
  its steps listed on the left): first the server (the database, Redis,
  Horizon, the scheduler and Reverb, checked live with the Status page's
  `ServiceChecks`, and keyword search: pg_textsearch is recommended with
  why and how, and turned on there with `EnablePgTextsearchAction` when the
  database has it loaded; Next with a service down asks first), then AI
  provider and key, chat models and the
  model for quick tasks and the model for image descriptions (the
  provider's recommended ones picked), the
  embedding model and the reranker, document reader, website import (Firecrawl), speech to
  text, voice conversations (the OpenAI Realtime API, off by default),
  storage, mailer, and a final step with the choices made and where
  the defaults are changed. Each key is checked before the next step; the
  optional services start on "Not now", which turns them off. Every panel page leads there (`RequireSetup`) until
  `chatvia.admin.setup_status` (`SetupStatus`) is `completed` or `skipped`.
  Skipping warns with what is still missing (`SetupRequirements`); a skipped
  setup can be finished later from Settings.
- **Adding an option.** Add a nullable property with `#[ConfigKey]` to the
  right class, a line to its settings migration, and a field to its card
  (under Advanced when only a few admins need it). Give the config key a
  default in config/*.php, and give the field a short `help:` in plain
  words: it shows as the "?" tooltip next to the option's label. Explain
  it in the admin panel docs too, in `../chatvia-landing`
  (`docs/guide/admin-panel/settings/{page}.md`, under the card's title), and
  rewrite `SettingsDocsTest`'s fixture with `SETTINGS_DOCS_WRITE=1`.
- **Chunking.** Every text is split in-process by `BuiltinTextSplitter`
  (`Knowledge\Ingestion\Services\Chunking`); there is no outside service.
  The strategy follows the kind of content (`ContentKind`): Markdown (web
  pages, .md files, ebooks), Text (typed texts, .txt files) and Transcript
  (audio and video), each with a default that the admin can change under
  Advanced (`SplitStrategy`, `ChunkingConfigData::forKind()`). The chunk
  size and overlap are in one unit (`ChunkSizeUnit`): tokens of the
  tokenizer (`TokenMeasure`, which only cuts where a token starts a new
  character) or characters as a reader sees them (`CharacterMeasure`,
  grapheme clusters). `RecursiveSplitter` cuts at paragraphs, then lines,
  sentences (ICU, `SentenceSeparator`) and words, packs pieces up to the size
  with the overlap, and falls back to fixed windows; the `sentence` and
  `fixed` strategies use it with fewer separators. `MarkdownSplitter` (the
  Markdown default) splits by heading sections, keeps code blocks and tables
  whole when they fit (fences or header row repeated when they do not), and
  starts every chunk with the headings above it. Documents read page by page
  (PDF, office files, images) make one chunk per page; a page longer than the
  chunk size is split as Text, and every part keeps the page number
  (`PageData::partsOf()`). Token counts (`chunks.tokens`) use the built-in
  `TikToken` with the tokenizer set in Settings → Splitting
  (`chatvia.ai.tokenizer`).
- **Chunk enrichment.** On by default (Settings → Knowledge,
  `chatvia.ai.chunker.enhance`): before embedding, the AI enhancement model
  (`LlmTask::Enhancement`) writes a
  short summary and the questions each chunk likely answers (with the page's
  pictures and tables for documents), plus a summary of the whole source. It
  costs one model call per chunk; Off embeds the plain text.
- **Website refresh.** A website can refresh on its own
  (`source_websites.refresh_interval`: off, daily, weekly, monthly). The
  hourly `chatvia:knowledge:refresh-websites` runs `RefreshDueWebsitesAction`, which crawls
  again with the options the website was added with, then trains; pages that
  did not change keep their chunks (checksum).
- **Relevance check.** `KnowledgeBase` keeps only what answers the question:
  a cross-encoder reranker (Settings → AI models → Reranker: OpenRouter, Cohere or a
  self-hosted `/rerank` server) scores the candidates; without one, or when it
  fails, a basic similarity check (cosine to the query, returned by
  `HybridChunkSearch`) gates them. Each model scores on its own scale, so the
  bars follow the model: `RerankModelCatalog` for rerankers,
  `EmbeddingProvider::similarityThresholds()` for the basic check (a model
  never measured gets no check), unless config sets them
  (`RelevanceThresholds`). The thresholds are measured with the evals in
  `tests/Eval` (knowledge retrieval and tool routing). `ToolRelevanceGate`
  gates tools the same way. The results then fit `max_context_tokens`, and
  each source is named for the model and the chat clients (`SourceLabeler`);
  the sources of every answer are stored (`chat_message_sources`).
- **Command line.** `php artisan chatvia:settings:list` lists the values (never
  secrets), `php artisan chatvia:settings:set group.property value` sets one.

Switches for a self-hosted install:

| Setting | Effect |
|---|---|
| Open sign-up (admin panel, **Settings → Workspaces**) | Opens `/register`. On by default. |
| Monthly message limit of new workspaces (admin panel, **Settings → Workspaces**) | Applied when someone signs up. 0 (the default) means no limit. Change one workspace's limit on its page under **Workspaces**. |
| Days to keep conversations (admin panel, **Settings → Workspaces**) | The longest a conversation is kept (default 366). A workspace can choose fewer days in its security settings, never more. |
| Branding (admin panel, **Settings → Branding**) | Shows or hides the "Powered by ChatVia.ai" credit. Hiding it needs a license key ($399, one time), bought at chatvia.ai. Saving a key activates it at chatvia.ai for the host of `APP_URL` (one domain at a time, `LicenseActivation`); the activated key is then checked offline (`CreditLicense`, enforced in `SettingsConfig`). `chatvia:license:check` asks chatvia.ai daily whether the key is still active and removes it if the buyer revoked it there. The product name and links are fixed. |
| `FEATURE_YOUTUBE_SOURCES` | Shows the unfinished YouTube source type (off by default). |

The user guide has the full setup: <https://chatvia.ai/docs/guide/installation>.

---

## 9. Frontend

The dashboard and the hosted chat are one Inertia + Vue 3 app in
`resources/js/`. Every single-file component uses `<script setup lang="ts">`.

| Folder | Holds |
|---|---|
| `pages/` | Inertia pages. Inertia finds a page by its path: `Inertia::render('agents/Sources/Web')` renders `pages/agents/Sources/Web.vue`. Do not move a page without updating the controller. |
| `layouts/` | Page layouts (`AppLayout`, `AgentLayout`, `AuthLayout`, account and workspace layouts). |
| `components/ui/` | shadcn-vue components, generated by the shadcn CLI (`components.json`). Do not edit them by hand; ESLint and Prettier skip them. |
| `components/common/` | Our own shared components (for example `DeleteConfirmationDialog`, `DateRangePicker`). |
| `components/{area}/` | Components for one area (`agents`, `chat`, `analytics`, ...). |
| `api/` | Typed API modules, one per area (`api/topics.ts`, `api/chat.ts`, ...), built on `api/http.ts` (`get`, `post`, `put`, `patch`, `del`, `errorMessage`, `validationErrors`) and the Wayfinder route functions. Components never call axios directly. |
| `routes/`, `wayfinder/` | Typed URL functions generated from the Laravel routes by Wayfinder (gitignored; see below). |
| `composables/` | State and side effects shared by components (`useChatStream`, `useDateFormat`, `useTeamPermissions`, `composables/chat/*`, `composables/agents/*`). Each has a short TSDoc header. |
| `lib/`, `utils/` | Small helpers (`safeStorage`, `fileSize`, the chat event bus). |
| `types/` | Hand-written types (`SharedData`, chat, stream). |
| `i18n/` | vue-i18n setup and the locale files. |

A page file should read like an outline: split a component of more than about
400 lines into child components and composables.

### URLs (Wayfinder)

Build every URL with the functions Wayfinder generates from the named Laravel
routes into `resources/js/routes` (`php artisan wayfinder:generate
--skip-actions`). Route `agents.forms.store` is the `store` export of
`@/routes/agents/forms`:

```ts
import { store } from '@/routes/agents/forms';

router.post(store.url(agentId), payload);
```

The functions return a path, not a full URL; add `window.location.origin`
when a URL leaves the page (copied, shown, put in an embed snippet). The
generated folders are gitignored: Vite generates them before each dev or
build run and again when a route file or controller changes, and
`composer ci:check` generates them before the frontend checks. For "is this
the current page?" use `useCurrentRoute()`, which reads the route name and
parameters the server shares with every page.

Dashboard URLs have no language segment: the UI language follows the
signed-in user's saved locale.

### Types from PHP

PHP DTOs and enums marked `#[TypeScript]` become global TypeScript types in
`resources/types/generated.d.ts` (for example
`Domain.Agent.Data.Resources.Account.AgentResourceData`). After you change such
a class, run `php artisan typescript:transform` and commit the file. Type API
responses with these types or a small local interface; do not use `any`.

### Translations

- UI text always goes through vue-i18n: `t('key')`.
- Add new keys to a fragment file in `resources/js/i18n/locales/_fragments/`
  named `{area}.{locale}.json`, then merge the fragments into the locale
  files: `npm run i18n:merge` (every locale) or
  `npm run i18n:merge -- de fr` for only some. Commit the merged files.
- `npm run i18n:check` fails when a static `t('…')` key is missing from
  `en.json`, or when a message in any locale does not compile. Keys built
  at runtime (`t(variable)`) are not checked, so test them by hand.
- vue-i18n treats `@ { } $ |` as special. Write a literal `@` as `{'@'}`.
  A plural message has exactly two forms (`one | many`).
- Server-side text (e-mails, flash messages, validation) uses Laravel's
  `__('English text')` with `lang/*.json`. This is separate from vue-i18n.

### Other frontend rules

- **Right-to-left:** the app root wraps everything in reka-ui's
  `ConfigProvider :dir`. Use logical Tailwind classes (`ms-*`, `me-*`, `ps-*`,
  `pe-*`, `start-*`, `end-*`, `gap-*`), not `ml-*`, `mr-*`, `left-*`,
  `space-x-*`.
- **Dates:** format with `useDateFormat` (the user's date format) or
  `useLocaleDate`, never with `toLocaleDateString` or `Intl` directly.
- **Confirmations:** destructive actions use the shadcn `AlertDialog`, never
  `confirm()`, `alert()` or `prompt()`.
- **Colors:** theme CSS variables hold a full color. Use `var(--primary)`,
  never `hsl(var(--primary))`.
- **Storage:** read `localStorage` through `lib/safeStorage` (it can throw in an
  iframe).
- **Real-time:** one Echo connection, configured in `app.ts`.
- **Icon-only buttons** get an `aria-label`. Use `useClipboard` from
  `@vueuse/core` to copy text. No `console.log`.

---

## 10. The widget package

Shakomako is the embeddable chat widget (chat bubble and command bar) that
customers put on their own sites with one `<script>` tag. It is a
TypeScript package in its own repository,
[chatvia/shakomako](https://github.com/chatvia/shakomako), with its own
ESLint config, Vitest tests, Rollup build and CI. It imports nothing from the
app.

- It talks to the `/widget/*` API (`routes/widget.php`) and streams answers
  over SSE. It sends the ids in the headers named in `Domain\Widget\WidgetHeaders`;
  those names are a contract on both sides.
- The in-app chat (`resources/js`) serves the dashboard, the hosted chat page
  and the playground. The widget does not share code with it.
- This repository builds its newest release: the highest `vX.Y.Z` tag
  (pre-releases and untagged commits never). `scripts/build-shakomako.mjs`
  finds it; `npm run shakomako:build` builds it into `public/shakomako`,
  `SHAKOMAKO_VERSION` pins another release and `SHAKOMAKO_PATH` builds a local
  checkout. The `Dockerfile` has no default for `SHAKOMAKO_VERSION`: the
  release workflow passes the newest release in (`--print-version`), the
  image's `widget` stage builds that tag, and only the build reaches the
  image. A server change to the widget contract is merged before the widget
  release that needs it.
- The build has no server settings: the widget takes its API from the address
  its `embed.js` loads from (`/widget` on the same server), and the WebSocket
  (Reverb) settings from the widget settings response
  (`chatvia.widget.reverb`).
- The app points to `embed.js` with `chatvia.widget.script_url_embed`: the copy
  in `public/shakomako`, unless the admin panel (**Settings → Widget**) names another one.

See the widget's README for embed options.

---

## 11. Tests

| Suite | Folder | Notes |
|---|---|---|
| Unit | `tests/Unit/` | Actions, services, DTOs, at the path of the class they test (`tests/Unit/Domain/Agent/Actions/Account/CreateAgentActionTest.php` for `src/Domain/Agent/Actions/Account/CreateAgentAction.php`; App code under `tests/Unit/App/…`). A test of behaviour across several classes sits in its context's folder. |
| Feature | `tests/Feature/` | Tests through an entry point (an HTTP route, an admin page, an artisan command, broadcasting auth), at the path of that entry point: `tests/Feature/App/Http/Controllers/…/{Controller}Test.php`, `tests/Feature/App/Admin/…`, `tests/Feature/App/Console/Commands/{Ns}/…`. A flow across several entry points is named for its behaviour in their nearest common folder (`tests/Feature/App/Http/CrossWorkspaceRouteTest.php`). Tests of files outside `src/` sit in `tests/Feature/{Bootstrap,Database,Routes}`. |
| Architecture | `tests/Architecture/` | Framework-free rules (section 2). |
| Eval | `tests/Eval/` | Quality checks with real models (group `eval`). They skip themselves without an API key. |
| Frontend | `resources/js/**/*.test.ts` | Vitest unit tests of plain TypeScript, next to the file they test (`npm run test`, `vitest.config.ts`). |

- Tests use PostgreSQL with the same extensions as the app. The suite builds
  its own `testing` database: on the first run it installs the extensions,
  migrates and seeds; when migrations change it rebuilds the schema. Each
  test runs in a transaction.
- Every Action that has behaviour (a branch, a transaction, an event, an
  outside call) gets a unit test: build the DTO, call `execute()`, check the
  result and the events with `Event::fake()`.
- Tests never call real outside services. Use `Http::fake()` and the fakes in
  `tests/Support/` (embeddings, reranker, channel clients). `TestCase` calls
  `Http::preventStrayRequests()`, so a request no fake answers fails the test,
  and it turns off Inertia's server-side rendering. The `chat` cache store,
  Redis in every environment, is an array store in tests.
- Shared set-up lives in `tests/Pest.php` (`createAgent()`,
  `teammateWithPermissions()`, …) and the model factories. A helper declared
  in a test file is a global function, so it gets a short per-file prefix.

### The ratchets

`ActionsHaveTestsTest` and `ClassesHaveDocblocksTest` each have an allowlist
for the files that do not follow the rule yet. Both lists are empty today:
every Action has a test and every class has a docblock. The lists may only
shrink:

- a new Action without a test, or a new class without a docblock, fails. The
  allowlist is the explicit way out, and it shows up in code review;
- an allowlisted entry that now follows the rule also fails, so you remove it
  from the list.

Both tests print the live ratio on every run, in the form
`Action test coverage: <tested>/<total> (<percent>) tested, <n> allowlisted`.

---

## 12. Where does new code go?

| You are adding ... | Put it in |
|---|---|
| A new state change | an Action in `src/Domain/{Context}/Actions/{Feature}/`, a DTO in `Data/{Feature}/`, a form request with `toData()`, a thin controller method, a unit test |
| A new page | a controller in `src/App/Http/Controllers/{Area}/`, a route, a Vue page in `resources/js/pages/`, API calls in `resources/js/api/` |
| A side effect (e-mail, broadcast, push) | an event in `{Context}/Events/`, a listener in `{Context}/Listeners/`, **registered** in `DomainServiceProvider::registerEventListeners()` |
| Background work | a job that calls an Action, on the right queue (`config/horizon.php`) |
| A client for an outside service | a Service in `{Context}/Services/`, behind a contract when there can be more than one driver |
| A new tool provider or built-in tool | `Domain\Integration\*`, or an MCP server in `App\Mcp` plus a `Connector` class |
| A new catalog connector | a class in `Domain\Integration\Connector\Catalog\Connectors` extending the base for how it connects (`DynamicOAuthConnector`, `PreRegisteredOAuthConnector`, `TokenConnector`, …), listed in `config/connectors.php`, with a logo and i18n text (`ConnectorCatalogTest` checks all three) |
| A new messaging channel | `Domain\Channel\{Name}\` with a `ChannelAdapter` |
| Account or sign-in logic | `App\Identity\*` |
| A generic helper with no business rule | `Support\` |
| A schema change | the table's create migration in `database/migrations/` (before the first release) |
| UI text | an i18n fragment, then `npm run i18n:merge` |
