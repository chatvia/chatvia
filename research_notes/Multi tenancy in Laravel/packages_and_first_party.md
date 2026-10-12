# Laravel multi-tenancy packages and first-party team features (state as of 2026-10-12)

Research date: 2026-10-12. Version numbers and dates come from Packagist metadata (`repo.packagist.org/p2/*.json`), GitHub release and tag APIs, and official docs fetched on that date. When a source is older or conflicts with another source, the note says so.

## 1. stancl/tenancy ("Tenancy for Laravel", archtechx): v3 vs v4 status, Laravel 12/13 support, licensing

### Takeaway
v3 is the only **tagged** line. The latest is v3.10.1 (2026-08-05), which supports Laravel 10–13. v4 is the `master` branch: very active, and its docs are live at v4.tenancyforlaravel.com, which say it "was released in 2026". However, no `v4.*` git tag or Packagist release exists as of 2026-10-12, so installing v4 means pinning `dev-master`. The core package is MIT and "always free". Paid products are sold beside it: the SaaS boilerplate, an e-book, and sponsor-only docs (for example, Filament integration recipes).

### Cited Findings
- Latest stable on Packagist: **v3.10.1, 2026-08-05**. It requires `laravel/framework ^10.0|^11.0|^12.0|^13.0` and `php ^8.0`. v3.10.0 (2026-03-18) added Laravel 13. v3.9.0 (2025-02-25) added Laravel 12 and dropped Laravel 9 — [Packagist p2 metadata](https://repo.packagist.org/p2/stancl/tenancy.json); [GitHub release v3.10.0](https://github.com/archtechx/tenancy/releases/tag/v3.10.0); [v3.9.0](https://github.com/archtechx/tenancy/releases/tag/v3.9.0)
- The v3.10.1 release note reads "[3.x] Laravel 13.24 support (backport #1476)" — [GitHub release v3.10.1](https://github.com/archtechx/tenancy/releases/tag/v3.10.1)
- No `v4*` or `4*` tags exist in the repo. Packagist dev versions list `dev-master` (updated 2026-10-12) and `3.x-dev` (2026-08-05) — [GitHub tags](https://github.com/archtechx/tenancy/tags); [Packagist ~dev metadata](https://repo.packagist.org/p2/stancl/tenancy~dev.json)
- `master` (= v4) `composer.json`: `"license": "MIT"`, `"php": "^8.4"`, `"illuminate/support": "^12.0|^13.0"`. It depends on `stancl/jobpipeline: 2.0.0-rc7` (a pre-release dependency) — [composer.json on master](https://github.com/archtechx/tenancy/blob/master/composer.json)
- The repo's default branch is `master`, last pushed 2026-10-12, with 4,421 stars, not archived. The README points docs to `https://v4.tenancyforlaravel.com` — [GitHub repo](https://github.com/archtechx/tenancy)
- Recent master commits (2026-08 to 2026-10) include "[4.x] resource syncing: force deleting…" (#1485, 2026-09-21), "[MINOR BC] Fix FilesystemTenancyBootstrapper…" (#1473, 2026-09-08) and "Correct job order in `DeletingTenant` pipeline" (#1492, 2026-10-06). Minor breaking changes are still landing — [GitHub commits on master](https://github.com/archtechx/tenancy/commits/master)
- v4 roadmap issue: on 2024-03-16 stancl wrote "v4 is now mostly finished". On 2024-12-31 he wrote "public beta is now available 🎉" (announced on Discord) — [Issue #654](https://github.com/archtechx/tenancy/issues/654)
- The v4 docs intro says: "This documentation is for version 4 which was released in 2026." — [v4 docs: Introduction](https://v4.tenancyforlaravel.com/introduction/). **This conflicts with** the absence of any v4 tag on GitHub or Packagist ([Packagist](https://packagist.org/packages/stancl/tenancy)).
- The v4 "What's new" page is marked "WIP" and says "This page doesn't cover all new v4 features." — [v4 docs: What's new](https://v4.tenancyforlaravel.com/version-4)
- Licensing and monetisation: "The core package will always be free and open-source." Paid "premium products" are a Multi-Tenant SaaS Boilerplate ("56 different setups"), the e-book "Multi-Tenant Laravel in Production", and "Exclusive Content for Sponsors" (a separate docs site) — [v4 docs: Sponsoring](https://v4.tenancyforlaravel.com/sponsoring/)
- The sponsor-only docs include "Filament in the Tenant App", "Universal (Central & Tenant) Filament", "Billable Tenants with Cashier", "Central (SSO-like) Authentication", "Frictionless Testing Setup" and "Laravel Cloud Integration" — [v4 docs nav (Introduction page)](https://v4.tenancyforlaravel.com/introduction/)
- Historical (2022): v4 early access was limited to experienced users giving feedback, with backers to come next "weeks before 4.0.0 is released" — [archte.ch blog, June 2022](https://archte.ch/blog/june-2022-update/) (older information)
- Adoption: 10.95M total installs, about 1.03M per month — [Packagist](https://packagist.org/packages/stancl/tenancy)

### Inferences
- For a Laravel 13 app today there are two choices. One is stable v3.10.1, which has no Postgres RLS and the old bootstrapper set. The other is `dev-master`/v4, which has the newest features but is untagged and still taking "[MINOR BC]" changes. Pinning a commit hash would be the safe way to use v4.
- v4 needs PHP ^8.4. A PHP 8.5 app meets this.
- The Filament integration recipes are sponsor-gated, so integrating with Filament 5 may need either a sponsorship or your own work.

### Gaps
- I could not find an official v4.0.0 release date or announcement. The docs say "released in 2026", but no tag exists. Discord announcements were not accessible.
- Exact sponsorship prices or tiers are not on the sponsoring page.
- I could not confirm whether v4 early access was ever paywalled after the public beta (2024-12-31). Current evidence points to a free MIT core on `master`.

## 2. stancl/tenancy single-database tenancy: `BelongsToTenant`, `BelongsToPrimaryModel`, `HasScopedValidationRules`, global scope, Postgres RLS

### Takeaway
Single-database mode is opt-in. You disable the DB bootstrapper and the DB-creation jobs. Scoping is done by an Eloquent global scope (`TenantScope`) that a trait adds to "primary" models. It only applies while tenancy is initialized, and only to Eloquent: the `DB` facade and validation rules are not scoped. v4 adds **PostgreSQL Row-Level Security**: a dedicated non-owner DB user, a session variable, and auto-generated policies. When RLS is used, it replaces the Eloquent scope. The docs call it "still considered somewhat experimental".

### Cited Findings
- Setup (v3): disable `DatabaseTenancyBootstrapper` and the `CreateDatabase`/`MigrateDatabase`/`SeedDatabase` jobs on `TenantCreated`. Other bootstrappers (cache, filesystem) may stay on. Model types are tenant, primary (belong directly), secondary (belong through a parent) and global — [v3 docs: Single-database tenancy](https://tenancyforlaravel.com/docs/v3/single-database-tenancy)
- `TenantScope::apply()` (v4 source): `if (! tenancy()->initialized) return;` then `$builder->where($model->qualifyColumn(Tenancy::tenantKeyColumn()), tenant()->getTenantKey())`. `extend()` registers a `withoutTenancy` macro that is `withoutGlobalScope(TenantScope::class)` — [TenantScope.php (master)](https://github.com/archtechx/tenancy/blob/master/src/Database/TenantScope.php)
- `BelongsToTenant` (v4): `use FillsCurrentTenant`; defines a `tenant(): BelongsTo` relation to `config('tenancy.models.tenant')`. On Laravel 13 it registers the scope via `static::whenBooted(...)`. It adds `TenantScope` **only if** RLS is not handling scoping, that is, unless `TraitRLSManager::$implicitRLS` is on or the model implements `RLSModel` — [BelongsToTenant.php (master)](https://github.com/archtechx/tenancy/blob/master/src/Database/Concerns/BelongsToTenant.php)
- Unscoped when there is no current tenant (for example, a central admin panel). v3 column config: `BelongsToTenant::$tenantIdColumn = 'team_id'`, which is global, so "you can't use both `team_id` and `tenant_id`" — [v3 docs](https://tenancyforlaravel.com/docs/v3/single-database-tenancy)
- v4 column config: `tenancy.models.tenant_key_column`. Changing it also affects `HasDomains` (`domains.tenant_id`) — [v4 docs: Single-database tenancy](https://v4.tenancyforlaravel.com/single-database-tenancy/)
- `BelongsToPrimaryModel` (secondary models): implement `getRelationshipToPrimaryModel()` (for example, returning `'post'`). Direct queries like `Comment::all()` are scoped through the parent. The docs advise accessing secondary models via the parent instead. Tertiary and deeper chains: "We do not provide a solution for tertiary (and more indirect) relations out of the box." — [v3 docs](https://tenancyforlaravel.com/docs/v3/single-database-tenancy); [v4 docs](https://v4.tenancyforlaravel.com/single-database-tenancy/)
- v4 docs: scoping applies only to "select many" queries. Specific instances are not enforced. `FillsCurrentTenant` fills the tenant id automatically when a primary model is created — [v4 docs: Single-database tenancy](https://v4.tenancyforlaravel.com/single-database-tenancy/)
- `HasScopedValidationRules` (on the Tenant model): `unique($table, $column)` returns `new Unique(...)->where(tenantKeyColumn, $this->getTenantKey())`, and `exists()` does the same with `Exists`. Usage: `$tenant->unique('posts')` / `$tenant->exists('posts')` — [HasScopedValidationRules.php (master)](https://github.com/archtechx/tenancy/blob/master/src/Database/Concerns/HasScopedValidationRules.php); [v3 docs](https://tenancyforlaravel.com/docs/v3/single-database-tenancy)
- Warnings: "DB facade queries, or any similarly low level queries, will not be scoped." "Laravel's built-in validation rules use simple DB queries, not models." Unique indexes must include `tenant_id`, for example `['tenant_id','slug']` — [v4 docs: Single-database tenancy](https://v4.tenancyforlaravel.com/single-database-tenancy/)
- Postgres RLS (v4 only):
  - `tenants:rls` creates a non-owner DB user (`TENANCY_RLS_USERNAME`/`PASSWORD`) and generates policies.
  - `PostgresRLSBootstrapper` switches the default connection to a `tenant` connection that uses that user, and sets a session variable (default `my.current_tenant`). On revert it RESETs the variable and reconnects to central.
  - Setup: disable `DatabaseTenancyBootstrapper` and `DatabaseSessionBootstrapper`, and remove the DB jobs.
  - Source: [v4 docs: PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)
- Bootstrapper source: `bootstrap()` runs `$this->database->statement("SET {$tenantSessionKey} = '{$tenant->getTenantKey()}'")`. This is a session-level `SET`, not `SET LOCAL`. `revert()` runs `RESET …` and `reconnectToCentral()` — [PostgresRLSBootstrapper.php (master)](https://github.com/archtechx/tenancy/blob/master/src/Bootstrappers/PostgresRLSBootstrapper.php)
- RLS managers:
  - `TableRLSManager` (default) walks foreign keys to find the shortest path from each table to `tenants`. It needs no code changes and supports unlimited indirection. Opt-out per column with a `no-rls` comment. `$scopeByDefault = false` switches to opt-in via an `rls` comment. Comment constraints such as `->comment('rls users.id')` stand in for a missing foreign key.
  - `TraitRLSManager` uses the `BelongsToTenant`/`BelongsToPrimaryModel` traits and supports at most 2 levels.
  - Source: [v4 docs: PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)
- `FORCE ROW LEVEL SECURITY` is applied by default (`CreateUserWithRLSPolicies::$forceRls = true`). It can be turned off for hosts that cannot grant `BYPASSRLS`, such as Laravel Cloud — [v4 docs: PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)
- RLS caveats:
  - "Once RLS policies are created for a table, it's impossible to modify it until the policy is dropped." Use `tenancy()->dropRLSPolicies()` in migrations, and run `tenants:rls` after every `migrate`.
  - A central user without `BYPASSRLS` gets `unrecognized configuration parameter "my.current_tenant"`.
  - Deep FK paths produce heavy policy queries, so add indexes or a direct `tenant_id`.
  - Source: [v4 docs: PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)
- Maturity labels conflict. The "What's new" page says RLS is "still an experimental feature" ([v4 What's new](https://v4.tenancyforlaravel.com/version-4)), and the single-DB page says "still considered somewhat experimental" ([v4 single-DB](https://v4.tenancyforlaravel.com/single-database-tenancy/)). The RLS page itself carries no experimental label ([v4 RLS](https://v4.tenancyforlaravel.com/postgres-rls/)). An active branch named `configurable-force-rls-2` exists — [GitHub branches](https://github.com/archtechx/tenancy/branches)

### Inferences
- The Eloquent-scope part is small: one `Scope` class, about 20 lines, plus a trait with a `creating` filler. It is easy to copy without adopting the package.
- The RLS bootstrapper uses session-level `SET` on a persistent connection. That can leak tenant context with PgBouncer in transaction-pooling mode, or with long-lived workers (Octane, Horizon) if `revert()` is skipped after an exception. A team copying the idea for PostgreSQL 18 should consider `SET LOCAL` / `set_config(..., true)` inside a transaction, or session pooling. This is my analysis, not a documented package warning.
- The tenant key is interpolated into the SQL string. That is safe only because keys are app-generated. Copying the pattern should use `set_config('my.current_tenant', ?, false)` with a binding.
- "Scoping applies only to select-many queries" means route-model binding by id must still be guarded by authorization or a scoped binding.

### Gaps
- I did not fetch the v3 docs pages for "tenant identification" and "bootstrappers". The v3 lists in section 3 come from the 3.x source tree.
- There is no benchmark data on RLS overhead in the docs.

## 3. stancl/tenancy: identification (domain, subdomain, path, header, custom), bootstrappers, users across tenants

### Takeaway
Both v3 and v4 ship domain, subdomain, domain-or-subdomain, path and request-data identification middleware. v4 adds `Origin`-header and cookie identification, early (kernel) identification, universal routes and route cloning. v3 bootstrappers are Database, Cache, Filesystem, Queue and Redis. v4 adds about 14 more (URL, mail, broadcasting, session, Scout, Fortify, Postgres RLS, …). Neither the v3 nor the v4 single-DB docs address users belonging to several tenants. The relevant patterns are "resource syncing" (multi-DB) and the sponsor-only "Central (SSO-like) Authentication".

### Cited Findings
- v3 (3.x branch) middleware: `InitializeTenancyByDomain`, `…BySubdomain`, `…ByDomainOrSubdomain`, `…ByPath`, `…ByRequestData`, `PreventAccessFromCentralDomains`, `ScopeSessions`, `CheckTenantForMaintenanceMode`. Resolvers: `DomainTenantResolver`, `PathTenantResolver`, `RequestDataTenantResolver`. Bootstrappers: `CacheTenancyBootstrapper`, `DatabaseTenancyBootstrapper`, `FilesystemTenancyBootstrapper`, `QueueTenancyBootstrapper`, `RedisTenancyBootstrapper` — [3.x source tree](https://github.com/archtechx/tenancy/tree/3.x/src)
- v4 middleware adds `InitializeTenancyByOriginHeader` (for SPA frontends calling an API) and `PreventAccessFromUnwantedDomains` — [master source tree](https://github.com/archtechx/tenancy/tree/master/src/Middleware); [v4 docs: Tenant identification](https://v4.tenancyforlaravel.com/tenant-identification/)
- v4 `RequestDataTenantResolver` config: `header` (default `X-Tenant`), `query_parameter` (default `tenant`), `cookie` (default `tenant`). Setting one to null disables it. `tenant_model_column` is also available — [v4 docs: Tenant identification](https://v4.tenancyforlaravel.com/tenant-identification/)
- v4 `PathTenantResolver`: `tenant_parameter_name` (default `tenant`), `tenant_model_column`, and `allowed_extra_model_columns` (needed for `{tenant:slug}`) — [v4 docs: Tenant identification](https://v4.tenancyforlaravel.com/tenant-identification/)
- Resolver cache is off by default (`cache => false`, TTL 3600). On Laravel 13+ the cached tenant classes must be allowed via `cache.serializable_classes`. Failure throws `TenantCouldNotBeIdentifiedException`, overridable per middleware with `::$onFail` — [v4 docs: Tenant identification](https://v4.tenancyforlaravel.com/tenant-identification/)
- v4 routing features: early identification (kernel middleware, `default_route_mode`, `'central'`/`'tenant'` route middleware), route cloning (`tenant.foo`), universal routes, the `SingleDomainTenant` interface, and session scoping — [v4 docs: What's new](https://v4.tenancyforlaravel.com/version-4); [v4 nav](https://v4.tenancyforlaravel.com/introduction/)
- v4 bootstrapper classes on master: `BroadcastChannelPrefix`, `BroadcastingConfig`, `CacheTags` (v3's tag-based cache bootstrapper, renamed), `CacheTenancy` (prefix-based), `DatabaseCache`, `DatabaseSession`, `DatabaseTenancy`, `FilesystemTenancy`, `LogChannel`, `MailConfig`, `PersistentQueueTenancy`, `PostgresRLS`, `QueueTenancy`, `RedisTenancy`, `RootUrl`, `TenantConfig`, `UrlGenerator`, `Integrations/FortifyRoute`, `Integrations/ScoutPrefix` — [master Bootstrappers dir](https://github.com/archtechx/tenancy/tree/master/src/Bootstrappers)
- v4 queue change: tenant context is reset after each job and `$forceRefresh` is removed. `PersistentQueueTenancyBootstrapper` keeps the v3 behaviour — [v4 docs: What's new](https://v4.tenancyforlaravel.com/version-4)
- Other v4 APIs: `Tenant::current()` / `currentOrFail()`, `tenancy()->reinitialize()`, `tenant:tinker`, per-tenant maintenance (`tenants:down/up`), UUIDv7/ULID key generators, pending tenants, and impersonation hardening — [v4 docs: What's new](https://v4.tenancyforlaravel.com/version-4)
- Users across tenants: the v4 single-DB page "doesn't address whether users should be tenant-scoped, multi-tenant user membership, or central users" (checked on fetch). v4 resource syncing (multi-DB) syncs central resources such as users into tenant DBs. It uses a polymorphic `tenant_resources` pivot and `global_id` — [v4 docs: Single-database tenancy](https://v4.tenancyforlaravel.com/single-database-tenancy/); [v4 docs: What's new](https://v4.tenancyforlaravel.com/version-4)
- The "Central (SSO-like) Authentication" recipe is sponsor-only — [sponsor docs index](https://sponsors.tenancyforlaravel.com/)

### Inferences
- In single-DB mode, stancl's model is that a `users` table that is not scoped is "global". Many-to-many membership (one user in several tenants) is left to the app: a pivot plus the identification middleware. The package gives no `HasTenants`-style membership API, unlike Filament or the starter kits.
- Path or request-data identification fits an app that uses no subdomains. Header identification (`X-Tenant`) fits an API.

### Gaps
- I found no public doc text on "central vs tenant users" for single-DB. It may be in the sponsor-only docs or the e-book, which I could not access.

## 4. spatie/laravel-multitenancy (v4)

### Takeaway
The current version is v4.2.1 (2026-09-22), for Laravel 11–13 and PHP ^8.2. v4.0.0 shipped 2024-07-24. The package is deliberately minimal ("bare essentials", "unopinionated"). It decides the current tenant (`TenantFinder`), runs `SwitchTenantTask`s on switch, and makes queued jobs tenant-aware. It supports single- and multi-DB. It ships **no automatic `tenant_id` query scoping**: there is no `BelongsToTenant` equivalent, only connection traits. Single-DB isolation of rows is up to the app.

### Cited Findings
- Versions: 4.2.1 (2026-09-22), 4.2.0 (2026-08-07), 4.1.x (2026-02 to 2026-07). It requires `laravel/framework ^11.0|^12.0|^13.0` and `php ^8.2` — [Packagist p2](https://repo.packagist.org/p2/spatie/laravel-multitenancy.json)
- 4.0.0 was published 2024-07-24 and introduced the `IsTenant` contract ("Contract IsTenant replace Tenant uses", #544) — [GitHub release 4.0.0](https://github.com/spatie/laravel-multitenancy/releases/tag/4.0.0)
- 4.2.0: "Processing a job no longer leaves the surrounding code with a different current tenant than it had before" (#639, #643). This matters for `sync` / `dispatchSync()` — [GitHub releases](https://github.com/spatie/laravel-multitenancy/releases)
- Philosophy: "it should only provide the bare essentials to enable multitenancy". The package is "unopinionated" and supports single and multiple databases — [Spatie docs v4: Introduction](https://spatie.be/docs/laravel-multitenancy/v4/introduction)
- The single-DB install page covers only the `tenants` landlord table, isolation of cache and filesystem via tasks, and the queue option. It says nothing about query scoping — [Spatie docs v4: Using a single database](https://spatie.be/docs/laravel-multitenancy/v4/installation/using-a-single-database)
- Source tree: the only model concerns are `UsesTenantConnection`, `UsesLandlordConnection` and `ImplementsTenant`. There is no scope or trait that filters by tenant id. `UsesTenantConnection::getConnectionName()` returns the tenant connection name — [src tree](https://github.com/spatie/laravel-multitenancy/tree/main/src); [UsesTenantConnection.php](https://github.com/spatie/laravel-multitenancy/blob/main/src/Models/Concerns/UsesTenantConnection.php)
- `IsTenant` interface: `static current(): ?static`, `static checkCurrent(): bool`, `static forgetCurrent()`, `makeCurrent(): static`, `forget()`, `isCurrent()`, `getDatabaseName()`, `newCollection()`, `execute(callable)`, `callback(callable)` — [IsTenant.php](https://github.com/spatie/laravel-multitenancy/blob/main/src/Contracts/IsTenant.php)
- Config keys: `tenant_finder` (default `null`; it must extend `TenantFinder`; `DomainTenantFinder` ships), `switch_tenant_tasks` (`PrefixCacheTask`, `SwitchTenantDatabaseTask`, `SwitchRouteCacheTask`, all commented out by default), `tenant_model`, `queues_are_tenant_aware_by_default => true`, `tenant_database_connection_name`, `landlord_database_connection_name`, `current_tenant_context_key => 'tenantId'`, `current_tenant_container_key => 'currentTenant'`, overridable `actions`, and a `queueable_to_job` map covering mailables, notifications, closures, queued listeners and broadcast events — [config/multitenancy.php](https://github.com/spatie/laravel-multitenancy/blob/main/config/multitenancy.php); [TenantFinder dir](https://github.com/spatie/laravel-multitenancy/tree/main/src/TenantFinder)
- Middleware: `NeedsTenant`, `EnsureValidTenantSession`. Events: `MakingTenantCurrentEvent`, `MadeTenantCurrentEvent`, `ForgettingCurrentTenantEvent`, `ForgotCurrentTenantEvent`, `TenantNotFoundForRequestEvent`. Command: `tenants:artisan` — [src tree](https://github.com/spatie/laravel-multitenancy/tree/main/src)
- Queues:
  - With the default `true`, the current tenant is stored at dispatch and made current in the job.
  - Opt in with the `TenantAware` marker interface or `tenant_aware_jobs`. Opt out with `NotTenantAware` or `not_tenant_aware_jobs`.
  - A missing tenant throws `CurrentTenantCouldNotBeDeterminedInTenantAwareJob`. The previous tenant is restored after the job, success or failure.
  - Source: [Spatie docs v4: Making queues tenant aware](https://spatie.be/docs/laravel-multitenancy/v4/basic-usage/making-queues-tenant-aware)
- Adoption: 4.27M total installs, about 317k per month — [Packagist](https://packagist.org/packages/spatie/laravel-multitenancy)

### Inferences
- In a single-DB Postgres app, Spatie would provide only "current tenant" context plus tenant-aware jobs (via the Laravel `Context` key `tenantId`) and cache prefixing. The app still needs its own `BelongsToTenant`-style global scope, scoped validation and tests.
- Spatie's queue-awareness design is the most reusable part: the marker interfaces, the config lists, and restoring the prior tenant after a job.

### Gaps
- I did not fetch the docs page on how the tenant id is carried in the job payload. The config shows it uses `current_tenant_context_key` (Laravel Context), but the exact mechanism is not verified from the docs.

## 5. Filament 5 tenancy (`->tenant()`, `HasTenants`, ownership, registration/profile, global scopes and limits)

### Takeaway
Filament v5 (latest v5.10.1, 2026-10-07) has panel-level tenancy built for **many-to-many** user↔team membership. You set `->tenant(Team::class, slugAttribute:, ownershipRelationship:)`. The user implements `HasTenants` (`getTenants`, `canAccessTenant`) and optionally `HasDefaultTenant`. Resources in the panel get an automatic tenant global scope, and records get the tenant FK on create. Filament explicitly limits this: the scope exists only inside the tenancy-enabled panel after tenant resolution, and Filament "does not provide any guarantees about the security of your application".

### Cited Findings
- Version: `filament/filament` v5.10.1 (2026-10-07), v5.10.0 (2026-10-06), v5.9.0 (2026-09-26). It requires `php ^8.2` — [Packagist p2](https://repo.packagist.org/p2/filament/filament.json)
- Scope: Filament tenancy targets many-to-many (users belong to many teams and switch). For one-to-many, the docs suggest a global scope plus an observer on `team_id` instead — [Filament 5.x docs: Tenancy](https://filamentphp.com/docs/5.x/users/tenancy)
- Panel config: `->tenant(Team::class)`, with optional `slugAttribute:` (slug in URLs) and `ownershipRelationship:` (the default ownership relation on resource models) — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- `HasTenants` on User: `getTenants(Panel $panel)` (feeds the switcher) and `canAccessTenant(Model $tenant)` (must check membership so users cannot reach other tenants by guessing IDs in URLs). `HasDefaultTenant::getDefaultTenant(Panel $panel)` picks the tenant after login. Without it, the first of `getTenants()` is used — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Pages: `->tenantRegistration(RegisterTeam::class)` extends `RegisterTenant` (`form()`, `handleRegistration()`). Users with no tenant are redirected there. `->tenantProfile(EditTeamProfile::class)` extends `EditTenantProfile` — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Current tenant: `Filament::getTenant()` — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- URLs: by default `/admin/{tenant id or slug}`. `->tenantRoutePrefix('team')` adds a segment. `->tenantDomain('{tenant:slug}.example.com')` gives subdomains, and `'{tenant:domain}'` matches the full host. A domain setup registers a global `tenant` route pattern (`[a-z0-9.\-]+`), which "can conflict with other panels or routes" — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Ownership: by convention the tenant has a plural relation (`posts()`) and the resource model a singular one (`team()`). Override per resource with `$tenantOwnershipRelationshipName` / `$tenantRelationshipName`. On create, Filament sets the FK through `creating`/`created` listeners — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Scoping: resources in a tenancy-enabled panel get an automatic global scope. It covers list tables and URL record resolution; non-matching records return 404. Opt out with `protected static bool $isScopedToTenant = false`. Invert the default with `Resource::scopeToTenant(false)`. Bypass one query with `withoutGlobalScope(filament()->getTenancyScopeName())` — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Models without resources: add your own global scope in middleware registered via `->tenantMiddleware([...], isPersistent: true)`. `isPersistent` also runs it on Livewire AJAX requests — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Documented security limits:
  - "Filament does not provide any guarantees about the security of your application."
  - Queries before tenant resolution (early middleware, service providers) are not scoped. Queries outside the panel have no tenant and are not scoped.
  - "If any of your queries disable all global scopes, the tenancy global scope will be disabled as well."
  - "This protection does not extend to records passed into relation managers, widgets, or modal table selects."
  - Laravel `unique`/`exists` ignore global scopes, so use `scopedUnique()` / `scopedExists()`.
  - Muting model events can break automatic tenant association.
  - Source: [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)
- Billing: `filament/spark-billing-provider` with `->tenantBillingProvider(new SparkBillingProvider())`, or implement a custom `BillingProvider` (`getRouteAction()`, `getSubscribedMiddleware()`). `->requiresTenantSubscription()` gates the panel. Tenant display: `HasName`, `HasAvatar`, `HasCurrentTenantLabel` — [Filament docs](https://filamentphp.com/docs/5.x/users/tenancy)

### Inferences
- Filament tenancy is a **panel feature, not an app-wide tenancy layer**. In an Inertia/Vue dashboard + API + queue app with Filament used only as a staff/admin panel, Filament tenancy would not protect the main surfaces. A cross-tenant admin panel would normally **not** enable `->tenant()` at all.
- If a customer-facing Filament panel is added later, `HasTenants::canAccessTenant()` must reuse the app's existing membership check, so there is one source of truth.

### Gaps
- Exact Filament 5 class namespaces for `HasTenants` / `HasDefaultTenant` (I believe `Filament\Models\Contracts\…`) were not re-verified in the 5.x source.

## 6. Laravel Jetstream teams and Laravel Spark team billing

### Takeaway
Jetstream teams use `teams` (with an owner `user_id` and a `personal_team` flag), a `team_user` pivot with a nullable `role` string, `team_invitations`, `users.current_team_id`, the `HasTeams` trait and roles defined in code via `Jetstream::role()`. Jetstream is **no longer recommended**. Laravel 12's release notes say "Laravel Breeze and Laravel Jetstream will no longer receive additional updates", and the Jetstream README says it is "for Laravel 11.x and prior". Even so, Jetstream v5.5.x (2026) still declares Laravel 13 compatibility. Spark bills teams by switching the billable to `Team`, with `resolve`/`authorize` closures and `Cashier::useCustomerModel(Team::class)`.

### Cited Findings
- "With the introduction of our new application starter kits, Laravel Breeze and Laravel Jetstream will no longer receive additional updates." The same notes give Laravel 12's release as February 24th 2025 and Laravel 13's as "Q1 2026" — [Laravel 12.x release notes](https://laravel.com/docs/12.x/releases)
- Jetstream README: "This starter kit is for Laravel 11.x and prior. For our latest starter kits, check out: https://laravel.com/starter-kits" — [laravel/jetstream README](https://github.com/laravel/jetstream)
- **Conflict / nuance:** Packagist shows Jetstream v5.5.3 (2026-05-19) and v5.5.0 (2026-03-05) requiring `^11.0|^12.0|^13.0`. Compatibility bumps continued in 2026 despite the "no additional updates" statement. It still has about 1.0M installs per month — [Packagist p2](https://repo.packagist.org/p2/laravel/jetstream.json); [Packagist](https://packagist.org/packages/laravel/jetstream)
- Jetstream schema (stub migrations):
  - `teams`: `id`, `user_id` (indexed owner), `name`, `personal_team` boolean, timestamps.
  - `team_user`: `id`, `team_id`, `user_id`, `role` nullable string, timestamps, `unique(['team_id','user_id'])`.
  - There is also a `team_invitations` migration.
  - Source: [Jetstream migrations](https://github.com/laravel/jetstream/tree/HEAD/database/migrations)
- Jetstream docs: every user gets a "Personal" team at registration (for example "Sally's Team"). `HasTeams` provides `currentTeam`, `allTeams()`, `ownedTeams`, `belongsToTeam($team)`, `teamRole($team)` and `hasTeamPermission($team, 'server:create')`. Roles are defined with `Jetstream::role('admin', 'Administrator', [...permissions])` in `JetstreamServiceProvider`. The docs warn: "Jetstream's team scaffolding and opinions may not work for every application." — [Jetstream docs: Teams (source)](https://github.com/laravel/jetstream-docs/blob/HEAD/src/features/teams.md)
- Spark team billing (Stripe edition cookbook):
  - Config: `config/spark.php` `'billables' => ['team' => ['model' => Team::class, 'plans' => [...]]]`.
  - `Spark::billable(Team::class)->resolve(fn (Request $request) => $request->user()->currentTeam)`.
  - `->authorize(...)` checks the current user's `id` equals the team's `user_id` (owner).
  - `Cashier::useCustomerModel(Team::class)` in `SparkServiceProvider::boot()`.
  - The Spark columns migration is renamed to target `teams`, and the `subscriptions` table uses `team_id` instead of `user_id`.
  - The billables key becomes the portal URL slug (`/billing/team`).
  - Source: [Spark docs: Stripe cookbook](https://spark.laravel.com/docs/spark-stripe/cookbook); slug detail also from [search summary of Spark configuration docs](https://spark.laravel.com/docs/spark-stripe/configuration)

### Inferences
- Jetstream's "owner = `teams.user_id`" shape is what Spark's cookbook `authorize()` assumes. The new starter kits (section 7) model ownership as a role in `team_members` instead.
- Jetstream should be treated as legacy reference code, not a dependency, for a Laravel 13 app.

### Gaps
- A search summary claimed Spark's Stripe edition supports only one billable model type per application. The cookbook page fetched did **not** confirm this, so treat it as unverified.
- I did not verify the current Spark version or Laravel 13 support. Spark is a private package, not on public Packagist.

## 7. Current official Laravel starter kits (Laravel 12/13 era): teams/organizations; WorkOS AuthKit variant

### Takeaway
**New in 2026:** the official React, Svelte, Vue and Livewire starter kits can now be generated **with teams**, via `laravel new --teams` or the installer prompt. This was first merged into Laravel's kit-builder repo (`laravel/maestro`, "feat: Teams support", PR #14) on 2026-03-27 and is documented in the Laravel 13.x starter-kit docs.

What it gives you:
- `teams` (with slug, `is_personal`, soft deletes), `team_members` (role string), and `team_invitations` (code, expiry).
- `users.current_team_id`.
- Owner/Admin/Member role and permission enums.
- `{current_team}` slug-prefixed routes with `URL::defaults`, plus a membership middleware.

The WorkOS AuthKit variant (social, passkeys, Magic Auth, SSO) also has a `workos-teams` flavour. That flavour uses the same **local** teams tables, not WorkOS Organizations.

### Cited Findings
- Laravel 13.x docs, "Teams" section: "The React, Svelte, Vue, and Livewire starter kits may also be generated with team support. When the teams feature is enabled, each user belongs to one or more teams and has a current team. During registration, new users are automatically given a personal team." The kits include screens to create and switch teams, invite members and update team details — [Laravel 13.x docs: Starter Kits](https://laravel.com/docs/13.x/starter-kits)
- Routing: "the dashboard route becomes `/{current_team}/dashboard`, while team management pages use routes such as `settings/teams/{team}`". The kits "automatically ensure that the authenticated user belongs to the requested team". They register URL defaults for the current team and refresh them on sign-in, register and switch. Reserved team names (for example `settings`, `login`, `dashboard`) are rejected — [Laravel 13.x docs: Starter Kits](https://laravel.com/docs/13.x/starter-kits)
- Installer: `--teams` option ("Install team support") plus an interactive prompt "Would you like to add teams support to your application?". The flag combination picks kit branch `dev-workos-teams`, `dev-workos` or `dev-teams` — [laravel/installer NewCommand.php](https://github.com/laravel/installer/blob/HEAD/src/NewCommand.php)
- Kit branches on `laravel/vue-starter-kit`: `main`, `teams`, `workos`, `workos-teams`. The `teams` branch was last updated 2026-09-24 — [vue-starter-kit branches](https://github.com/laravel/vue-starter-kit/branches)
- Timeline (`laravel/maestro`, where the kits are built):
  - PR #14 "feat: Teams support" merged 2026-03-27.
  - Follow-ups: invitation i18n (2026-04-01), "Teams Invitation Flow Improvements" (#120, 2026-06-10), "Add leave team button" (#160, 2026-06-12), "Scope personalTeam() to teams the user owns" (#208, 2026-08-31).
  - Still open: "Add Teams variant to the API starter kit" (#80, opened 2026-04-17) and "Add stateless API starter kit" (#78).
  - Source: [laravel/maestro PRs](https://github.com/laravel/maestro/pulls?q=teams)
- Schema (teams branch, migration `2026_01_27_000001_create_teams_table.php`):
  - `teams`: `id`, `name`, `slug` unique, `is_personal` bool default false, timestamps, `softDeletes`.
  - `team_members`: `id`, `team_id` FK cascade, `user_id` FK cascade, `role` string, timestamps, `unique(['team_id','user_id'])`.
  - `team_invitations`: `id`, `code` (64) unique, `team_id`, `email`, `role`, `invited_by` → users, `expires_at`, `accepted_at`, timestamps.
  - Second migration: `users.current_team_id` nullable FK → teams, `nullOnDelete`.
  - Source: [create_teams_table migration](https://github.com/laravel/vue-starter-kit/blob/teams/database/migrations/2026_01_27_000001_create_teams_table.php); [add_current_team_id migration](https://github.com/laravel/vue-starter-kit/blob/teams/database/migrations/2026_01_27_000002_add_current_team_id_to_users_table.php)
- Roles and permissions are PHP enums, not DB tables:
  - `TeamRole` (`owner`, `admin`, `member`) with `permissions()`, `level()` (3/2/1) and `isAtLeast()`. Owner gets all permissions. Admin gets `UpdateTeam`, `CreateInvitation`, `CancelInvitation`. Member gets none.
  - `TeamPermission` values: `team:update`, `team:delete`, `member:add`, `member:update`, `member:remove`, `invitation:create`, `invitation:cancel`.
  - Source: [TeamRole.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Enums/TeamRole.php); [TeamPermission.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Enums/TeamPermission.php)
- `HasTeams` concern (app code, not a package):
  - `teams()` BelongsToMany `withPivot(['role'])`, `ownedTeams()` HasManyThrough, `teamMemberships()`, `currentTeam()` → `current_team_id`.
  - `personalTeam()` (`is_personal`). `switchTeam()` updates `current_team_id` and calls `URL::defaults(['current_team' => $team->slug])`.
  - `belongsToTeam()`, `isCurrentTeam()`, `ownsTeam()`, `teamRole()`, `fallbackTeam()`, `hasTeamPermission(Team, TeamPermission)`.
  - The `Team` model auto-generates unique slugs on create and update, uses a custom `Membership` pivot (`->using(Membership::class)`), and has an `owner()` method.
  - Source: [HasTeams.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Concerns/HasTeams.php); [Team.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Models/Team.php)
- Middleware: `EnsureTeamMembership` (route param `current_team` or `team`) runs `abort_if(! $user->belongsToTeam($team), 403)`, accepts an optional minimum role (`EnsureTeamMembership:admin`), and auto-switches the current team. `SetTeamUrlDefaults` sets `URL::defaults(['current_team' => slug, 'team' => slug])`. Routes: `Route::prefix('{current_team}')->middleware(['auth','verified', EnsureTeamMembership::class])` — [EnsureTeamMembership.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Http/Middleware/EnsureTeamMembership.php); [SetTeamUrlDefaults.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Http/Middleware/SetTeamUrlDefaults.php); [routes/web.php](https://github.com/laravel/vue-starter-kit/blob/teams/routes/web.php)
- WorkOS variant: social auth (Google, Microsoft, GitHub, Apple), passkeys, "Magic Auth", SSO. It needs `WORKOS_CLIENT_ID`/`WORKOS_API_KEY`/`WORKOS_REDIRECT_URL`. The docs say: "WorkOS offers free authentication for applications up to 1 million monthly active users." — [Laravel 13.x docs: Starter Kits](https://laravel.com/docs/13.x/starter-kits)
- The `workos-teams` branch contains the same local `Team`/`TeamInvitation`/`HasTeams` files plus a `CreatePersonalTeam` listener on `Illuminate\Auth\Events\Registered`. No organization-named files were found in its tree — [vue-starter-kit workos-teams](https://github.com/laravel/vue-starter-kit/tree/workos-teams); [CreatePersonalTeam.php](https://github.com/laravel/vue-starter-kit/blob/workos-teams/app/Listeners/CreatePersonalTeam.php)
- `laravel/workos` package: v0.6.0 (2026-02-19), Laravel 11–13, still pre-1.0 — [Packagist p2](https://repo.packagist.org/p2/laravel/workos.json)
- Older context: the original 2025 starter-kit launch post said Laravel was "looking at adding more features like two-factor authentication (2FA) and teams functionality soon" (as summarised by search) — [Laravel blog: Starter Kits](https://laravel.com/blog/laravel-starter-kits-a-new-beginning-for-your-next-project)

### Inferences
- The starter-kit teams code is now the **first-party reference design** for "workspace/team" membership on Laravel 13. Its main differences from Jetstream:
  - ownership is a role (`owner`) in `team_members`, not a `teams.user_id` column;
  - roles and permissions are enums;
  - URLs use a team slug prefix plus `URL::defaults`;
  - invitations carry a code and an expiry;
  - teams are soft-deleted.
- The kits give **membership and authorization only, not data scoping**. They do not add `team_id` global scopes to domain models. A SaaS still needs a tenancy scope layer (copy stancl's `TenantScope`, or write one).
- Since the code is generated into the app (not a package), "adopting" it for an existing app means copying patterns. That fits a team that already has its own membership model.

### Gaps
- I found no Laravel blog or Laravel News post announcing the teams option. The evidence is the 13.x docs, the installer source and the Maestro PRs.
- I found no evidence that the WorkOS variant maps teams to WorkOS Organizations. `laravel/workos` source mentions "organization" in `User.php` and the AuthKit request classes, but I did not read those files.

## 8. Other packages (status) and a cross-package comparison for single-DB PostgreSQL on Laravel 13 + Filament 5

### Takeaway
- `tenancy/tenancy` (ex-hyn) still releases (v2.5.0, 2026-05-09, Laravel 10–13) but has little use (about 1.5k installs per month).
- `hyn/multi-tenant` is effectively dead: last release 5.9.1 (2023-08-18), Laravel 9/10 only.
- `romegasoftware/multitenancy` is not on Packagist.
- `mpociot/teamwork` is alive (v11.0.0, 2026-09-03, Laravel 9–13) but niche.
- `spatie/laravel-permission` v8.x (2026) has a "teams" mode that scopes roles and permissions by `team_id` (set via `setPermissionsTeamId()`). That is authorization scoping, not data tenancy.

### Cited Findings
- `tenancy/tenancy`: v2.5.0 (2026-05-09) requires `laravel/framework ^10.0 | ^11.0 | ^12.0 | ^13.0`. v2.4.x was 2025. 52.5k total and about 1.5k monthly installs, 1,335 stars, repo last pushed 2026-07-01 — [Packagist p2](https://repo.packagist.org/p2/tenancy/tenancy.json); [Packagist](https://packagist.org/packages/tenancy/tenancy); [GitHub tenancy/tenancy](https://github.com/tenancy/tenancy)
- `hyn/multi-tenant`: last release 5.9.1 (2023-08-18), requires `^9.0|^10.0`. The repo moved to `tenancy/multi-tenant` ("previously github.com/hyn/multi-tenant"). It is not flagged abandoned on Packagist — [Packagist p2](https://repo.packagist.org/p2/hyn/multi-tenant.json); [GitHub tenancy/multi-tenant](https://github.com/tenancy/multi-tenant)
- `romegasoftware/multitenancy`: Packagist returns "404 not found, no packages here", and Packagist search found no match — [Packagist p2 lookup](https://repo.packagist.org/p2/romegasoftware/multitenancy.json)
- `mpociot/teamwork`: v11.0.0 (2026-09-03) requires `^9.0|…|^13.0`. 733k total and about 9.7k monthly installs — [Packagist p2](https://repo.packagist.org/p2/mpociot/teamwork.json); [Packagist](https://packagist.org/packages/mpociot/teamwork)
- `spatie/laravel-permission`: v8.0.0 (2026-05-30) to v8.3.0 (2026-07-03), requires `^12.0|^13.0`, PHP `^8.3`.
  - Teams mode: `'teams' => true` in `config/permission.php` (set **before** first migration, or run `php artisan permission:setup-teams`). The FK name is configurable via `team_foreign_key`.
  - Set the active team with `setPermissionsTeamId(...)` in middleware (example: from `session('team_id')`). That middleware must run before `SubstituteBindings` "else you may get 404 Not Found responses when a 403 Not Authorized response might be expected".
  - The design is inspired by Laratrust.
  - Source: [Packagist p2](https://repo.packagist.org/p2/spatie/laravel-permission.json); [teams-permissions.md](https://github.com/spatie/laravel-permission/blob/main/docs/basic-usage/teams-permissions.md)
- Adoption snapshot (Packagist total / monthly): stancl/tenancy 10.95M / 1.03M; spatie/laravel-multitenancy 4.27M / 317k; laravel/jetstream 24.7M / 1.0M; mpociot/teamwork 733k / 9.7k; tenancy/tenancy 52.5k / 1.5k — [Packagist stancl](https://packagist.org/packages/stancl/tenancy); [Packagist spatie](https://packagist.org/packages/spatie/laravel-multitenancy); [Packagist jetstream](https://packagist.org/packages/laravel/jetstream); [Packagist teamwork](https://packagist.org/packages/mpociot/teamwork); [Packagist tenancy/tenancy](https://packagist.org/packages/tenancy/tenancy)

### Inferences
Comparison for a **single-database PostgreSQL** SaaS on **Laravel 13 + Filament 5**. This is synthesised from the cited findings above.

| | Automatic row scoping | DB-enforced isolation | Identifies tenant from request | Queue/cache awareness | User↔tenant membership model | L13 status (2026-10) |
|---|---|---|---|---|---|---|
| stancl/tenancy v3.10.1 | Yes: Eloquent `TenantScope` via `BelongsToTenant`/`BelongsToPrimaryModel` (not `DB::`, not validation) | No | Domain, subdomain, path, header/query | Yes (5 bootstrappers) | Not provided | Stable, supports L13 |
| stancl/tenancy v4 (`dev-master`) | Same, or Postgres RLS instead | **Yes**: Postgres RLS (`TableRLSManager`, `FORCE RLS`), labelled experimental | + Origin header, cookie, early identification, universal routes | Yes (about 19 bootstrappers) | Not provided (sponsor recipe) | Untagged, PHP ^8.4, L12/13 |
| spatie/laravel-multitenancy 4.2.1 | **No** | No | `TenantFinder` (Domain built-in, custom) | Yes (jobs via Context, `PrefixCacheTask`) | Not provided | Stable, supports L13 |
| Filament 5 tenancy | Only inside the panel, after tenant resolution | No | Panel URL slug or domain | No | `HasTenants`/`canAccessTenant` (many-to-many) | v5.10.1 |
| Starter kits `--teams` | No | No | `{current_team}` slug + `URL::defaults` | No | `team_members` + role enums + invitations | First-party, March 2026+ |
| Jetstream teams | No | No | `current_team_id` (no URL) | No | `team_user` + `Jetstream::role` | Legacy, compat bumps only |
| spatie/laravel-permission teams | No (roles/permissions only) | No | Manual `setPermissionsTeamId()` | No | Roles per team | v8.3.0 |

- No single first-party feature delivers data tenancy for a single-DB app. The first-party pieces (starter kits, Filament) cover **membership and UI**. Only stancl covers **row scoping**, and only stancl v4 covers **DB-enforced (RLS) scoping**.
- A common "copy, don't adopt" combination for an existing app (my synthesis):
  - a stancl-style `TenantScope` + `FillsCurrentTenant` trait;
  - stancl-style `$tenant->unique()/exists()` validation helpers;
  - Spatie-style tenant-aware job marker interfaces with restore-after-job;
  - starter-kit-style membership, role enums, invitations and slug URL defaults;
  - optionally Postgres RLS as defence in depth, using `SET LOCAL` per transaction.

### Gaps
- `romegasoftware/multitenancy` could not be located on Packagist or by search. Its existence and status are unverified.
- I did not check `spatie/laravel-permission` v8's changelog for team-mode changes beyond the docs page.
