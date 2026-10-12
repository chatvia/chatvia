# Cross-tenant data-leak pitfalls in single-database Laravel SaaS, and how teams prevent them (2023–2026 practice)

Scope note for the report writer: the target app is a single-database Laravel 13 SaaS where the tenant is a "workspace" (no tenancy package; workspace data keyed to the owner, per `/Users/ahmad/Sites/chatvia/CLAUDE.md`). The stancl/tenancy (archtechx) and spatie/laravel-multitenancy material is used as a reference for the failure modes those packages were built to solve. Every source was fetched or searched on 2026-10-12 unless marked otherwise. "Unverified" means the claim came from a search snippet, a secondary source, or my own knowledge, and was not confirmed against a primary page.

## 1. Queues and jobs losing tenant context (Context facade, spatie TenantAware, stancl queue bootstrapper, batches/chains/listeners/notifications, long-running workers and Octane)

### Takeaway
Queue workers do not run HTTP middleware, so the tenant has to travel with the job. Laravel's `Context` (hidden context) does this automatically at dispatch and resets per job. The packages do the same thing with a tenant id in the payload. Three things stay outside that protection: work that has no dispatching request (scheduler, console, closures), state that outlives a job (singletons, statics, config, mailer), and models restored by `SerializesModels`, which ignore global scopes.

### Cited Findings

**Laravel `Context` facade (Laravel 11+; docs for 13.x)**
- Failure mode: a job runs without the request's tenant information. Mitigation: Laravel captures the context when a job is dispatched and "hydrates" it back into the current context while the job runs: "When the job is dispatched, any information currently stored in the context is captured and shared with the job." — [Laravel 13.x Context docs](https://laravel.com/docs/13.x/context)
- "Hidden" context (`Context::addHidden()`) is not written to logs and is not returned by `Context::get()`. That makes it a good place for a tenant id that should travel with the job but stay out of log metadata. — [Laravel 13.x Context docs](https://laravel.com/docs/13.x/context#hidden-context)
- `Context::dehydrating()` and `Context::hydrated()` callbacks run at dispatch time and when the job starts. The docs' example restores `app.locale` on the queue so queued notifications use the right locale. The docs warn: "You should not use the `Context` facade within the `dehydrating` callback, as that will change the context of the current process." The same warning applies to `hydrated`. — [Laravel 13.x Context docs](https://laravel.com/docs/13.x/context#events)
- `Context::scope()` changes context only while a closure runs, then restores it. Objects changed inside the scope stay changed outside it. — [Laravel 13.x Context docs](https://laravel.com/docs/13.x/context#scoped-context)
- How it works (framework source, 13.x): the Context `Repository` is bound with `$this->app->scoped(...)`. `Queue::createPayloadUsing` writes the dehydrated context into the job payload under the key `illuminate:log:context`. A `JobProcessing` listener calls `Context::hydrate()` with that value, or with null when the payload has none. Console processes can be hydrated from a `__LARAVEL_CONTEXT` environment variable. — [ContextServiceProvider.php (13.x)](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Log/Context/ContextServiceProvider.php)
- Scoped bindings "will be flushed whenever the Laravel application starts a new 'lifecycle', such as when a Laravel Octane worker processes a new request or when a Laravel queue worker processes a new job." So per-tenant services bound with `scoped()` / `#[Scoped]` reset between jobs, but `singleton()` / `#[Singleton]` services do not. — [Laravel 13.x Service Container docs](https://laravel.com/docs/13.x/container#binding-scoped)

**spatie/laravel-multitenancy (v4 docs)**
- Global switch: `queues_are_tenant_aware_by_default`. When it is on, "the package will keep track of which tenant is the current one when a job is dispatched" and makes that tenant current inside the job. — [spatie docs: making queues tenant aware](https://spatie.be/docs/laravel-multitenancy/v4/basic-usage/making-queues-tenant-aware)
- Per job: the `Spatie\Multitenancy\Jobs\TenantAware` / `NotTenantAware` marker interfaces, or the `tenant_aware_jobs` / `not_tenant_aware_jobs` config arrays. Closures cannot implement the interfaces, so the docs suggest capturing the tenant and wrapping the work in `$tenant->execute()`. — [spatie docs](https://spatie.be/docs/laravel-multitenancy/v4/basic-usage/making-queues-tenant-aware)
- Fail closed: a tenant-aware job whose tenant cannot be found (for example, it was deleted) fails with `CurrentTenantCouldNotBeDeterminedInTenantAwareJob`. Non-tenant-aware jobs run with no current tenant. — [spatie docs](https://spatie.be/docs/laravel-multitenancy/v4/basic-usage/making-queues-tenant-aware)
- The previous current tenant is restored after a job finishes, whether it succeeded or failed. This matters for the `sync` connection and `dispatchSync()`, which run in the same process. `queue:retry` makes the failed job's tenant current for the retry and then restores the original. — [spatie docs](https://spatie.be/docs/laravel-multitenancy/v4/basic-usage/making-queues-tenant-aware)
- The page I fetched does not cover queued listeners, notifications, mailables, batches or chains (see Gaps).

**stancl/tenancy v3 (archtechx), QueueTenancyBootstrapper**
- It "adds the current tenant's ID to the queued job payloads" and "initializes tenancy based on this ID when jobs are being processed." `$forceRefresh` defaults to `false`. Setting it to `true` re-initializes tenancy for every job, which matters when tenant data changes between jobs. — [stancl/tenancy v3: tenancy bootstrappers](https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers)
- Failure mode: leftover global state. "Jobs dispatched from the central context will remain central," but the docs recommend "not to mix queue connections for central & tenant jobs due to potential leftover global state," for example "central jobs thinking they're in the previous tenant's context." — [stancl/tenancy v3: queues](https://tenancyforlaravel.com/docs/v3/queues)
- With the `database` queue driver plus the DB bootstrapper, pin `queue.connections.database.connection` to the central connection. With Redis, keep the queue connection out of `tenancy.redis.prefixed_connections`. — [stancl/tenancy v3: queues](https://tenancyforlaravel.com/docs/v3/queues)

**`SerializesModels` restoration bypasses global scopes (framework source)**
- `Model::newQueryForRestoration($ids)` returns `$this->newQueryWithoutScopes()->whereKey($ids)`. `newQueryWithoutScopes()` never calls `registerGlobalScopes()`. It does apply the model's `$with` and `$withCount`. — [Eloquent Model.php (13.x)](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Database/Eloquent/Model.php)
- Consequence (my reading of the source): a tenant global scope does not protect a model that a queued job restores. The job reloads exactly the row it was given, whatever tenant is current. That is fine when the id came from trusted code, and dangerous when the id came from user input that was never checked. I did not fetch `SerializesModels::restoreModel()` itself, so its call path into `newQueryForRestoration` is from memory (unverified for 13.x).

**Long-running workers and Octane (stale state between jobs or requests)**
- Octane "boots your application once, keeps it in memory." Service provider `register`/`boot` run once per worker. Injecting the container, the request, or the config repository into a singleton leaves "a stale version of the container or request on subsequent requests." "Octane will automatically handle resetting any first-party framework state between requests. However, Octane does not always know how to reset the global state created by your application." Static arrays leak across requests. Workers restart after 500 requests by default (`--max-requests`). — [Laravel 13.x Octane docs](https://laravel.com/docs/13.x/octane#dependency-injection-and-octane)
- Practitioner-reported pitfalls for queue workers (unverified, from search snippets of package READMEs/blogs, 2024–2026). Tenant state such as DB connection, cache prefix, filesystem path and facade singletons bleeds from one job into the next. The Mail facade keeps another tenant's SMTP settings. `queue:retry` does not restore the tenant. Batch `then()/catch()/finally()` callbacks run in the wrong tenant. A batch-repository singleton survives tenant switches. — [rylxes/laravel-tenant-jobs (Packagist)](https://packagist.org/packages/rylxes/laravel-tenant-jobs); [houlokmah/laravel-tenancy-batch-fix (Packagist)](https://root.packagist.org/packages/houlokmah/laravel-tenancy-batch-fix); [dev.to: Architectural blueprint, multi-tenant SaaS with Laravel](https://dev.to/software_solutions_740799/architectural-blueprint-building-a-multi-tenant-saas-application-with-laravel-2d51). These are small third-party packages, not authoritative.

**Real-world illustration of connection or worker state leaking across users (see section 6)**
- OpenAI, March 2023. In the redis-py client, a cancelled request could leave a pooled connection holding a response, which "the next response that's dequeued for an unrelated request can receive." Users saw other users' chat titles, and about 1.2% of active ChatGPT Plus users had billing details visible to others. — [Help Net Security](https://www.helpnetsecurity.com/2023/03/27/chatgpt-data-leak/); [Sonatype](https://www.sonatype.com/blog/openai-data-leak-and-redis-race-condition-vulnerability-that-remains-unfixed); OpenAI's own post ([openai.com/index/march-20-chatgpt-outage](https://openai.com/index/march-20-chatgpt-outage/)) returned HTTP 403 to my fetch, so its quotes come via secondaries.

### Inferences
- Catalogue of queue failure modes and the Laravel mitigation for each:
  1. **Tenant missing in the job.** Put the workspace id in the job's DTO or constructor (explicit). As a backup, add `Context::addHidden('workspace_id', …)` in middleware so it travels automatically. In the job, check that the DTO's workspace id matches every loaded model's owner.
  2. **Jobs that start outside a request (scheduler, console, `Bus::batch` callbacks, closures).** No request means no context. These must loop over workspaces explicitly and set the tenant per iteration, wrapping each iteration in `Context::scope()` or a `$tenant->execute()`-style helper. spatie's "fail if tenant missing" behavior is the safe default to copy.
  3. **Restored models ignore tenant scopes.** Never build a job from an id the user supplied unless ownership was checked first, which in this app's convention means the FormRequest or Action. Better still, re-check ownership in the Action the job calls. The app's rule that "Jobs: no logic … call an Action" (CLAUDE.md) concentrates that check in one place.
  4. **Leftover state in Horizon workers.** Anything holding a workspace id or a per-workspace credential should be `scoped()`, not `singleton()`. No static caches keyed by "current" workspace. Config set at runtime (for example a per-workspace mailer or LLM key) must be reset or set per job. Laravel only flushes scoped instances, not config values you `Config::set()`.
  5. **Batch, chain and listener callbacks.** Treat them as new entry points. Capture the workspace id in the closure by value and re-establish it. Do not rely on ambient state.
- The app is single-database and has no "current tenant" switcher. Its main queue risk is therefore wrong-owner writes and reads by id, not a wrong DB connection. The connection-switching warnings from stancl apply only by analogy (Redis prefixes, mailer config).

### Gaps
- I did not confirm whether `Context` propagates into chained jobs (each chain link is dispatched from inside the worker, so it plausibly re-captures the hydrated context) or into `Bus::batch` `then/catch/finally` callbacks. Test both directly.
- I did not confirm whether `Repository::hydrate(null)` fully clears the previous job's context. The scoped binding suggests a fresh instance per job, but I did not read `Repository.php`.
- spatie's behavior for queued listeners, notifications, mailables, batches and chains is not on the fetched page. Check spatie's GitHub issues.
- I found no official Laravel guidance that names multi-tenancy in the queue docs. All tenant-specific guidance comes from the packages.

## 2. Cache, Redis, session, storage/filesystem, broadcasting and Reverb scoping

### Takeaway
Laravel scopes none of these by tenant out of the box. Keys, paths and channel names must carry the workspace id, and every private channel callback must check ownership. Cache tags are not a tenant boundary on most drivers. `Cache::flush()` ignores prefixes. Channel model binding is not scoped automatically. Public channels need no authentication at all.

### Cited Findings

**Cache and Redis**
- "Cache tags are not supported when using the `file`, `dynamodb`, `database`, or `storage` cache drivers." Laravel's default cache driver is `database`. — [Laravel 13.x Cache docs](https://laravel.com/docs/13.x/cache#cache-tags)
- "Flushing the cache does not respect your configured cache 'prefix' and will remove all entries from the cache." — [Laravel 13.x Cache docs](https://laravel.com/docs/13.x/cache#removing-items-from-the-cache)
- Items stored with tags "may not be accessed without also providing the tags that were used to store the value." — [Laravel 13.x Cache docs](https://laravel.com/docs/13.x/cache#accessing-tagged-cache-items)
- stancl's `CacheTenancyBootstrapper` "adds tags with the current tenant's ids to each cache call". It requires "a cache store that supports tagging, e.g. Redis". The `RedisTenancyBootstrapper` "changes the Redis prefix for each tenant" only for direct Redis calls, "(not cache calls, direct Redis calls)", and needs phpredis ("predis won't work"). — [stancl/tenancy v3: bootstrappers](https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers)
- `Cache::memo()` keeps resolved values in memory "during a single request or job execution". — [Laravel 13.x Cache docs](https://laravel.com/docs/13.x/cache#cache-memoization)
- Post-incident mitigation from the OpenAI Redis leak: OpenAI "implemented redundant checks to ensure the data returned by our Redis cache matches the requesting user" (quoted via secondary). — [Help Net Security](https://www.helpnetsecurity.com/2023/03/27/chatgpt-data-leak/)

**Storage / filesystem**
- stancl's `FilesystemTenancyBootstrapper` suffixes `storage_path()` and every disk root listed in `tenancy.filesystem.disks` with the tenant key. The docs warn that suffixing the root alone "could cause unwanted behavior since Laravel does its own suffixing" (hence `root_override`). "Logs will be saved in `storage/logs` regardless." "With S3, overriding the disk roots is not necessary." — [stancl/tenancy v3: bootstrappers](https://tenancyforlaravel.com/docs/v3/tenancy-bootstrappers)

**Broadcasting (Reverb/Echo)**
- "Any visitor to your application may subscribe to a public channel without any authentication or authorization." — [Laravel 13.x Broadcasting docs](https://laravel.com/docs/13.x/broadcasting)
- Private channels: the `routes/channels.php` callback gets the authenticated user plus wildcard params and returns true/false. Example: `return $user->id === Order::findOrNew($orderId)->user_id;`. — [Laravel 13.x Broadcasting docs](https://laravel.com/docs/13.x/broadcasting#authorizing-channels)
- "Unlike HTTP route model binding, channel model binding does not support automatic implicit model binding scoping." — [Laravel 13.x Broadcasting docs](https://laravel.com/docs/13.x/broadcasting#authorization-callback-model-binding)
- Presence channels return "an array of data about the user", which is "made available to the presence channel event listeners". Anything returned there is visible to every member. — [Laravel 13.x Broadcasting docs](https://laravel.com/docs/13.x/broadcasting#authorizing-presence-channels)
- "Private channels ensure that only authorized users may listen on a channel. However, the event data itself still passes through your broadcasting service in plain text." (Encrypted channels are the documented option.) — [Laravel 13.x Broadcasting docs](https://laravel.com/docs/13.x/broadcasting)
- Channels authenticate with the default guard unless you pass `['guards' => [...]]`. If no user is authenticated, "channel authorization is automatically denied". — [Laravel 13.x Broadcasting docs](https://laravel.com/docs/13.x/broadcasting)
- For SPA/token auth, Sanctum's docs register channels via `->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])`. — [Laravel 13.x Sanctum docs](https://laravel.com/docs/13.x/sanctum#authorizing-private-broadcast-channels)

### Inferences
- Failure mode: a cache key built from a guessable id with no workspace segment, such as `agent:{id}:settings`, shares the value across workspaces if ids collide or are enumerated. Mitigation: build every tenant cache key with one helper that always prefixes the owner workspace id, and on read check that the cached payload's owner matches the requester. This copies OpenAI's "redundant check" (above).
- Failure mode: using `Cache::tags(['workspace:X'])` as the isolation mechanism. Tags are a grouping and invalidation feature, and they are unsupported on the default `database` driver. Use them for invalidation, not for isolation.
- Failure mode: `Cache::flush()` in a per-workspace "reset" path wipes every tenant's cache and, on a shared Redis, other apps' keys too. The user's memory notes that the production Redis is shared with other projects. Mitigation: delete by key pattern or tag. Never flush from tenant code.
- Failure mode: Reverb channels named by a raw sequential id, such as `conversation.{id}`, where the callback only checks "is authenticated". Mitigation: every private/presence callback must load the model and compare its owner workspace (or chatter/session) to the user. Use the non-guessable external id in the channel name. Widget and visitor channels need their own guard or token check, because the default guard is a dashboard user.
- Session: the app's hosted chat uses its own `chat` guard and a `/chat` cookie path (memory note). I found no Laravel docs page that discusses tenant scoping of sessions (see Gaps).
- Storage: the app's entity-first paths `users/{ext_id}/agents/{ext_id}/…` (memory note) match the stancl idea of a per-tenant prefix. The extra safeguard is to never accept a raw storage path from the client. Derive it from a model whose ownership was checked. Serve private files through signed, short-lived URLs created after authorization.

### Gaps
- I did not fetch Laravel's docs on `URL::temporarySignedRoute` / `Storage::temporaryUrl`, so I have no cited guidance on signed URL scoping.
- I found no authoritative source on session fixation or session sharing across tenants in single-DB apps.
- I did not check Reverb's docs on app-level isolation (multiple Reverb "apps") or on channel-name limits.

## 3. Database: unique indexes, global scopes not applied, route model binding IDOR, `exists:`/`unique:` rules, mass assignment, sequential ids

### Takeaway
Global scopes are a convenience, not a security boundary. Many paths skip them: validation rules, `DB::table`, `withoutGlobalScopes()`, model restoration in jobs and Livewire, early middleware and service providers, and route binding that is not scoped. Experienced teams back scopes with explicit ownership checks, tenant-aware validation rules, unguessable public ids, and database constraints that include the tenant column.

### Cited Findings

**Global scopes bypassed**
- Filament (5.x docs): "The global scopes are applied after the tenant has been identified from the request." Queries in early middleware or service providers are not scoped. Queries outside the tenancy-enabled panel "do not have access to the current tenant." `withoutGlobalScopes()` also removes the tenancy scope, and "it can lead to data leakage." — [Filament 5.x tenancy docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Laravel's `exists` rule with a model class, `'exists:App\Models\User,id'`, uses the model only to determine the table name. The `Rule::exists()->where(...)` callback receives an `Illuminate\Database\Query\Builder`, which is the query builder, not Eloquent. — [Laravel 13.x Validation docs](https://laravel.com/docs/13.x/validation#rule-exists)
- Filament states this directly: Laravel's `unique` and `exists` rules bypass global scopes by default. Use Filament's `scopedUnique()` / `scopedExists()`. — [Filament 5.x tenancy docs](https://filamentphp.com/docs/5.x/users/tenancy)
- `SerializesModels` restoration uses `newQueryWithoutScopes()` (see section 1). — [Eloquent Model.php (13.x)](https://raw.githubusercontent.com/laravel/framework/13.x/src/Illuminate/Database/Eloquent/Model.php)
- Livewire model restoration in relation managers, widgets and modal table selects does not apply global scopes by default (Filament docs). — [Filament 5.x tenancy docs](https://filamentphp.com/docs/5.x/users/tenancy)

**Validation rules that need a tenant clause**
- Documented pattern: `Rule::unique('users')->where(fn (Builder $query) => $query->where('account_id', 1))`, and the same for `Rule::exists('staff')->where(...)`. — [Laravel 13.x Validation docs](https://laravel.com/docs/13.x/validation#rule-unique)
- "You should never pass any user controlled request input into the `ignore` method … Otherwise, your application will be vulnerable to an SQL injection attack." — [Laravel 13.x Validation docs](https://laravel.com/docs/13.x/validation#rule-unique)
- The `unique` rule counts soft-deleted rows unless you call `->withoutTrashed()`. — [Laravel 13.x Validation docs](https://laravel.com/docs/13.x/validation#rule-unique)
- Array input: `Rule::forEach(fn ($value) => [Rule::exists(Company::class, 'id'), new HasPermission('manage-company', $value)])` shows a per-element authorization rule alongside `exists`. — [Laravel 13.x Validation docs](https://laravel.com/docs/13.x/validation#accessing-nested-array-data)

**Route model binding (IDOR across tenants)**
- Nested custom-key bindings (`/users/{user}/posts/{post:slug}`) are scoped to the parent automatically. Without a custom key you must opt in with `->scopeBindings()` (per route or `Route::scopeBindings()->group(...)`). `->withoutScopedBindings()` turns it off. You can override `resolveRouteBinding()` / `resolveChildRouteBinding()` on the model. — [Laravel 13.x Routing docs](https://laravel.com/docs/13.x/routing#implicit-model-binding-scoping)
- OWASP API1:2023 (BOLA) prevention: authorize "in every function that uses client input to access a record". Use "random and unpredictable values as GUIDs for records' IDs". Write authorization tests and do not deploy changes that break them. — [OWASP API Security Top 10 2023: API1 BOLA](https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization)

**Mass assignment and tenant id on create**
- Filament links new records to the current tenant through `creating`/`created` listeners, but only for models that have a tenant-aware resource. Other models need their own `creating` listener. — [Filament 5.x tenancy docs](https://filamentphp.com/docs/5.x/users/tenancy)

**Database constraints (composite keys including tenant_id)**
- Pattern: make `(tenant_id, id)` unique on the parent and have children reference `FOREIGN KEY (tenant_id, parent_id) REFERENCES parent (tenant_id, id)`. This makes it "physically impossible for a video in tenant A to reference a channel in tenant B." — [dev.to: multi-tenant video platform schema in PostgreSQL](https://dev.to/ahmet_gedik778845/building-a-multi-tenant-video-platform-schema-that-scales-in-postgresql-53dn) (blog; date not checked)
- Trade-off: `ON DELETE SET NULL` on such a key tries to null `tenant_id` too and fails. A pgsql-hackers patch proposed a column list for `SET NULL`/`SET DEFAULT`. — [pgsql-hackers thread: ON DELETE SET NULL (<column_list>)](https://www.postgresql.org/message-id/CAF%2B2_SGRXQOtumethpuXhsyU%2B4AYzfKA5fhHCjCjH%2BjQ04WWjA%40mail.gmail.com) (mailing-list thread, likely pre-2023, older). I believe this shipped in PostgreSQL 15 as `ON DELETE SET NULL (col, …)`. That is unverified in this session.
- Composite keys stop structural leaks (bad references being stored). Row-level security stops query leaks (a forgotten `WHERE tenant_id`). They complement each other. — [dev.to: SQLAlchemy composite foreign keys for multi-tenant](https://dev.to/uaslimcreate/sqlalchemy-composite-foreign-keys-for-multi-tenant-hierarchical-permissions-enforcing-tenantid-n9b) (blog)

### Inferences
- Catalogue (DB):
  1. **Unique index without the tenant column** (for example a globally unique `slug` or `email`). One workspace's value blocks another's, and the error message reveals that the value exists elsewhere, which is a small enumeration leak. Mitigation: `UNIQUE (workspace_id, slug)` in the create migration, plus `Rule::unique(...)->where('user_id', $ownerId)`. Exception: values that are global by design (such as a hosted-chat public slug) should stay globally unique on purpose, with a generic error message.
  2. **`exists:` rule without a tenant clause.** A request can attach another workspace's record (for example `agent_id` or `source_id`) that passes validation. Mitigation: `Rule::exists(...)->where('user_id', $owner)`, or resolve the id through the owner's relationship (`$workspace->agents()->whereKey($id)->firstOrFail()`) inside the FormRequest. The same bug class as Filament GHSA-7q3w (section 6).
  3. **`DB::table` / raw SQL / reporting queries.** No Eloquent scopes apply. Mitigation: confine raw queries to query classes that take the owner id as a required argument, and ratchet them with an architecture test.
  4. **Top-level route binding `{agent}` with no tenant check.** Implicit binding resolves any row. Mitigation: route middleware or policy (`can.access.agent`) on every route group, `scopeBindings()` for nested params, and lookups by the scoped `external_id`. The project's `ConventionsTest` already enforces "scoped `external_id` lookups", per CLAUDE.md.
  5. **Mass assignment of the owner column.** If `user_id` / `workspace_id` is fillable and the DTO is built from request input, a client can set it. Mitigation: never take the owner from input. Set it from the authenticated workspace inside the Action. Keep the owner column out of `$fillable` or out of DTO constructors built from `validated()`.
  6. **Sequential ids.** They make BOLA probing cheap. Mitigation: unguessable public ids (the app uses `external_id` uuid7). Note that uuid7 is time-ordered and partly predictable, so it reduces enumeration but does not replace an authorization check. OWASP lists unguessable ids as a supplement to per-request authorization.
  7. **Child rows pointing to another tenant's parent.** For example a chunk referencing another workspace's source, or a pivot linking agent A to tool B. Mitigation: composite FKs `(owner_id, parent_id)`, or at minimum Action-level checks plus a test. This is a larger schema change. In a "not released yet, edit the create migration" policy it is cheap to add now and expensive later.

### Gaps
- I did not find an authoritative Laravel source that discusses `$fillable` / `$guarded` and tenant columns specifically.
- I found no Laravel-specific write-up on composite tenant foreign keys in migrations. Laravel's schema builder supports `$table->foreign(['a','b'])->references(['a','id'])`, but I did not verify that in this session.
- I did not research PostgreSQL RLS with Laravel (setting `app.current_tenant` per connection and resetting it in long-lived workers).

## 4. Authorization: policies missing tenant checks, `Gate::before` overreach, API tokens not bound to a tenant, Filament tenancy limits

### Takeaway
Laravel's authorization is per user, not per tenant. `Gate::before` returning `true` short-circuits every check, tenant checks included. Sanctum tokens belong to a user model, with no built-in tenant binding. Filament's tenancy scoping only covers resources inside a tenancy-enabled panel and has documented Livewire gaps.

### Cited Findings
- `Gate::before`: "If the `before` closure returns a non-null result that result will be considered the result of the authorization check." `Gate::after` cannot override a gate or policy result unless that result was `null`. Inline `Gate::allowIf/denyIf` "does not execute any defined 'before' or 'after' authorization hooks." — [Laravel 13.x Authorization docs](https://laravel.com/docs/13.x/authorization#intercepting-gate-checks)
- A policy's `before` "will not be called if the class doesn't contain a method with a name matching the name of the ability being checked." — [Laravel 13.x Authorization docs](https://laravel.com/docs/13.x/authorization#policy-filters)
- `Response::denyAsNotFound()` / `denyWithStatus(404)` let you hide existence instead of returning 403. — [Laravel 13.x Authorization docs](https://laravel.com/docs/13.x/authorization#gate-responses)
- Sanctum: tokens are issued by a model using `HasApiTokens` (the User). Abilities are stored on the token. "`tokenCan` … will always return `true` if the incoming authenticated request was from your first-party SPA." The docs say policies must check both the ability and ownership, for example `$request->user()->id === $server->user_id && $request->user()->tokenCan('server:update')`. Tokens never expire by default. — [Laravel 13.x Sanctum docs](https://laravel.com/docs/13.x/sanctum#token-abilities)
- Filament tenancy: "If implemented partially or incorrectly, data belonging to one tenant may be exposed to another tenant." A model is scoped only if it has a tenant-aware resource in that panel. Livewire-restored records in "relation managers, widgets, or modal table selects" are not re-queried through global scopes. The docs suggest an `ApplyTenantScopes` middleware with `isPersistent: true` so it also runs on Livewire requests. `$isScopedToTenant` / `scopeToTenant(false)` control the default. — [Filament 5.x tenancy docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Filament AttachAction/AssociateAction Select fields (GHSA-7q3w-xqjw-g3cr, CVE-2026-48067, CWE-639). `recordSelectOptionsQuery()` limited the options shown, but validation did not apply the same limit. A user could change Livewire state and submit an out-of-scope id. Fixed in filament/actions 4.11.4 and 5.6.4, and filament/tables 3.3.51. — [GitHub Advisory GHSA-7q3w-xqjw-g3cr](https://github.com/advisories/GHSA-7q3w-xqjw-g3cr)
- OWASP BOLA: object-level authorization belongs "in every function that uses client input to access a record". — [OWASP API1:2023](https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization)

### Inferences
- Failure mode: a super-admin `Gate::before(fn ($u) => $u->isAdmin() ? true : null)` also grants the admin every workspace's data through the normal dashboard and API. It also hides bugs in tests run as admin. Mitigation: keep staff access in a separate panel and guard (this app uses Filament with `UserType::Admin` and `canAccessPanel`, per memory). Never add a global `Gate::before` that returns `true`. If one exists, exclude tenant-data abilities and write tests that run as a non-admin teammate.
- Failure mode: a policy checks a role ("is editor") but not that the model belongs to the user's workspace. Mitigation: every policy method that takes a model starts with an owner check (`$model->user_id === $user->workspace()->id`), and returns `denyAsNotFound()` so existence is not revealed. Under the user's "no denial logging" rule, do not log the probe.
- Failure mode: a Sanctum token issued to a teammate keeps working after the teammate leaves, or after their membership is narrowed. Tokens are bound to the user, not the workspace. Mitigation: resolve the workspace and permissions on every request from current membership, not from token abilities alone, and revoke tokens when membership is deleted. The memory note "API permission parity" says `/api/v1` already runs the dashboard permission middleware. A test should cover "teammate removed, token now gets 404/403".
- Failure mode: the internal MCP endpoint trusts a caller-supplied workspace header. This is the same bug class as the Asana MCP incident. Mitigation already in the conventions: caller identity through an injected `McpCallerContext`, "never read `X-Chatvia-*` headers" (CLAUDE.md). The memory note says `VerifyInternalMcpRequest` has a workspace mode via `X-Chatvia-Workspace-Id`. Check that this header is only accepted together with a server-side signature or secret, never alone.

### Gaps
- I found no official Laravel guidance on binding Sanctum tokens to a team or tenant. Jetstream's team-scoped API tokens were not researched.
- I did not research Laravel Nova or other admin packages for similar Livewire/model-restoration issues.

## 5. Testing strategies: Pest arch tests, two-tenant feature tests, static analysis, DB constraints

### Takeaway
Teams layer the defenses. Architecture tests enforce structure (every tenant model has the scope or trait, no raw `DB::table` in tenant code, scoped lookups). Feature tests create two workspaces and prove that workspace B gets 404/403 or empty results for workspace A's ids. Database constraints catch what code misses. OWASP explicitly recommends authorization tests that block deployment.

### Cited Findings
- OWASP BOLA: "Write tests to evaluate the vulnerability of the authorization mechanism. Do not deploy changes that make the tests fail." — [OWASP API1:2023](https://api-security.owasp.org/editions/2023/en/0xa1-broken-object-level-authorization)
- Filament asks you to verify scoping yourself: "If in doubt, please check if your queries are properly scoped." — [Filament 5.x tenancy docs](https://filamentphp.com/docs/5.x/users/tenancy)
- spatie fails jobs closed when the tenant is missing (`CurrentTenantCouldNotBeDeterminedInTenantAwareJob`), which makes missing context visible in tests and failed-job logs. — [spatie docs](https://spatie.be/docs/laravel-multitenancy/v4/basic-usage/making-queues-tenant-aware)
- Practitioner advice (unverified, from search-result snippets): keep isolation tests in CI, test that queued jobs re-establish tenant context, and use a "safeguard mode that throws exceptions if you accidentally query without a tenant scope". — [laraplugins: rylxes/laravel-multitenancy](https://laraplugins.io/plugins/rylxes/laravel-multitenancy); [dev.to: Stop data leaks, tenant scopes in Laravel](https://dev.to/iprajapatiparesh/stop-data-leaks-tenant-scopes-in-laravel-2kok)
- Composite FKs as a DB-level backstop (section 3). — [dev.to: multi-tenant PostgreSQL schema](https://dev.to/ahmet_gedik778845/building-a-multi-tenant-video-platform-schema-that-scales-in-postgresql-53dn)
- Project context: the app already runs Pest architecture tests (`tests/Architecture/ConventionsTest.php`) that enforce layering, Action/Job rules, listener registration, resource-or-invokable controllers and "scoped `external_id` lookups", plus shrink-only allowlists. — `/Users/ahmad/Sites/chatvia/CLAUDE.md` (local project doc)

### Inferences
- Suggested test matrix for a single-DB "workspace" app:
  1. **Two-workspace HTTP tests per resource route.** Create workspaces A and B, act as B, hit A's external ids on show/update/delete/nested routes, and assert 404 (or 403) with no data in the body. Generate these from the route list so new routes are covered automatically (a "route coverage" architecture test). The user's memory notes Sanctum caches the first user across requests in one test. Call `app('auth')->forgetGuards()` between identities.
  2. **Validation tests.** For each FormRequest with an id field, submit B's id while acting as A and assert a validation error.
  3. **Job tests.** Dispatch with workspace A's DTO, run the job in a process where context was set for B (or cleared), and assert that it acts only on A, or fails closed. Include `Bus::batch` callbacks and scheduled commands.
  4. **Broadcast channel tests.** Call each `routes/channels.php` callback with a user from another workspace and assert `false`. Also confirm that no tenant event uses a public `Channel`.
  5. **Cache tests.** Assert that every tenant cache key contains the owner id (wrap the cache in a key builder and assert in an architecture test that `Cache::` is not called with literal keys in `Domain\`).
  6. **Arch rules (Pest `arch()`).** Every model with an owner column uses the owner trait or scope. No `DB::table(` or `withoutGlobalScopes(` in `src/Domain` outside an allowlist. No `Rule::exists(`/`Rule::unique(` without `->where(` in FormRequests (token-based check). Keep these as ratchets with allowlists, matching the project's style.
  7. **Static analysis.** A custom PHPStan/Larastan rule can flag `Model::find($request->...)` / `whereKey($input)` without an owner constraint. I found no ready-made rule (see Gaps).

### Gaps
- I did not fetch Pest's arch-testing docs, so the exact expectation names (`toUseTrait`, `toHaveAttribute`, `toOnlyBeUsedIn`, etc.) and their behavior in Pest 5 are unverified.
- I found no published Larastan/PHPStan rule set specific to tenant scoping.
- I found no Laravel-specific write-up from a recognized security firm on testing tenant isolation. The available material is package docs and dev.to posts.

## 6. Real incidents and advisories (Laravel packages and general SaaS)

### Takeaway
The Laravel-specific advisories from 2026 are about one bug class: a UI-limited option list or search that the server did not re-scope (Filament Attach/Associate, Curator picker search). The large SaaS incidents show the same classes at scale. In Asana (2025) a tenant-isolation check in a new AI/MCP endpoint was flawed. In OpenAI (2023) shared connection or cache state crossed user boundaries. I found no published GHSA for archtechx/tenancy.

### Cited Findings
- **Filament GHSA-7q3w-xqjw-g3cr / CVE-2026-48067 (Moderate, CVSS 6.5, CWE-639).** `AttachAction`/`AssociateAction` Select fields validated a different scope than the options shown, so a modified Livewire state could submit an out-of-scope record. Affected: filament/actions 4.0.0–4.11.3 and 5.0.0–5.6.3, and filament/tables 3.0.0–3.3.50. Fixed: 4.11.4, 5.6.4 and 3.3.51. Published on the Filament repository 2026-05-23, added to GitHub's advisory database 2026-06-11. The advisory does not mention tenants specifically, but the same flaw would cross tenants when the options query is the tenant scope. — [GitHub Advisory](https://github.com/advisories/GHSA-7q3w-xqjw-g3cr); [Packagist filament/actions advisories](https://packagist.org/packages/filament/actions/advisories)
- **awcodes/filament-curator cross-tenant media metadata disclosure.** "In multitenant panels, an authenticated user could retrieve other tenants' media metadata by typing in the picker search box." Introduced in v3.2.4 (when multitenancy support arrived). Fixed in 3.7.8, 4.1.1 and 5.1.1. CVSS 5.0 (`AV:N/AC:L/PR:L/UI:N/S:C/C:L/I:N/A:N`), CWE-284. The GHSA/CVE was "to be linked once published". The release date is approximate (about mid-2026). — [newreleases.io: filament-curator v3.7.8](https://newreleases.io/project/github/awcodes/filament-curator/release/v3.7.8)
- **Asana MCP server (June 2025).** A logic flaw in the tenant-isolation check of Asana's new MCP server (launched May 2025) exposed data across organizations. Asana found it on 2025-06-04, disabled MCP from June 5 to 17, notified about 1,000 customers on June 16, and reset all MCP connections. Exposure was limited to data types the user could already see in their own org, and covered task details, project metadata, team info, comments and files. It was an internal bug, not an external attack. — [UpGuard](https://upguard.com/blog/asana-discloses-data-exposure-bug-in-mcp-server); [Security Boulevard](https://securityboulevard.com/2025/06/saas-security-alert-asana-mcp-server-data-exposure-incident/); [SANS NewsBites](https://www.sans.org/newsletters/newsbites/xxvii-47); [Nudge Security](https://www.nudgesecurity.com/post/asana-mcp-server-data-exposure-incident). Only Adversa claims the mechanism was cached results crossing orgs, and it also names affected companies that no other source confirms. Treat both as unverified. — [Adversa AI](https://adversa.ai/blog/asana-ai-incident-comprehensive-lessons-learned-for-enterprise-security-and-ciso)
- **OpenAI ChatGPT (2023-03-20).** A redis-py bug (CVE-2023-28858 and CVE-2023-28859, fixed in 4.5.3) let a cancelled request leave data on a pooled connection that the next unrelated request read. A server change raised the cancellation rate. Users saw other users' chat titles, and about 1.2% of active Plus users had name, email, billing address, card last 4 and expiry visible to others during a roughly 9-hour window. Mitigations: "redundant checks to ensure the data returned by our Redis cache matches the requesting user", plus Redis cluster hardening. — [Help Net Security](https://www.helpnetsecurity.com/2023/03/27/chatgpt-data-leak/); [Security Affairs](https://securityaffairs.com/?p=144057); [Sonatype](https://www.sonatype.com/blog/openai-data-leak-and-redis-race-condition-vulnerability-that-remains-unfixed). The primary [OpenAI post](https://openai.com/index/march-20-chatgpt-outage/) returned 403 to my fetch.
- **archtechx/tenancy (stancl).** My searches found no GHSA. — search only; the [archtechx GitHub org](https://github.com/archtechx) was surfaced but its security tab was not checked.
- **Older (pre-2023):** CVE-2021-32645 is listed against a product called "Tenancy Multi-Tenant", described as a "multi-domain controller for the Laravel web framework" (probably the older hyn/tenancy `tenancy/multi-tenant` package). Not verified, and the details were not read. — [Vumetric vulnerability listing](https://cyber.vumetric.com/vulns/tenancy)

### Inferences
- The mapping to this app is direct:
  - **Asana's bug:** a new MCP surface with its own tenant check. The app has built-in MCP servers, an internal account-analytics MCP with a workspace mode, and connector/MCP tool routing. Each MCP tool needs the same two-workspace tests as HTTP routes, and the caller workspace must come from server-side context, never from tool arguments or headers.
  - **OpenAI's bug:** shared Redis plus cached per-user state. The app keeps live chat memory (`ChatMemory`) in cache and runs Horizon/Reverb on a Redis shared with other apps. Including the owner or session id in each key, and checking the owner on read, is the cheap safeguard.
  - **Filament's bug:** the UI limits a select, the server validates globally. The app's equivalent is any Inertia/Vue picker (agents, sources, tools, forms, teammates) whose submit endpoint uses `exists:` without an owner clause.
- I found no public postmortem of a cross-tenant leak caused specifically by a Laravel queue worker. The queue risks in section 1 are well documented by the tenancy packages, but the evidence from incidents is indirect.

### Gaps
- I did not check GitHub's advisory database directly for `spatie/laravel-multitenancy`, `stancl/tenancy` or `archtechx/tenancy`. The searches surfaced nothing, but that is not proof of absence.
- Search snippets mentioned CVE-2026-48019 (laravel/framework 12.x before 12.60.0 and 13.x before 13.10.0, "bypass the security policy"). I did not investigate it and do not know whether it bears on tenancy. Check the advisory before relying on it.
- I could not read OpenAI's primary postmortem (HTTP 403). The figures come from secondary coverage that agrees closely across outlets.
- Asana's formal post-mortem was "available on request" and was not found publicly.
