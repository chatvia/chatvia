---
paths:
  - 'src/Domain/**/Actions/**'
  - 'src/App/Identity/**/Actions/**'
---

# Actions

## One use case, one Action, one execute()
An Action does one thing the app does and is named for it: `{Verb}{Noun}Action` (`CreateAgentAction`, `TakeoverConversationAction`). It lives in `{Context}/Actions/{Feature}/` and has exactly one public method, `execute()` (never `handle()` or `__invoke()`).

## Skip the Action when there is no logic
A read is not an Action: put it in a `{Context}/Queries/{Noun}Query`, a query-builder method, or the read-only controller. A single Eloquent write with no rules needs no Action either. Add an Action when there is real logic: rules, several writes, side effects, or reuse from the dashboard, the API, jobs and commands.

## Take a DTO and models; return a result
Input is a `…Data` DTO plus the models it acts on (`execute(CreateAgentData $data): Agent`). Up to three typed scalars are fine. Never take a `Request`, an associative array or a boolean flag that switches behaviour (write two Actions or use an enum). Return a model, a DTO, a Collection, a scalar that is the real result (a count, a new id, a yes/no) or `void`; never an array or an HTTP response.

## Stay unaware of HTTP and the session
No `request()`, `session()`, `auth()`, `Auth::`, `response()`, `redirect()`, `abort()` or `->notify()` inside an Action. Who is acting comes in on the DTO or as a model argument, so the same Action runs from a controller, the API, a job, a command or a test.

## Wrap multi-step writes in a transaction
Put writes to more than one row in `DB::transaction()`, and use `lockForUpdate()` when two requests can race for the same row. Dispatch jobs after the transaction returns; when the Action may run inside a caller's transaction, use `Job::dispatch(…)->afterCommit()`, and delete files in `DB::afterCommit()`.

## Side effects go through events
Mail, notifications, broadcasts, CRM pushes and usage counts happen in listeners, not in the Action. Dispatch with `HandedOff::dispatch(…)`, not `event(new HandedOff(…))`.

## Compose Actions through the constructor
An Action that needs another Action or a service injects it as a promoted `private readonly` constructor parameter and calls its `execute()`. Never `new` or `app()` an Action inside an Action; the only exception is a serialized `Bus::batch()` callback.

## Run in the background through a Job
To run an Action on the queue, write a thin `{Verb}{Noun}Job` that receives models or the DTO and calls the Action from `handle()`. Don't add a queueable-action package.

## Every Action has a test
Test it directly, with no HTTP: `app(CreateAgentAction::class)->execute(new CreateAgentData(…))`, then check the returned value, the database and the side effects. Cover at least one refusal path and check that nothing changed. `ActionsHaveTestsTest` fails for an Action without a test.
