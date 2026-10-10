# Chatvia architecture

This guide explains how the Chatvia code is organised and why. Read it before
you make a bigger change. [CONTRIBUTING.md](CONTRIBUTING.md) explains how to set
up the project, run the checks and send a pull request.

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
8. [External services and self-hosting switches](#8-external-services-and-self-hosting-switches)
9. [Frontend](#9-frontend)
10. [The widget package](#10-the-widget-package)
11. [Tests](#11-tests)
12. [Where does new code go?](#12-where-does-new-code-go)

---

## 1. The big picture

Chatvia is a Laravel 13 application (PHP 8.5) with an Inertia + Vue 3 +
TypeScript frontend. Workspaces build AI agents, give them knowledge and tools,
and put them in front of people on a hosted chat page, an embeddable widget, a
command bar, messaging apps and a REST API.

### Processes

| Process | Command | What it does |
|---|---|---|
| Web | PHP-FPM (or `php artisan serve`) | Dashboard, hosted chat, widget API, REST API, webhooks. Chat answers stream as server-sent events (SSE). |
| Queues | `php artisan horizon` | Ingestion, channel replies, analytics, exports, embeddings. Several named queues (see `config/horizon.php`). |
| WebSockets | `php artisan reverb:start` | Live updates: ingestion progress, handoff messages, typing, eval progress. |
| Scheduler | `php artisan schedule:run` (cron, every minute) | Pruning, analysis, usage caches, backups (see `routes/console.php`). |
| PostgreSQL 18 | | All data, plus vector search (`vchord`, `pgvector`) and keyword search (`vchord_bm25`, `pg_tokenizer`). |
| Redis | | Queues, cache, rate limits, the live chat memory. |

### Repository map

| Path | What is there |
|---|---|
| `src/App/` | `App\` namespace: HTTP layer, framework wiring, accounts (`Identity`), built-in MCP servers, admin panel. |
| `src/Domain/` | `Domain\` namespace: business logic, one folder per bounded context. |
| `src/Support/` | `Support\` namespace: small helpers with no business rules. |
| `routes/` | One file per area (`agents.php`, `chat.php`, `widget.php`, `api.php`, `ai.php`, one per messaging channel, ...). |
| `config/` | Laravel config plus `chatvia.php` (product settings), `connectors.php`, `team-permissions.php`. |
| `database/` | Migrations, factories, seeders. |
| `resources/js/` | The Vue frontend (dashboard and hosted chat). |
| `resources/prompts/` | LLM prompt templates (Blade files, loaded with `prompt('folder/name')`). |
| `resources/views/` | Blade views: e-mails, PDF exports, the app shell. |
| `resources/types/generated.d.ts` | TypeScript types generated from PHP classes. |
| `lang/` | Server-side translations (e-mails, flash messages, validation). |
| `shakomako/` | Shakomako, the embeddable chat widget: a separate JavaScript package, built into `public/shakomako`. |
| `docs/` | The user documentation (VitePress). |
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
| `tests/Architecture/ConventionsTest.php` | Domain does not import `Illuminate\Http\Request` (except the two bridges); Domain imports from App only through `App\Identity`; Integration does not use Conversation; Support has no code reference to Domain or App; Actions use `execute()` (never `handle()`), never return `redirect`, never use the `Auth` facade, never send notifications; Jobs have no transactions or row locks; listeners have no `Listener` suffix; Domain models have no `...Request` names; controllers never call `$request->validate()`; `external_id` lookups of conversations and sessions are scoped; every listener is registered in a service provider. |
| `tests/Architecture/ActionsHaveTestsTest.php` | Every Action has a test, or is on a shrink-only allowlist. Prints the coverage. |
| `tests/Architecture/ClassesHaveDocblocksTest.php` | Every class, interface, trait and enum under `src/` has a docblock with at least one sentence of prose, or its file is on a shrink-only allowlist. Prints the coverage. |
| `tests/Architecture/FrontendConventionsTest.php` | Frontend rules: no `hsl(var(--x))`, no third-party icon CDN or Google Fonts, no `console.log`, `safeStorage` on the boot and hosted-chat paths, one shared Echo connection, `AlertDialog` for delete confirmations. |

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
| `App\Identity\Workspace` | The `User` model (all account types), sign-in flows, sessions and known devices, social accounts, data export, account deletion, monthly message limits (`MessageLimit`, `MessageAllowance`, `Actions/MessageLimits`), `AgentAccessPolicy`, `TeamPermissionPolicy`, widget sign-in tokens and widget session tokens. |
| `App\Identity\Chatter` | Chatter accounts: create, update, delete, reset password, chatter auth tokens. |
| `App\Identity\ApiKey` | Creating and deleting API keys for the REST API. |

All accounts are rows in `users`. The `type` column (`UserType`) says which
kind: `admin`, `workspace`, `employee` (a teammate) or `chatter`.

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

### `App\Http`

| Folder | Holds |
|---|---|
| `Controllers/{Area}/` | Thin controllers, grouped by screen area (`Agent`, `Chat`, `Widget`, `Api/V1`, `Settings`, `Webhooks`, ...). |
| `Requests/{Area}/` | Form requests with `rules()`, `authorize()` and `toData()`. |
| `Middleware/{Access,Auth,Chat,Web,Widget}/` | Access checks (`can.access.agent`, `agent.permission:…`, `team.permission:…`), authentication, hosted-chat resolution, web concerns (CSP, locale, Inertia), widget origin and access checks. Aliases are registered in `bootstrap/app.php`. |
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
cookie (`session.chat`, path `/chat`) and the `chat` guard. Chat code uses the
default guard, never `guard('web')`. Sign-ins that happen outside `/chat`
(magic links with two-factor, invitations, dashboard previews) reach the chat
with a one-time, agent-bound `?auth_token=` (`ChatterAuthTokenService`). An
agent's custom domain redirects to its chat (`RedirectCustomDomainToChat`).

### `App\Mcp`: built-in MCP servers

Chatvia offers some tools of its own (charts, inline forms, weather, currency,
web search, account analytics) as Laravel MCP servers in `App\Mcp\Servers`,
with their tools in `App\Mcp\Tools\{Analytics,Charts,Currency,Forms,Perplexity,Weather}`.
They are routed in `routes/ai.php` under `/mcp/*` and added to agents like any
other connector (`config/connectors.php`).

- They are transport adapters, like controllers: validate the input, call a
  Domain Action, query or service, and map the result to the MCP response.
- `VerifyInternalMcpRequest` accepts only calls signed by Chatvia itself
  (`BUILTIN_MCP_SECRET`) and resolves the caller once into an
  `McpCallerContext`, which tools receive by injection. Tools never read the
  `X-Chatvia-*` headers themselves.
- A tool's name, description, input schema and output are a contract with the
  LLM and the chat UI. Snapshot tests in `tests/Feature/Mcp/` pin them. Tool
  names come from the class name and are stored in the database, so never
  rename a tool class.
- After you add or change a built-in tool, run `php artisan app:sync-builtin-mcp`.
  It writes the tools into the shared catalog and embeds them for tool search.

### No public website

Chatvia has no landing, pricing, blog or legal pages. `/` (route `home`,
`HomeController`) sends guests to the login form and signed-in users to their
dashboard. Link your own website with `BRAND_WEBSITE_URL`.

### Other App folders

| Folder | Holds |
|---|---|
| `App\Admin` | The Filament admin panel at `/admin`. It signs in on its own `admin` guard and asks for two-factor authentication (required in production). Settings for third-party credentials live in `App\Admin\Settings`. |
| `App\Console\Commands` | Artisan commands. Keep `handle()` small: read the options, call an Action, print the result. |
| `App\Providers` | Service providers. `DomainServiceProvider` binds contracts to drivers, registers observers and **every event listener**. `AppServiceProvider` holds framework settings (password policy, morph map, model strictness). Others: rate limiters, gates, filesystems, API docs, Horizon, Telescope, TypeScript transformer. |
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
- **`Services\Tooling\`**: `ToolLoopRunner` (stream, run tool calls, repeat),
  the approval store and per-turn tool selection.
- **`Services\Memory\`**: `ChatMemory`, the live chat history in the cache
  (encrypted). The database copy is the chat log.
- **`Topic`**: conversation topics (`Topic`, `ConversationTopic`).
- Actions by feature: `Conversations`, `Messages`, `Sessions`, `Handoff`,
  `Attachments`, `Activity`, `Removal`, `Retention`, `Tools`.

### Knowledge

The knowledge base behind the agents' answers (RAG).

| Sub-namespace | Holds |
|---|---|
| `Knowledge\Source` | The source types: `File` (`SourceFile`), `Web` (`SourceWebsite` and its pages, `AgentWebsiteUrl`), `Text` (`SourceText`), `Qa` (`AgentQuestionAnswer`), `Youtube` (behind `FEATURE_YOUTUBE_SOURCES`). Sources belong to the workspace and are attached to agents through `agent_sources`. |
| `Knowledge\Ingestion` | The pipeline from a source to searchable chunks: queued jobs per type, parsing (`DocumentParser`), splitting (`TextSplitter`), chunk enhancement, OCR, media. The `Chunk` model. |
| `Knowledge\Embedding` | Embedding providers (`Qwen3`, `Cohere`, `Jina`, `Mistral`) and rerankers (`Qwen3Reranker`, `CohereReranker`, `NullReranker`). |
| `Knowledge\Retrieval` | Search: `HybridChunkSearch` (vector + BM25 in one SQL query), `KnowledgeBase` (embed, search, rerank), scoring and retrieval stats. |
| `Knowledge\Document` | File conversion tools (`LibreOffice`, `Pandoc`, `PdfInfo`) and `HasStorageFile` for models whose file is in storage. |

### Integration

Tools that an agent can call.

| Sub-namespace | Holds |
|---|---|
| `Integration\Tool` | The shared tool layer: the registry of an agent's tools, routing and the relevance gate, hybrid tool search, dispatch, access, approval and rate-limit policies, the `ToolName` value object (`mcp__{server}__{tool}`), embedding jobs. |
| `Integration\Function` | Webhook tools (`AgentFunction` model), `HttpToolDispatcher`, `FunctionSchemaValidator`. |
| `Integration\Mcp` | MCP servers and their tools (`AgentMcpServer`, `McpTool`, the shared `ConnectorTool` catalog), the `McpClient`, per-tool permissions. |
| `Integration\Connector` | Catalog connectors that sign in with OAuth: the OAuth flow (PKCE, token refresh), catalog sync, per-workspace OAuth clients (for example Salesforce). |
| `Integration\Builtin` | Clients for the outside services behind Chatvia's built-in tools: weather, currency, Perplexity web search. |

### Llm

Talking to language models. Provider clients (`Scaleway`, `Mistral`,
`OpenRouter`, `Groq`) implement `LlmProviderInterface` (OpenAI-compatible).
Callers use typed helpers: `LlmChat` (one completion), `StructuredCompletion`
and `LlmJson` (JSON answers), `DocumentLlm` (a model that reads whole PDF
files). The `Llm` model is the model catalog agents pick from.

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

- `Membership\Agent`: which chatters may use which agent (the `agent_user`
  pivot), chatter invitations (`AgentChatterInvitation`).
- `Membership\Team`: teams, roles and permissions for teammates (`Team`,
  `Role`, `Permission` on spatie/laravel-permission, the `team_user` pivot),
  team invitations. The permission codes live in `config/team-permissions.php`.

### Analytics

Event recording (`analytics_events` and rollups), dashboard and per-agent
statistics, conversation analysis (topics, sentiment, tone, with an LLM), IP to
country, user-agent parsing, and the query classes behind the account-analytics
MCP server. The tables can live in a separate database
(`ANALYTICS_DB_CONNECTION`); all access goes through `AnalyticsDatabase`.

### Evaluation

Test sets for agents: cases (`EvalCase`), runs (`EvalRun`) and results
(`EvalResult`). `EvalAgentRunner` runs each case through the agent's real
pipeline, but tools are only recorded, never called, and nothing is saved to
the chat log. Scorers (exact match, contains, regex, JSON schema, tool call,
LLM judge) grade the answers.

### Guardrail

Checks on the visitor's message and on the agent's answer: personal data (PII)
is found with regex filters and redacted; prompt injection, off-topic and toxic
content are judged by one classifier call. Every decision is logged as a
`GuardrailEvent`.

### Security

Workspace security settings and audit: IP allowlist and country rules
(`NetworkAccessGuard`), conversation retention, the security audit log and
delivery to a SIEM webhook. Every workspace has the full security feature set.

### Form

Forms inside the chat. A `FormDefinition` is a form an agent can show; a
`FormSubmission` stores the answers and is the single store for captured
contacts (leads). The Forms MCP server finds and renders forms; a submission
fires `FormSubmitted`, which can push it to a CRM webhook.

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
conversation-stream family.

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
  embeddings, MCP client, LibreOffice) or a stateless utility (`Chunker`,
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

Key workspace data to the workspace **owner** (`$agent->user_id`,
`$user->workspace()->id`), never to the teammate who is acting. Otherwise
removing a teammate would delete workspace data.

### Database

- Until the first release, each table has one create migration that holds its
  whole schema. A schema change edits that migration; there are no
  `add_*`/`alter_*` or data migrations. From the first release on, migrations
  only move forward.
- Do not set `protected $table` when Laravel's default name is right.
- Pivot tables and pivot models have no timestamps.
- Embeddings are `halfvec` with 1024 dimensions.

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
| Tenant / owner account | **Workspace** | `User` with `UserType::Workspace` (or `Admin`) |
| Dashboard user invited into a workspace | **Teammate** | `User` with `UserType::Employee`; role in `team_user` |
| End user who signs in to chat with an agent | **Chatter** | `User` with `UserType::Chatter` |
| Anonymous person in the chat (not signed in) | **Visitor** | a `Session` without a `user_id` |
| A visitor's identity with one agent, across conversations | **Session** | `Domain\Conversation\Models\Session` (`chat_sessions`) |
| One persistent chat between an agent and a visitor or chatter | **Conversation** | `Domain\Conversation\Models\Conversation` |
| One message in a conversation | **Message** | `Domain\Conversation\Models\Message` (`MessageRole`) |
| Working state of one turn while it streams | **ChatContext** | `Domain\Conversation\Services\ChatContext` |
| The live chat history in the cache | **Chat memory** | `Domain\Conversation\Services\Memory\ChatMemory` |
| Hosted-chat visitor session for the current request | **HostedChatSession** | `App\Http\Chat\HostedChatSession` |
| Widget sign-in credential for a protected agent | **Widget session token** | `WidgetAuthSessionService`, sent as `X-AUTH-TOKEN` (not related to `Session`) |
| Chatter's access to an agent | **AgentMembership** | the `agent_user` pivot (`GrantAgentMembershipAction`) |
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
| A catalog MCP integration that signs in with OAuth | **Connector** | `config/connectors.php` + an `AgentMcpServer` row |
| A turn paused until a person approves a tool call | **ToolApprovalCheckpoint** | `Domain\Conversation\Models\ToolApprovalCheckpoint` |
| A human operator taking over a conversation | **Handoff** | `HandoffStatus` on `Conversation`, `Actions/Handoff` |
| Contact details captured in a conversation | **Lead** | a `FormSubmission` (`Domain\Form\Models\FormSubmission`) |
| Where an agent answers | **Surface**: Hosted Chat, Chat Widget, Command Bar | values `hosted_chat`, `widget`, `bar` |
| A messaging app connection | **Channel** | `Domain\Channel\*` |
| A monthly cap on a workspace's agent replies or a chatter's messages | **MessageLimit** | `App\Identity\Workspace\Models\MessageLimit` (`message_limits`) |

---

## 8. External services and self-hosting switches

Outside services sit behind contracts. `DomainServiceProvider` picks the driver
from config, so the rest of the code never knows which service runs. Most steps
have an `auto` value: use the hosted service when its API key is set, otherwise
a local implementation or "off". A self-hosted install can run without most
accounts.

| Step | Contract | Drivers | Setting |
|---|---|---|---|
| Chat answers | `LlmProviderInterface` | `scaleway`, `mistral`, `openrouter`, `groq` | `LLM_PROVIDER` |
| Embeddings | `EmbeddingsProviderInterface` | `qwen`, `cohere`, `jina`, `mistral` (must return 1024 dimensions) | `AI_EMBEDDING_PROVIDER` |
| Reranking | `Reranker` | `qwen`, `cohere`, `none` | `AI_RERANKER` |
| Reading documents | `DocumentParser` | `mistral`, `local` (pdftotext + LibreOffice) | `AI_DOCUMENT_PARSER` |
| Splitting text | `TextSplitter` | `chunkfit`, `local` | `AI_TEXT_SPLITTER` |
| Speech to text | `Transcriber` | `scaleway`, `none` | `AI_TRANSCRIBER` |
| Reading whole PDFs | `DocumentLlm` | `mistral`, `none` | `AI_DOCUMENT_LLM` |
| Files | Laravel disks `private`, `public`, `assets` | local, S3-compatible, Bunny CDN (falls back to local) | `FILESYSTEM_*_DRIVER`, `SCALEWAY_BUCKET`, `BUNNYCDN_STORAGE_ZONE` |

API keys and OAuth apps for outside services (AI providers, Firecrawl,
chunk.fit, social sign-in, Meta, connectors, Resend, Turnstile, IP2Location)
and the workspace options (open sign-up, the message limit of new workspaces,
days to keep conversations) are entered in the admin panel under
**Settings**. They
are stored encrypted and put into the same config keys when the app boots,
before the providers boot (`App\Admin\Settings\SettingsStore`). The `.env`
file chooses drivers, endpoints and models.

Switches for a self-hosted install:

| Setting | Effect |
|---|---|
| Open sign-up (admin panel, **Settings → Workspaces**) | Opens `/register`. On by default. |
| Monthly message limit of new workspaces (admin panel, **Settings → Workspaces**) | Applied when someone signs up. 0 (the default) means no limit. Change one workspace's limit on its page under **Workspaces**. |
| Days to keep conversations (admin panel, **Settings → Workspaces**) | The longest a conversation is kept (default 366). A workspace can choose fewer days in its security settings, never more. |
| `BRAND_*` | Product name, links and the "Powered by" line. |
| `FEATURE_YOUTUBE_SOURCES` | Shows the unfinished YouTube source type (off by default). |

The user guide has the full setup: `docs/guide/self-hosting/`.

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

`shakomako/` is Shakomako, the embeddable chat widget (chat bubble and command
bar) that customers put on their own sites with one `<script>` tag. It is a
separate vanilla JavaScript package with its own `package.json`, ESLint config,
Vitest tests and Rollup build, and its own CI workflow
(`.github/workflows/shakomako.yml`). It imports nothing from the app, so the
folder also works on its own.

- It talks to the `/widget/*` API (`routes/widget.php`) and streams answers
  over SSE. It sends the ids in the headers named in `Domain\Widget\WidgetHeaders`;
  those names are a contract on both sides.
- The in-app chat (`resources/js`) serves the dashboard, the hosted chat page
  and the playground. The widget does not share code with it.
- `npm run shakomako:build` builds it into `public/shakomako` (the Docker
  image runs it). The build has no server settings: the widget
  takes its API from the address its `embed.js` loads from (`/widget` on the
  same server), and the WebSocket (Reverb) settings from the widget settings
  response (`chatvia.widget.reverb`).
- The app points to `embed.js` with `chatvia.widget.script_url_embed`: the copy
  in `public/shakomako`, unless `WIDGET_SCRIPT_URL` names another one.
- `.github/workflows/shakomako-mirror.yml` publishes the folder, with only its
  own history, to a separate read-only repository (every push to `main` and
  every `v*` tag). The copy closes pull requests with a pointer back here
  (`shakomako/.github/workflows/close-pull-request.yml`). This repository stays
  the only place to change the widget.

See [shakomako/README.md](shakomako/README.md) for building and embed options.

---

## 11. Tests

| Suite | Folder | Notes |
|---|---|---|
| Unit | `tests/Unit/` | Actions, services, DTOs. Grouped by context, following the source layout (`tests/Unit/Agent/Actions/Account/CreateAgentActionTest.php`). |
| Feature | `tests/Feature/` | HTTP-level tests, the safety net for routes, middleware and permissions. |
| Architecture | `tests/Architecture/` | Framework-free rules (section 2). |
| Eval | `tests/Eval/` | Quality checks with real models (group `eval`). They skip themselves without an API key. |

- Tests use PostgreSQL with the same extensions as the app. The suite builds
  its own `testing` database: on the first run it installs the extensions,
  migrates and seeds; when migrations change it rebuilds the schema. Each
  test runs in a transaction.
- Every Action that has behaviour (a branch, a transaction, an event, an
  outside call) gets a unit test: build the DTO, call `execute()`, check the
  result and the events with `Event::fake()`.
- Tests never call real outside services. Use `Http::fake()` and the fakes in
  `tests/Support/` (embeddings, reranker, channel clients).

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
| A new tool provider or built-in tool | `Domain\Integration\*`, or an MCP server in `App\Mcp` plus `config/connectors.php` |
| A new messaging channel | `Domain\Channel\{Name}\` with a `ChannelAdapter` |
| Account or sign-in logic | `App\Identity\*` |
| A generic helper with no business rule | `Support\` |
| A schema change | the table's create migration in `database/migrations/` (before the first release) |
| UI text | an i18n fragment, then `npm run i18n:merge` |
