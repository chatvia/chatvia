---
paths:
  - 'src/App/Http/Controllers/Api/**'
  - 'src/App/Http/Requests/Api/**'
  - 'src/App/Http/Resources/Api/**'
  - routes/api.php
---

# Public API

## Version the API
Routes sit under `prefix('v1')->name('api.v1.')`; controllers, requests and resources live under `…\Api\V1\{Area}`. A breaking change to a response shape means new V2 classes; never reshape V1.

## Reuse the dashboard's Action
An API endpoint calls the same Domain Action as the dashboard. Only the input (Form Request to DTO) and the response format differ.

## Answer with Data classes in a data envelope
Return a `Spatie\LaravelData\Data` class from `App\Http\Resources\Api\V1\{Area}\{Noun}Data`, built with `fromModel()`, wrapped as `['data' => …]` and typed `@return array{data: AgentData}` or `array{data: list<AgentData>}` for the API docs. Never return a model, a `JsonResource` or a hand-built array.

## Expose public ids and ISO dates
Expose `external_id` as `id`, never the integer key. Dates are ISO-8601 strings (`->toIso8601String()`).

## Use the right status code
201 for create, 204 for delete, 422 for validation, 404 for another workspace's record, 403 for a missing permission.

## Paginate every list
A list endpoint cursor-paginates: it accepts `cursor` and `per_page` (default 25, clamped to 1–100) and returns `{data, next_cursor}`. Never return an unbounded `->get()`.

## Only declared filters
Validate each filter in the list Form Request and resolve it inside the workspace. Never pass a raw query parameter to `where()` or `orderBy()`.

## Document every endpoint
Every API controller has `#[Group(name:, weight:)]`; the first docblock line of each method is the endpoint title; list parameters use `#[QueryParameter]`. Public docs use generic words ("bearer token", not the package name).

## One error shape
Errors are `{message, errors?}` with the right status. A field error from an Action is a thrown `DomainValidationException`; never hand-build 422 JSON in a controller.
