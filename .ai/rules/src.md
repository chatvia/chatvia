---
paths:
  - 'src/**/*.php'
---

# Src

## Use filled() / blank() for empty checks
Check for an empty value with Laravel's `filled()` / `blank()` (on a request: `$request->filled('key')`, then `string()` / `integer()`). Never write `is_string($x) && $x !== ''`, `$x !== null && $x !== ''`, `$x === null || $x === ''` or `trim($x) === ''`.
Never write `is_string()`, not even paired with `filled()` (`ConventionsTest` fails on one). Check the value with `filled()` / `blank()` alone. Tell a string-or-array value apart with `is_array()` (or `Arr::wrap()`), compare a `string|false` result (`base64_decode()`, `openssl_*`, `putFile()`) with `false`, and in a validation rule's `mixed $value` take `is_scalar($value)` before casting it. For request input, read it typed (`$request->string('key')`); for a header use `$request->headers->get()` (typed `?string`), not `header()`.

## Cast only what is not a string yet
Don't `(string)` a value that is already a string. For a `?string`, say what null becomes: `$app->redirectUri() ?? ''` (and `$x ??= ''`), not `(string) $app->redirectUri()`. Keep `(string)` for a real conversion: an int, a `mixed` value from outside JSON, an enum's `->value` going into a string.
Exceptions: streamed text deltas (a lone space or newline is real output, so compare with `''`), and values where an empty array is real data (`filled([])` is false).

## Choose boring code over clever code
Prefer an early return, `if` or `match` to nested ternaries, and drop the `else` after a `return`. More lines for more clarity is a good trade. Reach for the framework helper first: `abort_if()` / `abort_unless()`, `findOrFail()`, `Cache::remember()`, and `Str`, `Arr`, collections, `File`, `Process`, `Http` as `.ai/rules/laravel-first.md` lists.

## Pipe a value through plain PHP calls with |>
When one value goes through two or more calls in a row, chain them with the pipe operator (`|>`). Don't nest the calls, and don't assign the same variable again after each step. Put each step on its own line, starting with `|>`. A function that takes only the value is a first-class callable: `|> trim(...)`. Any other step is a typed arrow function in parentheses (PHP requires them): `|> (static fn (string $text): string => …)`. Name the parameter for what it holds, not `$x`.
Use a `Str::of()` or collection chain instead when Laravel covers every step (`.ai/rules/laravel-first.md`). Use the pipe when a step has no Laravel version (`html_entity_decode()`, `preg_replace()` with a list of patterns). Don't pipe a single call. Keep a variable when its name explains a step.

```php
$text = $html
    |> strip_tags(...)
    |> (static fn (string $text): string => html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
```

## Build only what is needed now
Add an interface, factory, manager or wrapper class only for a second real implementation, a test fake, or a context boundary. A class that wraps one call is noise: no `GetAgentsAction` around `Agent::query()->active()->get()`. A well-named scope called from a controller is already clean.

## Name things for what they hold and do
Variables say what they hold (`$activeAgents`, not `$data`), with no abbreviations; plural for collections, singular for one item. Methods are camelCase and start with a verb. Methods and local variables that answer yes/no start with `is`, `has`, `can`, `should`, `supports` or `needs`. Constants are SCREAMING_SNAKE_CASE; a class that only lists choices is an enum.

## Type every constant
Every class, interface, trait and enum constant states its visibility and its type: `public const string PREFIX = 'cvh1.';`, `private const int MAX_RETRIES = 3;`, `protected const array KEYS = […];`. Never write a bare `const PREFIX = …` or an untyped `public const PREFIX = …`. An override of a framework constant takes a type too: `public const ?string UPDATED_AT = null;`.

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
