---
paths:
  - 'src/**/Data/**'
  - 'src/App/Http/Resources/**'
---

# Data transfer objects

## Use a DTO where data crosses a boundary
Use a DTO for data that moves between layers: request to Action, an Action's result, a job or event payload, page props, an API response. Skip it for a single model or two validated values. Write one DTO per use case, not one per model.

## Pick the right kind of DTO
Extend `Spatie\LaravelData\Data` when the DTO is built from input, sent over the wire, nested, cast or exported to TypeScript. Use a plain `final readonly class …Data` for internal carriers such as verdicts, scores and commands.

## Name DTOs for their role
Input DTOs start with the verb of the Action they feed (`CreateAgentData`, `RenameAgentData`). Output DTOs end with their role: `…ResourceData` (page props), `…RowData` (one list row), `…ResultData` (an Action's answer), `…PageData`, `…RefData`. Public API responses live in `App\Http\Resources\Api\V1\{Area}\{Noun}Data`.

## Build DTOs with named arguments
Create them with `new CreateAgentData(user: …, team: …, name: …)`, reading request input through typed accessors (`$this->string('name')->toString()`, `->boolean()`, `->integer()`, `->enum('auth_type', FunctionAuthType::class)`). Prefer this to `XData::from($this->validated())`. A DTO may carry the bound models (`User`, `Team`, `Agent`) instead of their ids.

## Give output DTOs named constructors
Build output DTOs with a static constructor named for the source: `fromModel(Agent $agent)`, `fromConfig()`, `fromEvent()`. Plain readonly DTOs use `fromArray()` or `fromPayload()`. Eager-load every relation `fromModel()` reads, or guard it with `relationLoaded()`.

## Type every property
Make a property nullable only when the value can really be null, use the enum type for enum values, and nest DTOs instead of flattening fields. Type lists as `#[DataCollectionOf(X::class)] DataCollection` or document them as `Collection<int, XData>`. Never use `array{…}` or `array<string, mixed>` as a domain result.

## Property names follow the wire
Internal DTOs use camelCase properties. A DTO whose properties are the JSON or Inertia keys (API resources, widget payloads, `#[TypeScript]` page props) uses snake_case to match the wire. Never mix both in one class, and never add a name mapper.

## No validation in DTOs
Validation lives in Form Requests. A DTO has no `rules()` method and no validation attributes, and is never injected into a controller to validate a request.

## Never change a DTO after it is built
DTOs are read-only. To change a value, build a new DTO.

## Tag frontend DTOs for TypeScript
A DTO that reaches the Vue frontend carries `#[TypeScript]`, and so does every type it references. After changing one, run `php artisan typescript:transform`.
