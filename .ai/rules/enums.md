---
paths:
  - 'src/**/Enums/**'
  - 'src/**/ValueObjects/**'
  - 'src/Support/Casts/**'
---

# Enums and value objects

## Fixed sets are backed enums
A fixed set of values is a string-backed enum in `{Context}/Enums/`, cast on the model. Name it in the singular with no `Enum` suffix; cases are PascalCase (`HostedChat = 'hosted_chat'`). Never rename a stored value.

## Compare cases, not strings
Compare against enum cases everywhere. Use `->value` only at the edges: HTTP, JSON, queue payloads and raw `DB::table()` queries. Never write a literal like `'assistant'` or `'submitted'` where an enum exists.

## Behaviour lives on the enum
Put logic about a case on the enum with an exhaustive `match ($this)`: no `default`, no `switch`. `label()` returns English for the admin panel and server output; the dashboard translates labels from i18n fragments keyed by `->value`.

## Validate with the enum
Form Requests validate with `Rule::enum(X::class)`; use `Rule::in()` with an enum helper only for a subset of cases.

## Export only what the frontend needs
Tag `#[TypeScript]` only on enums whose values reach the Vue frontend, then run `php artisan typescript:transform`.

## Database lists follow the enum
Enum columns are Postgres `CHECK` constraints. A new case must also go into the column's list in the create migration, or inserts fail.

## Value objects are immutable
A value object is a `final readonly class` whose constructor or named constructor (`fromStored()`) checks and normalises its input; every operation returns a new instance. When it is stored in a column, give it a custom cast in `src/Support/Casts/` (`CastsAttributes<TGet, TSet>`) so the model hands it out directly.

## Money is an integer
Never store money as a float. Store an integer in the smallest unit; `float` is only for scores.
