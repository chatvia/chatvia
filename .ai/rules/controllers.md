---
paths:
  - 'src/App/Http/Controllers/**'
  - 'src/App/Http/Chat/**'
  - 'src/App/Http/Widget/**'
---

# Controllers

## Keep controllers thin
A controller receives the request, hands the work to an Action and returns a response (Inertia, JSON or a redirect). No business rules, calculations, outside HTTP calls, event dispatch or multi-step writes in its body. User-facing strings go through `__()`.

## Validate with a Form Request, then call an Action
Type-hint a Form Request, turn it into a DTO with `$request->toData()`, and method-inject the Action: `public function store(CreateAgentRequest $request, CreateAgentAction $action)`. Never call `$request->validate()`, `Validator::make()` or `$request->validated()` in a controller. Inject in the constructor only what every method shares; never `new` or `app()` a service.

## Model custom actions as resources
Public methods are the seven resource verbs or `__invoke` only. A custom action becomes CRUD on a new resource (Cruddy by Design): publishing is `PublishedAgentController@store`, not `AgentController@publish`. Use an invokable `{Verb}{Noun}Controller` (`ExportFormSubmissionsController`) when the new resource has only one action. Don't split one resource's CRUD into one invokable per verb, and never rename an existing controller whose route names the frontend uses.

## Name controllers for the resource
Resource controllers are singular `{Resource}Controller`; nested resources are `{Parent}{Child}Controller` (`AgentTopicController`).

## Bind models from the route
Type-hint the model (`Agent $agent`, `{function:external_id}`) instead of taking an `$id` and calling `findOrFail()`. Under an `{agent}` route, bind child records as models too: `can.access.agent` returns a 404 for any record that is not the agent's. Sources linked through `agent_sources` still need their own check.

## Short methods; queries out of the way
A read-only `index` or `show` may query directly, but anything past a simple lookup moves into a `#[Scope]`, a `{Model}QueryBuilder` method or a `{Context}/Queries/…Query`. When a method no longer fits on one screen, extract.

## Shape props and JSON with Data classes
Pass Inertia props as resource DTOs (`AgentResourceData::fromModel($agent)`), or as a page-props class like `App\Http\Chat\HostedChatPageProps` when a page draws on many sources. No private helpers that return `array<string, mixed>` and no hand-built row arrays. Data every page needs goes in `HandleInertiaRequests::share()` as lazy closures.

## Catch only to choose the response
Let `DomainValidationException` bubble up to the global 422. Catch a domain exception only when the controller must answer differently (a flash message, a redirect, another status).

## Authorize on the route
Team and agent permissions are route middleware, not code in the method; `.ai/rules/authorization.md` has the details.
