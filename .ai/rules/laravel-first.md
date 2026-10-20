---
paths:
  - 'src/**/*.php'
  - 'tests/**'
  - 'database/**'
  - 'config/**'
  - 'routes/**'
---

# Laravel first

When Laravel has a helper for the job, use it instead of the plain PHP function. The framework helpers are null-safe, read left to right, and tests can fake the facades. Older code still has plain PHP calls; do not copy them, and convert the ones in lines you touch.

## Strings: Str
Use `Str::` for one call and `Str::of($text)` / `str($text)` for a chain: `Str::contains()`, `Str::startsWith()`, `Str::endsWith()`, `Str::lower()`, `Str::upper()`, `Str::limit()`, `Str::squish()`, `Str::slug()`, `Str::before()` / `after()` / `between()`, `Str::replace()`, `Str::substr()`, `Str::length()`, `Str::wordCount()`, `Str::trim()`. For patterns use `Str::match()`, `Str::matchAll()`, `Str::isMatch()` and `Str::replaceMatches()`, not `preg_*`. Not `str_contains`, `strtolower`, `substr`, `mb_substr`, `ucfirst`, `trim`, `str_replace`, `explode` + `implode` round trips.
Ids and secrets: `Str::uuid7()`, `Str::ulid()`, `Str::random()`, `Str::password()`; never `uniqid()`, `rand()` or `md5(microtime())`.
Exception: `Support\Text\Utf8` and other byte-level code that must work on raw bytes.

## Arrays: collections and Arr
Chain with collections: `collect($items)->filter()->map()->values()`, never nested `array_map(array_filter(…))`. For a single step on a plain array use `Arr::`: `Arr::map()`, `Arr::where()`, `Arr::first()`, `Arr::pluck()`, `Arr::only()`, `Arr::except()`, `Arr::wrap()`, `Arr::get()` / `data_get()` for nested keys. Not `array_map`, `array_filter`, `array_column`, `array_key_exists` + manual default, `in_array` loops.
Return a `Collection` from domain code, not an array you built with a `foreach` and `$result[] = …`. Use a `LazyCollection` (`File::lines()`, `->lazy()`, `->cursor()`) for large files and large queries.
Keep a plain array when an API needs one (validation rules, config, `->toArray()` at an HTTP or JSON edge).

## Local files: File; disks: Storage
Paths on this machine (temp files, `storage_path()`, a converter's output) go through the `File` facade: `File::get()`, `File::put()`, `File::exists()`, `File::isFile()`, `File::delete()`, `File::ensureDirectoryExists()`, `File::deleteDirectory()`, `File::lines()`, `File::mimeType()`, `File::extension()`. Never `file_get_contents`, `file_put_contents`, `fopen`, `unlink`, `mkdir`, `is_file`, `file_exists`, `glob`, `rmdir`.
Files that belong to a workspace, user or chatter live on a disk: `Storage::disk('private')`, never a raw path. Temp files and folders come from `create_temp_file()` / `create_temp_dir()` in `src/Support/helpers.php`.

## Outside programs: Process
Run a binary with the `Process` facade (`Process::timeout(…)->run([...])`, `Process::pool()`), with the command as an array, not a string. Never `exec`, `shell_exec`, `system`, `passthru`, `proc_open` or Symfony's `Process` class. Tests use `Process::fake()`.

## HTTP: Http
Call outside services with `Http::` (timeouts, `retry()`, `throw()`, `->json()`). Never `curl_*`, `file_get_contents($url)` or a Guzzle client you built yourself. Tests use `Http::fake()`.

## URLs: Uri and url()
Read and build URLs with `Illuminate\Support\Uri` (`Uri::of($url)->host()`, `->withQuery()`), `url()`, `route()` and `SafeUrl` for user-given URLs. Not `parse_url`, `http_build_query` or string concatenation.

## Time: now() and Carbon
Use `now()`, `today()`, `Carbon::parse()` and `CarbonImmutable`, and compare with `->isPast()`, `->diffInSeconds()`, `->addMinutes()`. Never `time()`, `date()`, `strtotime()`, `microtime()` or `new DateTime`. Tests freeze time with `$this->travelTo()` / `Carbon::setTestNow()`.
Wait with `Sleep::for(2)->seconds()` (fakeable with `Sleep::fake()`), never `sleep()` / `usleep()`.

## Numbers: Number
Format with `Number::format()`, `Number::fileSize()`, `Number::percentage()`, `Number::abbreviate()`, `Number::clamp()`. Not `number_format()` or hand-written byte maths.

## JSON
Let Laravel encode and decode: model casts (`'array'`, `AsCollection`, `AsArrayObject`, a `Data` cast), `$response->json()`, `$request->json()`, `response()->json()`. When you must call `json_encode` / `json_decode` yourself, pass `JSON_THROW_ON_ERROR`.

## Small framework helpers
Reach for these before writing the same thing by hand: `filled()` / `blank()` (see `.ai/rules/src.md`), `optional()` / `?->`, `value()`, `tap()`, `with()`, `rescue()` instead of an empty `try`/`catch`, `retry()` instead of a retry loop, `once()` instead of a static cache property, `throw_if()` / `throw_unless()`, `abort_if()` / `abort_unless()` (HTTP code only), `Cache::lock()` instead of `flock()`, `Log::` instead of `error_log()`, `Hash::` / `Crypt::` instead of `password_hash()` / `openssl_*`, `Context::` for values every log line in a request needs, `Pipeline` for a chain of steps, `Benchmark::measure()` instead of `microtime()` maths.

## Check the docs first
Before writing a helper, search the Laravel docs (`search-docs` in Laravel Boost) for one that already exists. A new helper goes in a class in its context, or in `src/Support/` when it is generic; never in a new global helpers file.
