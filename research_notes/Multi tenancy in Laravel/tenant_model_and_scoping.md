# Tenant model and tenant scoping in single-database Laravel apps (Laravel 11–13, 2024–2026 practice)

Scope note: this covers single-database ("shared schema, `tenant_id`/`team_id` column") tenancy. Sources are dated where possible; anything from before 2023 is marked **[older]**. Framework source checks were done against the local `vendor/` copy of laravel/framework **v13.35.0** (the GitHub URLs given point at the same files on the `13.x` branch).

## 1. Tenant data model: separate `teams`/`organizations`/`workspaces` table + membership pivot vs "the user is the tenant" vs `users.tenant_id`

### Takeaway
The current first-party Laravel default (Laravel 13 starter kits, 2026) and Filament both use a **separate tenant table plus a membership pivot with a role**, with every user getting a personal team and a `users.current_team_id` pointer. "The user is the tenant" survives only as the degenerate "personal team" case. A plain `users.team_id` (one-to-many) is advised only when a user can never belong to more than one tenant, and Filament says to skip its tenancy feature in that case and use a column + global scope + observer instead.

### Cited Findings
**Laravel 13 official starter kits (React/Svelte/Vue/Livewire), teams option, 2026**
- The Laravel 13 docs say the starter kits "may also be generated with team support". Each user belongs to one or more teams and has a current team. New users get a personal team at registration. The kits include screens to create, switch, invite and update teams. — [Laravel 13 Starter Kits: Teams](https://laravel.com/docs/13.x/starter-kits#teams)
- Schema in the Vue starter kit `teams` branch (migration dated 2026_01_27). `teams` has `id, name, slug (unique), is_personal bool, timestamps, softDeletes`. `team_members` has `team_id` FK cascadeOnDelete, `user_id` FK cascadeOnDelete, `role` string, timestamps, `unique(team_id, user_id)`. `team_invitations` has `code` unique, `team_id`, `email`, `role`, `invited_by` FK users, `expires_at`, `accepted_at`. — [vue-starter-kit teams migration](https://github.com/laravel/vue-starter-kit/blob/teams/database/migrations/2026_01_27_000001_create_teams_table.php)
- `users.current_team_id` is a nullable FK to `teams` with `nullOnDelete()`. — [vue-starter-kit current_team_id migration](https://github.com/laravel/vue-starter-kit/blob/teams/database/migrations/2026_01_27_000002_add_current_team_id_to_users_table.php)
- **Ownership is a pivot role, not a column.** The `TeamRole` enum is `owner | admin | member`, with `level()` 3/2/1 and `isAtLeast()`. Owner has every `TeamPermission`. `assignable()` excludes Owner. `ownedTeams()` is a `hasManyThrough(Team, Membership)` filtered on `team_members.role = 'owner'`. `personalTeam()` is the owned team where `is_personal = true`. `switchTeam()` refuses teams the user does not belong to, updates `current_team_id` and refreshes `URL::defaults`. — [TeamRole.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Enums/TeamRole.php), [HasTeams.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Concerns/HasTeams.php)
- Deleting a team in the starter kit runs a transaction. Every other user whose `current_team_id` is that team is switched to their personal team, and invitations are deleted. — [TeamController.php (destroy)](https://github.com/laravel/vue-starter-kit/blob/teams/app/Http/Controllers/Teams/TeamController.php)

**Jetstream (2020–2024 default, now superseded by the starter kits) [older design, still widely deployed]**
- `teams` has `user_id` (owner; indexed, **no FK constraint**), `name`, `personal_team` bool. `team_user` has `team_id`, `user_id`, nullable `role`, `unique(team_id,user_id)`. — [Jetstream teams migration](https://github.com/laravel/jetstream/blob/5.x/database/migrations/2020_05_21_100000_create_teams_table.php), [team_user migration](https://github.com/laravel/jetstream/blob/5.x/database/migrations/2020_05_21_200000_create_team_user_table.php)
- In Jetstream the owner is separate from members. `$team->users` excludes the owner, `$team->allUsers()` includes them, and `$user->allTeams()` merges owned and joined teams. Every user gets a "Personal" team. Roles are "simply a presentational concept used to group granular permissions" (`Jetstream::role`, `hasTeamPermission`). — [Jetstream Teams docs](https://jetstream.laravel.com/features/teams.html)
- A Laracasts thread notes the Jetstream owner is not in the `team_user` pivot, so per-member columns end up split across the pivot and `teams`. — [search summary of Laracasts thread](https://laracasts.com/discuss/channels/laravel/metadata-for-users-of-a-jetstream-team) (snippet only, not fully read)
- **Deleting the owner deletes their tenants.** Jetstream's `DeleteUser` (teams variant) runs `$user->teams()->detach()` and then `DeletesTeams->delete($team)` for every owned team, inside `DB::transaction`. There is no ownership-transfer step. — [DeleteUserWithTeams.php stub](https://github.com/laravel/jetstream/blob/5.x/stubs/app/Actions/Jetstream/DeleteUserWithTeams.php)
- Jetstream hard-codes personal-team creation. Community workarounds include renaming the personal team instead of creating a new one, or a package that comments out personal-team creation (Laravel 10 / Jetstream 2 only). — [Laracasts reply](https://laracasts.com/discuss/replies/898094), [itbm/laravel-jetstream-disable-personal-team](https://packalyst.com/index.php/packages/package/itbm/laravel-jetstream-disable-personal-team)

**Filament v5 tenancy**
- Filament's tenancy assumes a user belongs to many tenants and can switch between them. Records are owned by the tenant ("group"). Setup is `->tenant(Team::class)` plus a `belongsToMany` `teams()` on User. The User implements `HasTenants`, with `getTenants(Panel)` and `canAccessTenant(Model)`. The docs stress `canAccessTenant` is required so users cannot reach other tenants by guessing IDs in the URL. Optional contracts: `HasDefaultTenant`, `HasName`, `HasAvatar`, `HasCurrentTenantLabel`. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)
- For one-to-many (a user belongs to exactly one team), Filament suggests a plain `team_id` column with a global scope and an observer instead of its tenancy feature. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)
- Billing lives on the tenant. Filament's `tenantBillingProvider(new SparkBillingProvider())` and `requiresTenantSubscription()` bill and gate the *team*, not the user. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)

**Community opinion**
- Laravel Daily (1 Apr 2026, "Is the new Laravel 13 Teams the same as multi-tenancy?") says Teams is the foundation that multi-tenancy builds on. It says application-level isolation (shared DB, `team_id` on every row) is what "90% of SaaS apps need": "Start with `team_id`, add global scopes, and ship." Database-level isolation (stancl/tenancy) is for compliance, per-tenant backups or similar needs. The article says Teams itself scopes nothing automatically. — [Laravel Daily 2026](https://laraveldaily.com/post/is-the-new-laravel-13-teams-the-same-as-multi-tenancy)
- **[older]** Laravel Daily's earlier tutorials scope per *user* (`created_by_user_id` or `user_id` + global scope + creating observer), i.e. "the user is the tenant". — [Laravel Daily: observers + global scopes (2018)](https://laraveldaily.com/use-laravel-observers-and-global-scopes-to-create-user-multi-tenancy/), [Laravel Daily: single-trait multi-tenancy](https://laraveldaily.com/post/simple-laravel-multi-tenancy-with-single-trait)
- Pivot-role pattern (role column on `team_user`, default "member", changed with `updateExistingPivot()`), with one owner and several members. — [Pineco: easy role management with pivot models](https://pineco.de/easy-role-management-pivot-models/) (date not checked; likely **[older]**)
- Reaching tenant-owned records from a user goes through the pivot (`hasManyThrough` via `team_user`), because projects belong to teams, not users. — [Codecourse: HasManyThrough with a pivot table](https://www.codecourse.com/courses/using-hasmanythrough-with-a-pivot-table-in-laravel)
- **[older, 2017]** Tom Schlick's Laracon US 2017 talk "Building Multitenant Applications" is still the reference Spatie points to before using laravel-multitenancy. Full Stack Radio ep. 80 with Schlick covers shared vs multi-database, how to identify the current tenant, and performance. — [Spatie laravel-multitenancy intro](https://spatie.be/docs/laravel-multitenancy/v2/introduction), [Full Stack Radio 80](https://fullstackradio.com/80), [Laravel News podcast: Laracon Sessions w/ Tom Schlick](https://podcast.laravel-news.com/38)
- Other SaaS products' ownership-transfer rules give a design model. Only one owner at a time, and the new owner must already be a member (GQueues). The new owner accepts an invite, the old owner is demoted, and billing moves with ownership (TeamSnap). — [GQueues](https://learn.gqueues.com/en/articles/5315150-transfer-team-ownership), [TeamSnap](https://helpme.teamsnap.com/article/328-transfer-team-ownership)

### Inferences
- The trade-offs between the three models:
  - **Ownership transfer.** Owner-as-pivot-role (starter kit) makes transfer a pivot update and allows several owners. An owner column (Jetstream `teams.user_id`) enforces "exactly one owner" in the schema, but transfer means changing the column *and* inserting the old owner into the pivot.
  - **"User is the tenant".** Here transfer is impossible without re-keying every row (`user_id` → new owner).
  - **Deleting the owner.** With the tenant as its own row, the team can outlive its owner if you add a transfer step. Jetstream's default instead deletes owned teams, the same outcome as "user is the tenant". The starter kit's `current_team_id` `nullOnDelete` plus "switch affected users to their personal team" is the reference way to clean up after deleting a team.
  - **Multi-membership.** This is the deciding factor. If a person must ever sit in two workspaces (agency, consultant, support staff), you need a pivot. `users.tenant_id` cannot express it, and "user is tenant" needs a second identity per workspace.
  - **Billing.** Billing on a dedicated tenant row (Filament/Spark) is the documented pattern. With "user is tenant", billing sits on the owner's user row and must move during any transfer.
- Chatvia note (from this repo's CLAUDE.md, not a web source): Chatvia today is the "user is the tenant" model (`UserType::Workspace` user row = workspace; teammates via `team_user` + role; data keyed to `$agent->user_id` / `$user->workspace()->id`). Moving to the starter-kit shape would mean a `workspaces` table, a membership pivot with an owner role, and `current_workspace_id`.

### Gaps
- I found no 2023–2026 written piece by Aaron Francis, Freek Van der Herten, Tighten or Benjamin Crozat that compares teams vs organizations vs workspaces, or argues "user as tenant" vs a separate table. I could not verify one. The Tom Schlick talk is 2017, and I did not watch the video or slides.
- No Laravel source I found models a two-tier **organization → teams** hierarchy. The starter kit and Jetstream are both one tier.
- The Filament page's exact wording "Records are owned by the group, and users can be members of multiple groups" came from the brief. My fetch summarized it as "Records are owned by the group", and I did not capture the full verbatim sentence.
- I did not re-check whether Laravel Cashier 13.x documents a non-User billable model (e.g. `Cashier::useCustomerModel(Team::class)`). It is unverified in this session.

## 2. Current-tenant resolution and switching (`current_team_id`, URL path, subdomain, session, header) for an Inertia/Vue app

### Takeaway
The 2026 first-party answer for Inertia apps is **tenant slug in the URL path (`/{current_team}/...`), with `users.current_team_id` as the remembered default**. Membership-checking middleware syncs the two, and `URL::defaults()` keeps `route()` calls short. Session-only or `current_team_id`-only resolution (Jetstream style) is simpler but not safe across tabs, and links cannot be shared. Subdomains and headers suit custom domains and APIs.

### Cited Findings
- Starter kit routing: `Route::prefix('{current_team}')->middleware(['auth','verified', EnsureTeamMembership::class])->group(...)`, so the dashboard is `/{current_team}/dashboard`. Team admin pages use `settings/teams/{team}`. — [vue-starter-kit routes/web.php](https://github.com/laravel/vue-starter-kit/blob/teams/routes/web.php), [Laravel 13 Starter Kits: Teams](https://laravel.com/docs/13.x/starter-kits#teams)
- `EnsureTeamMembership` resolves `current_team` or `team` from the route (looks the slug up with `Team::where('slug', …)`). It aborts 403 unless the user `belongsToTeam`, and can enforce a minimum role through a middleware parameter (`TeamRole::isAtLeast`). **If the URL team differs from `current_team_id`, it calls `$user->switchTeam($team)`**, so the URL wins and the column becomes the last-used default. — [EnsureTeamMembership.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Http/Middleware/EnsureTeamMembership.php)
- `SetTeamUrlDefaults` (appended to the `web` group) calls `URL::defaults(['current_team' => slug, 'team' => slug])`, so server-side `route('dashboard')` fills in the slug. The docs say the kits refresh these defaults on sign-in, registration and team switch. — [SetTeamUrlDefaults.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Http/Middleware/SetTeamUrlDefaults.php), [bootstrap/app.php](https://github.com/laravel/vue-starter-kit/blob/teams/bootstrap/app.php), [Starter Kits: Teams](https://laravel.com/docs/13.x/starter-kits#teams)
- Inertia side: `HandleInertiaRequests::share()` exposes lazy `currentTeam` and `teams` props. `TeamSwitcher.vue` calls `router.visit(switchMethod(team.slug))` (a Wayfinder route) and reloads. — [HandleInertiaRequests.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Http/Middleware/HandleInertiaRequests.php), [TeamSwitcher.vue](https://github.com/laravel/vue-starter-kit/blob/teams/resources/js/components/TeamSwitcher.vue)
- Because the slug is a top-level path segment, the kits reject team names that would collide with route prefixes (`settings`, `login`, `dashboard`). The `teams.slug` column is globally unique. — [Starter Kits: Teams](https://laravel.com/docs/13.x/starter-kits#teams), [teams migration](https://github.com/laravel/vue-starter-kit/blob/teams/database/migrations/2026_01_27_000001_create_teams_table.php)
- Filament options: path segment after the panel (`/admin/{tenant}`), `slugAttribute`, and `tenantRoutePrefix()` (e.g. `/admin/team/1`). Subdomain uses `tenantDomain('{tenant:slug}.example.com')`, and a full custom domain uses `tenantDomain('{tenant:domain}')`. The custom-domain mode registers a **global route pattern for `tenant`**, which can clash with other panels or routes. Code reads the tenant with `Filament::getTenant()`. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)
- **[older pattern, still common]** Jetstream keeps the current team only in `users.current_team_id` (changed from a team switcher), with no tenant in the URL. — [Jetstream Teams docs](https://jetstream.laravel.com/features/teams.html)
- spatie/laravel-permission's teams example puts `team_id` in the **session** at login and calls `setPermissionsTeamId(session('team_id'))` in middleware. It notes an API token can supply the team instead, and that "you must build your own team selection logic". — [spatie/laravel-permission v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- Full Stack Radio ep. 80 (2017) covers "approaches for identifying the current tenant" (domain, subdomain, path, user) as a core design choice. **[older]** — [Full Stack Radio 80](https://fullstackradio.com/80)

### Inferences
- **URL path (`/{team}/…`)**
  - Pros: two tabs on two workspaces don't interfere, deep links and shared links carry the tenant, and HTTP caching and logs show the tenant.
  - Cons: every tenant route and every frontend URL needs the parameter. `URL::defaults` fixes this server-side, but a Wayfinder/Vue frontend must pass the slug or get it from shared props. Reserved-word collisions must be blocked, and the slug becomes a public identifier.
- **`users.current_team_id` only**
  - Pros: zero URL changes.
  - Cons: switching in one tab silently changes the tenant for every other tab, device and queued request reading it. Bookmarked links can open in the wrong tenant, and a race between switch and submit can write into the wrong tenant unless forms carry the team id.
- **Session**: the same multi-tab problem, scoped to a browser. It also doesn't exist for API or channel requests.
- **Subdomain or custom domain**: strongest visual separation and needed for white-label. But the cookie/session domain must be set (or users log in per subdomain), local dev needs wildcard DNS, and Filament's global route-pattern side effect shows how it bleeds into other routes.
- **Header (`X-Tenant`)**: right for APIs, mobile and SPAs with token auth. Never trust it without the same membership check, and it is not usable for plain browser navigation.

### Gaps
- I did not check whether Wayfinder's generated TypeScript honours server-side `URL::defaults()` (i.e. whether `dashboard.url()` fills `current_team` itself). The starter kit's `TeamSwitcher` passes the slug explicitly.
- I found no published benchmark or incident report comparing these strategies in Inertia apps specifically.

## 3. Enforcement mechanisms, how well each works, and known failure modes

### Takeaway
No single Laravel mechanism keeps everything inside the tenant. The practical pattern stacks five layers:
- a tenant-resolving middleware (ordered before `SubstituteBindings`),
- a `BelongsToTenant` trait (global scope + creating hook) on *primary* models,
- scoped route bindings or explicit parent-child checks for nested resources,
- policies that compare `tenant_id`,
- tenant-scoped validation rules and composite unique indexes.

Queues, console commands, raw/`DB::` queries, validation rules, `withoutGlobalScopes()`, secondary models queried directly, and Octane/worker static state are the documented holes.

### Cited Findings
**Eloquent global scopes + creating hook (the `BelongsToTenant` trait pattern)**
- Laravel 13 global scopes come from a `Scope` class with `apply(Builder, Model)` (generated by `make:scope`), attached with `#[ScopedBy([...])]` or `static::addGlobalScope()` in `booted()`, or written as a named closure scope. They are removed with `withoutGlobalScope(X::class|'name')`, `withoutGlobalScopes([...])` or `withoutGlobalScopesExcept([...])`. If a scope adds select columns, use `addSelect`, not `select`. — [Laravel 13 Eloquent: Global Scopes](https://laravel.com/docs/13.x/eloquent#global-scopes)
- stancl/tenancy v4 single-database mode is the most complete documented version of the trait pattern:
  - `BelongsToTenant` on **primary** models (direct `belongsTo` tenant) scopes their queries.
  - `FillsCurrentTenant` (used by `BelongsToTenant`) sets `tenant_id` on create when missing.
  - **Secondary** models (e.g. `Comment` via `Post`) are *not* scoped on direct queries (`Comment::all()` returns every tenant's rows) unless you add `BelongsToPrimaryModel` with `getRelationshipToPrimaryModel()`.
  - "We do not provide a solution for tertiary (and more indirect) relations out of the box."
  — [Tenancy for Laravel v4: single-database tenancy](https://v4.tenancyforlaravel.com/single-database-tenancy)
- stancl v4 limits, quoted:
  - "`DB` facade queries, or any similarly low level queries, will not be scoped."
  - The traits "only affect what you may _query_ using models".
  - "If you have a specific instance, Tenancy doesn't enforce anything" (an instance already in hand is not re-checked).
  - The escape hatch is the `withoutTenancy()` macro, i.e. `withoutGlobalScope(TenantScope::class)`.
  - PostgreSQL RLS support is called "still considered somewhat experimental".
  — [Tenancy for Laravel v4](https://v4.tenancyforlaravel.com/single-database-tenancy)
- stancl v3: models are "not scoped at all when there's no current tenant (e.g. in a central admin panel)". In other words, a missing tenant **fails open** in that package. — [Tenancy for Laravel v3: single-database tenancy](https://tenancyforlaravel.com/docs/v3/single-database-tenancy)
- **[older]** Laravel Daily's trait registers the creating hook and global scope **only if `auth()->check()`** inside the trait's boot method. — [Laravel Daily: single-trait multi-tenancy](https://laraveldaily.com/post/simple-laravel-multi-tenancy-with-single-trait)
- Filament auto-scopes tenant-aware *resources* (list tables and URL record lookup → 404 outside the tenant) and auto-fills the tenant FK on create. Opt-outs are `$isScopedToTenant = false` or `Resource::scopeToTenant(false)`. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)
- Filament warnings, quoted:
  - Queries run "before" tenant resolution (early middleware or service providers) mean "the query will not be scoped to the current tenant".
  - Queries "outside the panel with tenancy enabled do not have access to the current tenant".
  - "If any of your queries disable all global scopes, the tenancy global scope will be disabled as well."
  - Protection "does not extend to records passed into relation managers, widgets, or modal table selects".
  - Models without a tenant-aware resource need their own scope, added through persistent `tenantMiddleware(..., isPersistent: true)`.
  - "Filament does not provide any guarantees about the security of your application."
  — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)

**Known failure modes of global scopes (with sources)**
- *Validation bypass:* Laravel's `unique` and `exists` rules "do not use Eloquent models to query the database by default", so global scopes are ignored. Filament provides `scopedUnique()` / `scopedExists()`. stancl's fix is `Rule::unique('posts','slug')->where('tenant_id', tenant('id'))` or `$tenant->unique('posts')` via `HasScopedValidationRules`. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy), [stancl v4](https://v4.tenancyforlaravel.com/single-database-tenancy)
- *Queue model restoration skips scopes:* `SerializesModels` restores models with `newQueryForRestoration($ids)`, which is `newQueryWithoutScopes()->whereKey($ids)`, then `loadMissing($relations)`. The *re-fetch* of a model passed to a job ignores the tenant scope. Any *new* query inside the job applies the scope with whatever tenant state the worker holds. — [Model.php newQueryForRestoration](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Database/Eloquent/Model.php), [SerializesAndRestoresModelIdentifiers.php](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php)
- *Octane / long-running workers:* global scopes are stored statically and "can bleed into other requests in Laravel Octane". PR #46922 (merged into 10.x) added get/set of the full scope list so Octane can save and restore it. The PR says it does not fix mutable state inside a Scope instance. — [laravel/framework PR #46922](https://github.com/laravel/framework/pull/46922)
- *Auth recursion:* a global scope on `User` that reads `auth()->user()` loops, because loading the auth user runs the scope. The suggested fix is a custom `EloquentUserProvider` that calls `withoutGlobalScopes()` in `retrieveById`. — [Laracasts: using data from auth user in global scope](https://laracasts.com/discuss/channels/eloquent/using-data-from-authuser-in-global-scope)
- *`orWhere` precedence:* a blog post (Infosec Write-ups; member-only, snippet seen) shows a root-level `orWhere` bypassing a tenant filter (`tenant AND status OR role=admin`). **Verified nuance:** this does *not* happen to tenant filters applied as Eloquent *global scopes*. `Builder::callScope()` groups the scope's new wheres, and `groupWhereSliceForScope()` wraps any existing slice containing `or` in a nested group. The trap applies to hand-written `->where('tenant_id', …)->orWhere(…)` filters. — [Infosec Write-ups: the orWhere trap](https://infosecwriteups.com/the-orwhere-trap-how-sql-operator-precedence-breaks-multi-tenant-data-isolation-in-laravel-ec598f5a0f27), [Eloquent Builder.php callScope / groupWhereSliceForScope](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Database/Eloquent/Builder.php)
- *Ambiguous column in joins:* a team-filter trait hit "ambiguous column" errors on joins until it used `qualifyColumn('team_id')`. — [Laracasts thread (search snippet only)](https://laracasts.com/discuss/replies/937887)

**Scoped implicit route bindings**
- If a nested parameter uses a custom key (`/users/{user}/posts/{post:slug}`), Laravel scopes the child through the parent relationship automatically, guessing `posts()` from the plural parameter name. `->scopeBindings()` (per route) or `Route::scopeBindings()->group(...)` scopes it even without a custom key, and `->withoutScopedBindings()` opts out. The model's `resolveChildRouteBinding($childType, $value, $field)` customizes this, and `resolveRouteBinding($value, $field)` customizes top-level lookup. `->missing(fn)` customizes the 404. — [Laravel 13 Routing: implicit binding / custom keys and scoping](https://laravel.com/docs/13.x/routing#implicit-binding)

**Middleware ordering ("scope applied before auth/tenant")**
- spatie/laravel-permission: the team middleware must run **before `SubstituteBindings`**, "otherwise you may get 404 responses where you expect 403s". On Laravel 11.27+, use `$kernel->addToMiddlewarePriorityBefore(...)` in `AppServiceProvider::boot()`. — [spatie/laravel-permission v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- Filament: tenant middleware runs after tenant identification. By default it runs only on the initial page load, not on later Livewire requests, unless `isPersistent: true`. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)

**Policies / Gates with tenant checks**
- Laravel 13 policies return `Response::denyAsNotFound()` / `denyWithStatus(404)` to hide a resource's existence ("hiding resources via a 404 response is such a common pattern"). They are applied as `->can('update','post')` / `can:` middleware or the `#[Authorize('update','post')]` controller attribute. A policy's `before()` "will not be called if the class doesn't contain a method with a name matching the name of the ability being checked". — [Laravel 13 Authorization](https://laravel.com/docs/13.x/authorization)
- Laravel 13 docs recommend exposing authorization results to Inertia through `HandleInertiaRequests::share()`, while noting authorization "must always be handled on the server". — [Laravel 13 Authorization: Authorization & Inertia](https://laravel.com/docs/13.x/authorization#authorization-and-inertia)
- Filament requires `canAccessTenant()` to stop tenant-ID guessing in URLs. — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)

**Carrying the tenant into queues: Context facade (Laravel 11+) and package job middleware**
- `Context::add()` data is appended to log entries *and* captured with every queued job, then hydrated back while the job runs. `Context::addHidden()` stores data that is not written to logs. `Context::dehydrating(fn (Repository $c) => …)` and `Context::hydrated(fn (Repository $c) => …)` hook the boundary. The docs' example restores `app.locale` on the worker, and they warn to change only the passed `$context`, not the facade, inside those callbacks. `Context::scope()` temporarily overrides values. — [Laravel 13 Context](https://laravel.com/docs/13.x/context)
- Verified in source:
  - `Repository::dehydrate()` serializes both `data` and `hidden`, and `ContextServiceProvider` adds the result to the job payload under `illuminate:log:context` (`Queue::createPayloadUsing`).
  - It re-hydrates on `JobProcessing`.
  - The Repository is bound as `scoped`, so it is reset per request/job lifecycle.
  - Context is also passed to child Artisan processes through the `__LARAVEL_CONTEXT` env var.
  — [Context Repository.php](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Log/Context/Repository.php), [ContextServiceProvider.php](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Log/Context/ContextServiceProvider.php)
- spatie/laravel-multitenancy (v4):
  - `queues_are_tenant_aware_by_default` records the current tenant at dispatch and makes it current inside the job. Opt in or out with the `TenantAware` / `NotTenantAware` interfaces or config arrays.
  - A deleted tenant makes the job fail with `CurrentTenantCouldNotBeDeterminedInTenantAwareJob`.
  - The previous tenant is restored after the job, which matters for `sync` / `dispatchSync()`.
  - Non-tenant-aware jobs run with **no** current tenant.
  — [spatie/laravel-multitenancy v4: making queues tenant aware](https://spatie.be/docs/laravel-multitenancy/v4/basic-usage/making-queues-tenant-aware)

**Uniqueness and indexes**
- Scope unique indexes to the tenant: `$table->unique(['tenant_id','slug'])` instead of `unique('slug')`. On secondary models, the first column can be the parent FK. — [stancl v4](https://v4.tenancyforlaravel.com/single-database-tenancy)
- The starter kit's own pivot uses `unique(['team_id','user_id'])`. Jetstream uses the same. — [vue-starter-kit migration](https://github.com/laravel/vue-starter-kit/blob/teams/database/migrations/2026_01_27_000001_create_teams_table.php), [Jetstream team_user migration](https://github.com/laravel/jetstream/blob/5.x/database/migrations/2020_05_21_200000_create_team_user_table.php)

### Inferences
- **Global scope that fails closed.**
  - Resolve the tenant inside `apply()` (not in `booted()`/trait boot). Models boot once per PHP process, so under Horizon or Octane a boot-time `auth()->check()` (the Laravel Daily trait) freezes the decision for the whole worker. That is fail-open in workers, where no user is logged in, so the scope is simply never registered.
  - If no tenant is set, the scope should throw, or add `whereRaw('1=0')`, rather than return everything. stancl's documented behaviour (no tenant → unscoped) is the opposite and depends on every central code path being intentional.
- **Holder for the current tenant.** Use a container `scoped()` binding (reset per request/job like the Context repository) or Context hidden data. A plain `singleton` can leak one job's tenant into the next job in a long-running worker. This follows from the scoped-binding behaviour above. The container docs describe `scoped` as flushed per Octane request / queue job, but that page was not re-fetched this session.
- **Context alone does not enforce anything.** Use `Context::addHidden('tenant_id', …)` in the tenant middleware plus a `Context::hydrated` callback (or job middleware) that rebinds the current-tenant holder. Jobs that take an explicit tenant/workspace id in their DTO avoid hidden coupling and are easier to test. Context is the safety net for code that dispatches without passing the tenant.
- **Layered defence.** Treat global scopes as defence in depth, not the only gate.
  - Route-bound top-level models still need a policy check (or `scopeBindings` under a tenant-bound parent). A model fetched while the scope is off (admin path, `withoutGlobalScopes`, queue restoration) carries no protection.
  - stancl's "if you have a specific instance, Tenancy doesn't enforce anything" makes the same point.
- **Composite foreign keys (PostgreSQL).** `FOREIGN KEY (tenant_id, post_id) REFERENCES posts(tenant_id, id)`, backed by a `UNIQUE (tenant_id, id)` on the parent, makes a cross-tenant child row impossible at the DB level. Laravel's schema builder accepts array columns in `foreign()->references()->on()`. This is an inference; I found no Laravel-specific article recommending it.
- **PostgreSQL RLS** is the only mechanism that also covers `DB::` / raw SQL, but stancl calls its support experimental. It is likely covered in depth by another research track.

### Gaps
- Laravel docs `validation#rule-unique` (`Rule::unique(...)->where(fn ($q) => $q->where('account_id', …))`) and `container#binding-scoped` were not re-fetched. Their behaviour is supported only by the stancl/Filament pages and the framework source above.
- I found no first-party Laravel doc section on multi-tenancy. Laravel itself documents the building blocks, not a recipe.
- The Infosec Write-ups `orWhere` article is behind a paywall; only the search snippet was seen.
- No 2024–2026 GitHub issue was found documenting a real cross-tenant leak caused by `withoutGlobalScopes()` in a Laravel app. The risk is documented by Filament and stancl, but there is no incident write-up.

## 4. spatie/laravel-permission "teams" mode and a workspace → teams hierarchy

### Takeaway
Teams mode adds a `team_foreign_key` column to the role/permission pivot tables and a process-global "current team id" (`setPermissionsTeamId`). It supports exactly **one** level of scoping. Roles can be global (`team_id = null`) or per-team, and role assignments always carry a team id. For workspace → teams you either point `team_foreign_key` at the *workspace* (making the workspace the permission scope) or build the second level yourself.

### Cited Findings
- Enable with `'teams' => true` in `config/permission.php` **before the first migration**. On an existing install, run `php artisan permission:setup-teams` + `migrate`. Customize the column with `'team_foreign_key' => 'custom_team_id'`. — [spatie/laravel-permission v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- `setPermissionsTeamId($id)` / `getPermissionsTeamId()` set and read the active team. The doc example sets it from `session('team_id')` in middleware, and an API token can supply it instead. The middleware must be prioritized before `SubstituteBindings` (Laravel 11.27+: `addToMiddlewarePriorityBefore`). — [spatie v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- Role creation: `Role::create(['name'=>'writer','team_id'=>null])` makes a **global** role assignable in any team. `['team_id'=>1]` makes a team-specific role, so different teams can reuse a name. Omitting `team_id` uses the default global team id. — [spatie v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- When switching teams, call `setPermissionsTeamId($new)` **then** `$user->unsetRelation('roles')->unsetRelation('permissions')` before any `hasRole` / `can` check, or you read stale cached relations. — [spatie v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- Super-admin in teams mode: `team_id` is always required on the role relationship. The documented pattern is a `Team::created` hook that saves the current team id, switches to the new team, assigns 'Super Admin', and restores the previous id. That is a per-team assignment, not a cross-tenant flag. — [spatie v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- The page is the v6 doc and notes newer versions exist. — [spatie v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)
- Laravel's own 2026 starter kits do *not* use spatie for team roles. They use a `role` string column on `team_members` plus a `TeamRole` enum with a hard-coded permission map. — [TeamRole.php](https://github.com/laravel/vue-starter-kit/blob/teams/app/Enums/TeamRole.php)
- A 2026 community starter kit (n1crack/laravel-starter-kit, Laravel 13) keeps team roles (owner/administrator/member) on the `team_user` pivot and app-wide admin/user roles in spatie. It does not combine the two. — [n1crack/laravel-starter-kit on Packagist](https://packagist.org/packages/n1crack/laravel-starter-kit)

### Inferences
- **One scope level.** `getPermissionsTeamId()` holds a single scalar for the whole process, so spatie scopes on one axis only.
  - Workspace → teams: set `team_foreign_key = workspace_id` and model sub-team access as data (e.g. `team_user` rows, or permissions like `agent.permission` checked in policies).
  - Alternatively, scope spatie to sub-teams and give workspace-wide roles by assigning the same role on every sub-team, which is fragile.
- **Global state.** Because the team id is global, every queue job and console command that checks permissions must call `setPermissionsTeamId()` first, and clear the relation cache. That is the same queue/Octane hazard as the tenant holder in section 3. Carrying it through `Context::hydrated` is a natural fit.
- **Simple role sets.** For two or three fixed roles, the starter-kit pivot-enum approach avoids the global-state issue entirely. spatie teams mode pays off when tenants define their own custom roles.

### Gaps
- I did not read the newer (v7) spatie teams page to see whether the API or caveats changed after v6.
- I found no published example of a two-level (organization + team) hierarchy using spatie teams mode.

## 5. Platform super-admin access across tenants: `Gate::before`, impersonation, audit logging

### Takeaway
Laravel apps grant platform-wide power in one of two ways:
- a `Gate::before` short-circuit, which bypasses every policy, tenant checks included;
- more commonly with Filament, a **separate admin panel with no tenancy**, where scopes are off by design.

Support access "as the customer" goes through impersonation (lab404/laravel-impersonate; stechstudio/filament-impersonate). Its events are the hook for audit logs. Audit logging is not built in and must be added.

### Cited Findings
- `Gate::before(fn (User $user, string $ability) => $user->isAdministrator() ? true : null)`: "If the `before` closure returns a non-null result that result will be considered the result of the authorization check." `Gate::after` cannot override a non-null result. Inline `Gate::allowIf` / `denyIf` skip before/after hooks. A policy-level `before()` can return `false` to deny everything for a user type. — [Laravel 13 Authorization](https://laravel.com/docs/13.x/authorization)
- In stancl's single-DB mode, models are "not scoped at all when there's no current tenant (e.g. in a central admin panel)". The central admin works by simply not setting a tenant. — [stancl v3](https://tenancyforlaravel.com/docs/v3/single-database-tenancy)
- Filament scopes only panels configured with `->tenant(...)`. A separate admin panel without tenancy sees all rows, and tenant scopes can be turned off per resource (`$isScopedToTenant = false`). — [Filament 5.x tenancy](https://filamentphp.com/docs/5.x/users/tenancy)
- lab404/laravel-impersonate (v1.7; Laravel 6–13):
  - `$admin->impersonate($user)` / `leaveImpersonation()`. The impersonator id is kept in the session key `impersonated_by`.
  - `ImpersonateManager` (`app('impersonate')`) offers `isImpersonating()` and `getImpersonatorId()`.
  - `canImpersonate()` / `canBeImpersonated()` **default to allowing everyone**, so you must override them.
  - `Route::impersonate()` provides `impersonate` / `impersonate.leave` routes, with multi-guard support through `guardName`.
  - The `impersonate.protect` middleware blocks impersonators from chosen routes (e.g. billing).
  - The `TakeImpersonation` / `LeaveImpersonation` events expose `$impersonator` and `$impersonated`.
  — [404labfr/laravel-impersonate](https://github.com/404labfr/laravel-impersonate)
- stechstudio/filament-impersonate (v5.6.0, July 2026): admin-only by default (override `canImpersonate` on the Filament user), `canBeImpersonated` to protect targets, and blocks soft-deleted targets since 4.0. No built-in audit trail or tenancy-specific behaviour was found. — [stechstudio/filament-impersonate (Packagist)](https://packagist.org/packages/stechstudio/filament-impersonate), [GitHub](https://github.com/stechstudio/filament-impersonate)
- Packstub Account Switcher (Filament 4/5, Laravel 12/13) records every switch: who, to whom, why, panel, IP and user agent. Features are scoped per panel, not per tenant. — [Filament plugin: Packstub Account Switcher](https://filamentphp.com/plugins/packstub-account-switcher)
- Teams-mode spatie super-admins are assigned per team (see section 4). They are not a cross-tenant bypass unless combined with `Gate::before`. — [spatie v6 teams](https://spatie.be/docs/laravel-permission/v6/basic-usage/teams-permissions)

### Inferences
- **`Gate::before` is all-or-nothing and invisible.**
  - It also bypasses tenant-membership policies, so a platform admin who browses the *customer* app can read and write any tenant through normal routes, unlogged.
  - Safer: keep platform admin in a separate panel or guard. Make `Gate::before` return `true` only for that guard, or only for specific abilities. Never let it fire in the tenant app.
- **Impersonation keeps tenant checks intact.** The admin runs as the target user, inside that user's tenant memberships, so cross-tenant access goes through the normal scopes and policies.
- **Audit logging.**
  - Write an append-only audit row on `TakeImpersonation` / `LeaveImpersonation`, and tag every request and job during impersonation, e.g. `Context::add('impersonator_id', …)`, which also reaches queued jobs and logs through Context.
  - Protect billing, password, 2FA and deletion routes with `impersonate.protect`.
  - Note: Chatvia's standing rule forbids *denial* logging (user identity + probed resource) at auth boundaries. Impersonation start/stop audit is a separate concern, but it should not log denied probes.
- **Filament's own two-factor/admin access** (Chatvia's admin panel uses `canAccessPanel` gated on `UserType::Admin`) fits the "separate panel, no tenancy" model above.

### Gaps
- I did not find a 2024–2026 article by a recognized Laravel author on platform-admin vs tenant-admin separation patterns specifically.
- I did not read the full stechstudio README, so audit or event hooks it may expose are unverified.
- I did not fetch spatie/laravel-activitylog docs for an impersonation-audit recipe, so no source was gathered for that combination.
