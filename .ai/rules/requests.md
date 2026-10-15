---
paths:
  - 'src/App/Http/Requests/**'
  - 'src/App/Http/Rules/**'
  - 'src/App/Rules/**'
  - 'src/Domain/**/Rules/**'
---

# Form requests and validation

## Every input gets a Form Request
Validate in a Form Request named `{Verb}{Noun}Request` (`UpdateAgentLlmRequest`, `CreateAgentRequest`) under `src/App/Http/Requests/{Area}/{Feature}/`. Use one `Save{Noun}Request` when store and update share the same rules; otherwise write `Create…` and `Update…` and share the rules through a trait named for what it validates (`ValidatesPdfAttachment`).

## Rules are arrays
Write `['required', 'string', 'max:255']`, never `'required|string'`. Validate enums with `Rule::enum(GuardMode::class)` and option lists from the enum (`Rule::in(GeoMode::values())`), never a hand-typed `'in:a,b'`. Scope every `exists` / `unique` to the workspace with `App\Http\Rules\WorkspaceRule::exists()` / `::unique()`.

## toData() builds the DTO
`toData()` returns the Action's `…Data` (never an `array`) and may take route models as parameters (`toData(Agent $agent)`). Clean input up here: decode JSON, trim, turn blanks into null, use `Optional` for fields left out of a partial update. `prepareForValidation()` only normalises raw input before the rules run; don't `replace()` data in `passedValidation()`.

## authorize() defers to route middleware
When route middleware gates the route, `authorize()` returns `true` and the class docblock names that middleware. Put a policy check here only when no middleware fits. A record from another workspace is `abort_unless(…, 404)`, never `return false` (that answers 403).

## Look up a bound record once
When a request resolves a workspace record by `external_id`, keep it in a private property, 404 in `authorize()` when it is missing, and expose a typed accessor (`function()`, `prompt()`) for the controller.

## Cross-field checks use after()
For checks across fields or against the database, return closures from `after(): array`; don't use `withValidator()`. Put empty-value checks there too, because `nullable` skips a field's rules on empty input.

## Partial updates use sometimes and Optional
Mark PATCH fields `sometimes` and map a missing field to spatie `Optional` in the DTO. Use `required_if`, `required_with`, `exclude_if` and `prohibits` for conditional rules.

## Translate every message
Add `messages()` or `attributes()` only for user-facing wording, and wrap each string in `__()`.

## Rule objects are small and final
A custom rule is a `final readonly class` that implements `ValidationRule`. Services come through the constructor; messages through `__()` or `$fail('validation.x')->translate()`. Put it in `App\Http\Rules` when it needs request or app state, in `{Context}\Rules` for a domain invariant, never in Support. Unit-test it by calling `validate()` with a fail closure.
