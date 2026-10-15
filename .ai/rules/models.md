---
paths:
  - 'src/**/Models/**'
  - 'src/**/QueryBuilders/**'
  - 'src/**/Queries/**'
  - 'src/**/Observers/**'
  - 'src/Support/Concerns/**'
---

# Models and queries

## Configure models with attributes
Use Laravel 13 model attributes: `#[Guarded(['id'])]` by default, `#[RouteKey('external_id')]` on routed models, `#[WithoutTimestamps]`, `#[UseFactory]`. Add `#[Table]` only when the default name does not resolve (pivot models default to a singular name, so they usually need it). Declare casts in `casts()`. Never write `$fillable`, `$guarded`, `$table` or `$casts` properties. On the few models with `#[Fillable]`, add every new column to the list: strict mode throws on attributes it would drop.

## Cast every typed column
Cast each column that is more than a plain string or integer: `datetime`, `boolean`, `array`, `integer`, an enum class, or a custom cast. A column backed by an enum always casts to that enum.

## Keep one member order
Traits, constants, `casts()`, relationships, accessors, scopes, then `booted()`. Keep the `@property` docblock in step with the create migration.

## Type every relationship
Each relationship has a native return type and a generic docblock: `@return BelongsTo<Agent, $this>`. Name a filtered relationship after a domain idea (`pdfFiles()`, `lastMessage()`). For "the latest X of each parent" use `hasOne()->latestOfMany()`, never `hasMany()->latest()->limit(1)` inside `with()`. Don't use `withDefault()`; read nullable relations with `?->`. A relationship never reads `auth()` or the request.

## Accessors derive; they don't query
Write accessors as `protected function name(): Attribute { return Attribute::get(fn (): string => …); }`, never `getNameAttribute()`. They only derive a value from the model's own columns: no queries and no business rules. For counts use `withCount()` or a subselect.

## Models hold data, not workflows
No orchestration, event dispatch, authorization checks, container lookups or HTTP calls in a model; those belong in Actions, Policies and services. No `toArray()` overrides (shape output with a resource DTO) and no validation (Form Requests). Expose state questions as predicates (`isHandedOff()`, `isIngested()`) instead of repeating status comparisons elsewhere.

## Where queries live
One filter on one model is a `#[Scope] protected function active(Builder $query): void`, named as an adjective or `forX` (never `scopeX`, never `get…`). Past about three scopes, move them to `{Context}/QueryBuilders/{Model}QueryBuilder`. A read across tables, or a report, is a `{Context}/Queries/{Name}Query`. No repositories, and no wrapper class around a one-line query.

## Query builder shape
Bind a builder by overriding `newEloquentBuilder($query): AgentQueryBuilder` with `@return AgentQueryBuilder<self>`. Declare `@template TModel of Agent` and `@extends Builder<TModel>`; chainable methods return `self` (`@return self<TModel>`). Name methods after domain ideas (`forAgent`, `reachableByWidgetVisitor`), never `whereXIs`. Test builder methods against the real database and assert on the rows returned, not the SQL.

## Strict mode is on
`Model::shouldBeStrict()` throws outside production on a lazy load, on reading a column that was not selected, and on an attribute `#[Fillable]` would drop. Eager-load every relation you read (`with()`, `loadMissing()`, dot notation, constrained closures), select every column you later read, and count with `withCount()` / `withExists()`.

## Filter in the database
Never `Model::all()->where(…)`; filter and aggregate in SQL. Use `pluck()` for lists, and `chunkById()` / `lazyById()` for big batches (never offset-based `chunk()`).

## Bulk queries skip model events
A query-level `update()` or `delete()` fires no model events and no observers. Delete models with cleanup hooks (attachments, conversations, messages, every knowledge source, LLMs) through Eloquent: `chunkById()`, then `->delete()`. Pivot writes fire no events either, so dispatch the cache-busting event yourself (`AgentToolsChanged`).

## booted() keeps data consistent; observers run side effects
Use `booted()` and boot traits only to fill defaults (slug, secret, `external_id`), cascade deletes through Eloquent and flush caches that mirror the row. Side effects (dispatching jobs, deleting files) go in `{Context}/Observers/{Model}Observer`, registered by hand in `DomainServiceProvider::registerObservers()` (no `#[ObservedBy]`), with storage deletes inside `DB::afterCommit()`. Prefer a domain event from an Action to a hidden model event.

## Public ids and morph aliases
A model with a public id uses `HasExternalId` (uuid7 in `external_id`). Polymorphic columns store `getMorphClass()` (the morph-map alias), never `::class`. Never rename a model, its table or a morph alias without a plan.
