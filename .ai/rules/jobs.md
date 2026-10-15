---
paths:
  - 'src/**/Jobs/**'
  - 'src/**/Listeners/**'
  - 'src/**/Events/**'
  - 'src/Domain/**/Middleware/**'
  - config/horizon.php
  - config/queue.php
---

# Jobs, events and listeners

## A job only calls an Action
A job's `handle()` method-injects an Action (and any services) and calls `execute()`. No business logic, transactions or row locks in a job. Name it `{Verb}{Noun}Job` in `{Context}/Jobs/`; use `Queueable` (plus `Batchable` when batched) and no other queue traits.

## Pass data, not services
The constructor takes only models, DTOs or ids, as promoted `public readonly` properties. Capture state that may change at dispatch time, so the job is self-contained. Pass an id when the record may be deleted before the job runs, then re-fetch it and return early if it is gone; otherwise pass the model and add `#[DeleteWhenMissingModels]`.

## Configure jobs with attributes
Use Laravel 13 queue attributes: `#[Queue('exports')]`, `#[Tries(3)]`, `#[Backoff(10, 60, 300)]`, `#[Timeout(120)]`, `#[MaxExceptions(3)]`, `#[UniqueFor(600)]`. Don't write `public int $tries` / `$timeout` properties or call `$this->onQueue()` in the constructor, unless the queue is chosen per instance. Give each setting a one-line reason.

## Pick the queue on purpose
Put a job on an existing queue: `messages` (chat replies), `default` (short work), `analytics`, `ingestion.*`, `exports` (long deletes and exports), `voice`. The queue name picks the Horizon supervisor. A job whose timeout is over 300 seconds must sit on a long supervisor's queue, below that connection's `retry_after`. A new queue needs a supervisor, a `waits` entry and a line in `HorizonConfigTest`.

## Never repeat billed or sent work
Work that bills a message or sends a reply gets one try, with a comment saying why. Retryable work must be safe to repeat: each step only does what is left. Stop duplicate dispatch with `ShouldBeUnique`, a namespaced `uniqueId()` built from entity ids, and `#[UniqueFor]` so a lock cannot stick.

## Dispatch after commit
Dispatch from Actions, listeners and observers, never from controllers. Dispatch after `DB::transaction()` returns, or use `->afterCommit()` when a caller's transaction may be open. Queued listeners set `public bool $afterCommit = true`.

## Batches set their own queue
`Bus::batch()` ignores each job's own queue, so every batch calls `->onQueue()`. Batched jobs return early on `$this->batch()?->cancelled()`. Batch callbacks are serialized: capture scalars only and reload models inside.

## Handle failures the user can see
When a job owns a status the user sees (an ingesting source, a data export), implement `failed()` and mark the record failed through an Action (`app(MarkSourceFailedAction::class)`), so nothing hangs in "processing".

## Throttle outside APIs in job middleware
Rate-limit calls to a third party with job middleware in `{Context}/Middleware/` (`Redis::throttle()` and a jittered `release()`, like `ThrottleFirecrawl`). The job bounds itself with `retryUntil()` and `#[MaxExceptions]`, so releases don't use up its tries.

## No mutable static state
Workers live long: keep no mutable static state in code a job reaches. A static cache may hold only values that never change, with a comment saying so. After editing code a job reaches, Horizon must restart before the change applies.

## Events and listeners
Events are named in the past tense (`HandedOff`) and dispatched with `HandedOff::dispatch(…)`. Listeners are action phrases with no `Listener` suffix. Register every listener by hand in `DomainServiceProvider::registerEventListeners()`; there is no auto-discovery, so an unregistered listener never fires.
