---
paths:
  - 'src/**/*.php'
---

# Src

## Use filled() / blank() for empty checks
Check for an empty value with Laravel's `filled()` / `blank()` (on a request: `$request->filled('key')`, then `string()` / `integer()`). Never write `is_string($x) && $x !== ''`, `$x !== null && $x !== ''`, `$x === null || $x === ''` or `trim($x) === ''`.
Keep `is_string()` only when the value can really be another type (raw request input before validation, outside JSON, LLM output, a validation rule's `mixed $value`), and pair it with `filled()`: `is_string($x) && filled($x)`. For a header use `$request->headers->get()` (typed `?string`), not `header()`.
Exceptions: streamed text deltas (a lone space or newline is real output, so compare with `''`), and values where an empty array is real data (`filled([])` is false).
