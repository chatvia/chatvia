---
paths:
  - 'src/**/*.php'
---

# Src

## Use filled() / blank() for empty checks
Check for an empty value with Laravel's `filled()` / `blank()` (on a request: `$request->filled('key')`, then `string()` / `integer()`). Never write `is_string($x) && $x !== ''`, `$x !== null && $x !== ''`, `$x === null || $x === ''` or `trim($x) === ''`.
Keep `is_string()` only when the value can really be another type (raw request input before validation, outside JSON, LLM output, a validation rule's `mixed $value`), and pair it with `filled()`: `is_string($x) && filled($x)`. For a header use `$request->headers->get()` (typed `?string`), not `header()`.
Exceptions: streamed text deltas (a lone space or newline is real output, so compare with `''`), and values where an empty array is real data (`filled([])` is false).

## Choose boring code over clever code
Prefer an early return, `if` or `match` to nested ternaries, and drop the `else` after a `return`. More lines for more clarity is a good trade. Reach for the framework helper first: `abort_if()` / `abort_unless()`, `findOrFail()`, `Cache::remember()`, `Str`, `Arr`, collections.

## Build only what is needed now
Add an interface, factory, manager or wrapper class only for a second real implementation, a test fake, or a context boundary. A class that wraps one call is noise: no `GetAgentsAction` around `Agent::query()->active()->get()`. A well-named scope called from a controller is already clean.

## Name things for what they hold and do
Variables say what they hold (`$activeAgents`, not `$data`), with no abbreviations; plural for collections, singular for one item. Methods are camelCase and start with a verb. Methods and local variables that answer yes/no start with `is`, `has`, `can`, `should`, `supports` or `needs`. Constants are typed SCREAMING_SNAKE_CASE (`public const string PREFIX = '…';`); a class that only lists choices is an enum.

## Inject your own classes; never new them
Get Actions, services and clients from the container: method injection when one method uses it, constructor injection (promoted `private readonly`) when several do. `new` is only for data: DTOs, value objects, exceptions. Laravel facades (`DB`, `Cache`, `Http`, `Storage`, `Log`, `Bus`) are fine anywhere, because tests can fake them.

## Use app() only where injection is impossible
`app(X::class)` is a service locator. Use it only in Filament closures, models, enums, traits, FormRequest `rules()`, validation Rule objects, serialized `Bus::batch()` callbacks and a Job's `failed()`. Never in Actions, services or controllers, and never `resolve()` or `app()->make()`.

## Start every Eloquent query with Model::query()
Write `Agent::query()->where(…)`, not `Agent::where(…)`. Give a condition a name (a scope or a query-builder method) instead of a comment that explains a `where` chain. No repository classes; `.ai/rules/models.md` says where queries live.

## Refuse business rules by throwing
When an Action or service refuses input, throw; never return an error value or a status string. Throw `Support\Exceptions\DomainValidationException($field, $message)` for a field error (`bootstrap/app.php` turns it into a 422), or a named `{Context}\Exceptions\…Exception` when callers must react differently. Never `abort()` or `ValidationException` outside HTTP code.

## Comments say why
Every class has a short docblock that says what it is and why it exists. Comments explain why, never what the code does: if a comment explains what, rename or extract instead. No history, tickets or people in comments.

## Leave formatting and types to the tools
Pint (default `laravel` preset) owns code style and Rector owns `declare(strict_types=1)` and type upgrades: run `vendor/bin/pint <files>` and `composer refactor`, never format by hand. Fix PHPStan errors in the code; never add a baseline or an `ignoreErrors` entry.

## Prefer interpolation to concatenation
Write `"{$agent->name} ({$count})"`, not `$agent->name.' ('.$count.')'`.
