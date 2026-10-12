# Database isolation strategies and PostgreSQL row-level security (RLS) for a Laravel SaaS

Scope: single database with a tenant column vs schema-per-tenant vs database-per-tenant on PostgreSQL;
how RLS can be added on top of single-database tenancy in Laravel; tenant filtering in vector search;
tenant partitioning. Research date: 2026-10-12. Source dates are given where known. Anything older
than 2023 is marked **(older)**.

---

## 1. Single DB (tenant_id) vs schema-per-tenant vs database-per-tenant: trade-offs and what the guides recommend

### Takeaway
All the major guides (Azure Architecture Center 2026, AWS SaaS Lens, Citus) treat the shared database with a
tenant key ("pool") as the default for many small or medium tenants. It is the cheapest option, has one schema
to migrate, one backup and easy cross-tenant reporting. Its weak points are isolation (one missing `WHERE`
leaks data), noisy neighbours and restoring a single tenant. Dedicated databases ("silo") give the best
isolation, per-tenant restore and per-tenant residency or keys, but cost more and multiply migrations and
connections. Schema-per-tenant ("bridge") sits in the middle. On PostgreSQL its known pain is catalog growth
and slow `pg_dump` at thousands of schemas. A common recommendation is a hybrid: pooled for most tenants,
dedicated for tenants who pay for it or have special compliance needs.

### Cited Findings

**Vendor and architecture guidance**
- AWS SaaS Lens defines three models. **Silo**: tenants get dedicated resources, for example "each tenant of your system has a separate database". **Pool**: "tenants share resources ... to achieve economies of scale, manageability, agility". **Bridge**: a mixed mode, where "the regulatory profile of a service's data and its noisy neighbor attributes might steer a microservice to a silo model" while agility and cost push others to pool. — [AWS SaaS Lens: Silo, Pool, and Bridge Models](https://docs.aws.amazon.com/wellarchitected/latest/saas-lens/silo-pool-and-bridge-models.html)
- AWS mapping to PostgreSQL **(older, May 2020)**: silo = one database instance per tenant, "the most separation at the expense of both higher infrastructure costs" and harder onboarding. Bridge = shared instance with a schema per tenant, which saves resources but makes maintenance and setup complex. Pool = shared tables with a tenant key, which "saves the most on operational costs", but isolation is often "implemented by hoping the correct `WHERE` clause is implemented in every SQL statement". — [AWS Database Blog: Multi-tenant data isolation with PostgreSQL RLS (2020)](https://aws.amazon.com/blogs/database/multi-tenant-data-isolation-with-postgresql-row-level-security/)
- Azure (updated Aug 2026) on a shared multitenant database: "provides the highest density of tenants to infrastructure, so it tends to come at the lowest financial cost" and "reduces the management overhead because there's a single database ... to manage, back up, and secure". It lists these drawbacks: scale limits of a single resource, noisy neighbours (mitigate with the Throttling or Rate Limiting patterns), per-tenant consumption metering, tenants with different security, backup, availability or location needs, and hard per-tenant schema customization. — [Azure: Architectural approaches for storage and data in multitenant solutions](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure on database-per-tenant: "each tenant's data is isolated", and some per-tenant customization becomes possible, but "the cost ... can be higher". It adds: "use automated deployment approaches when you provision databases for each tenant. Otherwise, the complexity ... becomes overwhelming." — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure on hybrid models: "you might use a multitenant database for most of your tenants but deploy single-tenant stamps for tenants who pay more or who have unusual requirements". It also recommends modelling the solution as deployment stamps even when the database inside a stamp is shared. — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure lists the conditions that call for isolating a tenant or grouping it with similar tenants: "Tenants need to use their own encryption keys", "Tenants have individual backup and restore policies", "Tenants need to have their data stored in different geographical locations". — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure on backup and restore: "In a shared database, restoring a single tenant might require you to restore the database to a separate resource and selectively recover that tenant's data. Sharded or dedicated databases can provide more granular recovery, but a shard restore might still affect multiple tenants." — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure on cross-tenant reporting: "As your solution scales, it becomes cumbersome to run queries on each database individually and aggregate the results. A different approach is to have each tenant's applications publish data to a centralized data warehouse." — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure on schema updates across a fleet: plan how to deploy schema updates "across your estate". As the number of databases grows, "it might be better for your application tier to detect the schema version for a specific database and to initiate the upgrade process". Keep the app "backwards-compatible with at least one previous schema version". — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure antipatterns: table-per-tenant in one database ("A single database can't support large numbers of tenants when you use this approach"), per-tenant columns, manual schema changes, and dependence on a single schema version. — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure on cost: "the higher the density of tenants to your deployment infrastructure, the lower the cost ... However, shared infrastructure increases the likelihood of problems like the noisy neighbor problem". — [Azure storage-data approaches](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)
- Azure on "delete my tenant": with customer-managed keys per tenant, "If a tenant chooses to deactivate their account, deleting the associated key ensures that their data is no longer accessible" (crypto-shredding). — [Azure: Azure Database for PostgreSQL in a multitenant solution (July 2026)](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/service/postgresql)
- Azure PostgreSQL elastic clusters (the Citus-based successor to Cosmos DB for PostgreSQL, which retires 31 Mar 2029) support row-based sharding by tenant ID and schema-based sharding. Schema-based sharding "is well-suited for workloads that have between 1 and 10,000 tenants" and suits apps "that can't undergo query modifications". — [Azure PostgreSQL multitenant guidance](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/service/postgresql)
- Azure states that PgBouncer is installed by default in Azure Database for PostgreSQL. Postgres's process-per-connection model "makes it inefficient to maintain large numbers of idle connections". This matters most when the database count or per-tenant connections multiply. — [Azure PostgreSQL multitenant guidance](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/service/postgresql)
- Citus's multi-tenant guide uses one shared schema where every table has the tenant column (`company_id`), and "Citus requires that primary and foreign key constraints include the distribution column". Tenant-scoped queries route to one node with full SQL. For noisy neighbours it recommends moving large tenants to dedicated nodes (`isolate_tenant_to_new_shard(..., 'CASCADE')` + `citus_move_shard_placement`). It notes tenant sizes are often Zipfian: the largest of 10k tenants might hold about 2% of the data. — [Citus docs: Multi-tenant applications](https://docs.citusdata.com/en/stable/use_cases/multi_tenant.html)

**Laravel packages**
- stancl/tenancy (v3 docs): single-database tenancy has "lower devops complexity, but larger code complexity than multi-database tenancy". It is preferable when "there are too many shared resources between tenants". Scoping is done with the `BelongsToTenant` trait (global scope). "DB facade calls, or any other types of direct database queries, of course won't be scoped". Unique indexes must include the tenant column, and `unique`/`exists` validation rules are not tenant-scoped by default. — [Tenancy for Laravel v3: Single-database tenancy](https://tenancyforlaravel.com/docs/v3/single-database-tenancy)
- stancl/tenancy v4: the default setup is multi-database. It adds a PostgreSQL RLS mode that makes the app "feel like multi-db while using a single-db setup" (details in section 2). — [Tenancy for Laravel v4: PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)
- spatie/laravel-multitenancy v4: "works for multitenancy projects that need to use one or multiple databases". Its "philosophy ... is that it should only provide the bare essentials", namely determining the current tenant and running tasks when switching tenant, plus tenant-aware queued jobs and per-tenant Artisan commands. It has no opinion on which isolation model to use. — [spatie/laravel-multitenancy v4 introduction](https://spatie.be/docs/laravel-multitenancy/v4/introduction)

**Schema-per-tenant on PostgreSQL at scale** (mostly **older** mailing-list evidence, PostgreSQL 8.3–9.3)
- In a database with more than 20,000 schemas, a full `pg_dump` grew to over 24 hours, and dumping one nearly empty schema took 12 minutes. pg_dump "has to examine all database objects in order to trace dependencies", so it cannot limit itself to one schema. — [pgsql-performance thread "pg_dump and thousands of schemas" (2012, older)](https://www.postgrespro.com/list/thread-id/1803078)
- Heroku: a large number of tables and schemas can cause "out of shared memory" errors (`max_locks_per_transaction`) and slow tooling such as pg_dump. They recommend sharding and splitting schemas into several databases at scale. — [Heroku help: out of shared memory / max_locks_per_transaction](https://help.heroku.com/EW2G2AF7/how-can-i-resolve-the-error-error-out-of-shared-memory-hint-you-might-need-to-increase-max_locks_per_transaction)
- Percona names "consolidating many schemas into a single database" as a common cause of a large system catalog and poor performance. — [Percona: What hurts in PostgreSQL, part one](https://www.percona.com/blog/what-hurts-in-postgresql-part-one-temporary-tables)
- A schema-only dump of 248 schemas and 53,154 relations pushed one backend past 6 GB of memory (PostgreSQL 9.2.5). — [pgsql thread: pg_dump excessive memory (older)](https://www.postgrespro.com/list/thread-id/1538179)
- A 2026 vendor blog gives a rule of thumb of about 1,000–2,000 schemas per database. I found no official PostgreSQL limit, so treat this as **unverified**. — [kolonell.com SaaS multi-tenant Postgres 2026](https://kolonell.com/en/blog/saas-multi-tenant-postgres-architecture-2026-en)

### Inferences
- For Chatvia (many small or medium workspaces, shared AI/RAG pipelines, operator-wide analytics, one PostgreSQL 18 with vchord/pgvector extensions) the cited guidance points to a **single shared database keyed by workspace owner**, which is what the codebase already does. Database-per-tenant would multiply extension installs, migrations, Horizon/Reverb connection pools and embedding-storage locks per tenant. It would also make the operator's cross-tenant analytics a fan-out job (Azure's "cumbersome" warning).
- The **self-hosted Docker install by a single organization is already the "silo" model** (one stamp per customer, in Azure's terms). An open-source product therefore covers "I need full physical isolation or my own region or keys" by self-hosting or by a dedicated stamp. It does not need per-tenant databases inside the hosted SaaS. This matches the Azure hybrid advice: pooled for most, a dedicated stamp for those with unusual needs.
- Schema-per-tenant gives few benefits for Chatvia and carries real PostgreSQL catalog and pg_dump risks once there are thousands of tenants. Each schema would also need its own copies of vector/BM25 indexes, and the extensions are database-level anyway.
- "Delete my tenant" in a single database means a cascade delete keyed by the owner. Per the Azure restore quote, backups still hold the data until they expire. Unless per-tenant keys (crypto-shredding) are added, retention policy in the privacy docs covers this, not the schema design. This inference is not directly sourced.
- Noisy-neighbour control in a shared PostgreSQL is mostly application-level: per-workspace rate limits, queue fairness in Horizon, per-statement timeouts. Azure points to the Throttling and Rate Limiting patterns. PostgreSQL has no per-tenant resource governor (general knowledge, not verified in a source here).

### Gaps
- I found no AWS SaaS Factory or SaaS Lens page dated 2023–2026 with PostgreSQL-specific numbers. The AWS RLS blog is from 2020.
- The Citus 12 schema-based sharding announcement (2023) returned 404. The 1–10,000 tenants figure comes from the Azure page only.
- The stancl v3 multi-database pages (migrations across tenant DBs, `tenants:migrate`) were not fetched. The per-tenant migration cost is described from Azure, not from the Laravel package docs.
- No current (PostgreSQL 15–18) benchmark of catalog or pg_dump behaviour with thousands of schemas was found. The evidence is from older versions plus a 2026 vendor rule of thumb.

---

## 2. PostgreSQL RLS with Laravel: mechanics, pitfalls, packages, and defense-in-depth

### Takeaway
RLS works well in Laravel as a database-enforced backstop under Eloquent scoping. The app sets the tenant per
transaction with `set_config('app.tenant_id', ?, true)` (the bindable form of `SET LOCAL`), and policies read it
with `current_setting('app.tenant_id', true)`. Several conditions must hold:
- The app must connect as a role that is neither superuser, nor `BYPASSRLS`, nor the table owner. Otherwise, use `FORCE ROW LEVEL SECURITY`.
- Migrations, dumps and platform analytics need a separate privileged role.
- Long-lived processes (Horizon, Octane, Reverb) must use transaction-scoped settings or reset in `finally`, or the tenant leaks into the next job.
- PgBouncer transaction mode forbids session `SET`.

An unset tenant fails closed (no rows returned). The vendor and community sources disagree on whether RLS should
replace or complement app-level scopes.

### Cited Findings

**Core PostgreSQL behaviour (PostgreSQL 18 docs)**
- Default deny: with RLS enabled, "If no policy exists for the table, a default-deny policy is used, meaning that no rows are visible or can be modified." `TRUNCATE` and `REFERENCES` are not subject to RLS. — [PostgreSQL 18: Row Security Policies](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)
- Bypass: "Superusers and roles with the BYPASSRLS attribute always bypass the row security system". "Table owners normally bypass row security as well, though a table owner can choose to be subject to row security with ALTER TABLE ... FORCE ROW LEVEL SECURITY." — [PostgreSQL 18: Row Security Policies](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)
- `USING` vs `WITH CHECK`: without an explicit `WITH CHECK`, the policy "implicitly provides a WITH CHECK clause identical to its USING clause". Out-of-scope updates silently affect 0 rows, and bad inserts fail with "new row violates WITH CHECK OPTION". — [PostgreSQL 18: Row Security Policies](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)
- Covert channel: "Referential integrity checks, such as unique or primary key constraints and foreign key references, always bypass row security". Uniqueness errors can therefore reveal another tenant's values. — [PostgreSQL 18: Row Security Policies](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)
- Ordering and performance: policy expressions are evaluated "prior to any conditions or functions coming from the user's query" except `leakproof` functions. Policies that "consider only the current values in the row" are "the simplest and best-performing case". Sub-select policies over other tables can race and need care. — [PostgreSQL 18: Row Security Policies](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)
- Setting `row_security = off` does not bypass RLS. It raises an error if a query would be filtered, which is useful for checking that dumps are complete. — [PostgreSQL 18: Row Security Policies](https://www.postgresql.org/docs/current/ddl-rowsecurity.html)

**Tenant context pattern (shared login + session/transaction variable)**
- AWS recommends one shared app login with `USING (tenant_id = current_setting('app.current_tenant')::UUID)` over a role per tenant, which "doesn't scale well". The app should connect as a role that is neither owner nor `BYPASSRLS`. AWS also warns: "Using session variables may be incompatible with server-side connection pooling such as pgBouncer." **(older, 2020)** — [AWS Database Blog (2020)](https://aws.amazon.com/blogs/database/multi-tenant-data-isolation-with-postgresql-row-level-security/)
- Crunchy Data (Craig Kerstiens, 3 Apr 2024) gives the same advice: "Instead of a user per customer ... set a session variable when we connect". The policy reads `current_setting('rls.org_id', TRUE)` and is limited with `TO application`. The article uses a plain session `SET` and does not cover owner bypass, `FORCE`, or pooling. (Note: its example sets `app.current_org_id` but the policy reads `rls.org_id`, a mismatch in the article.) — [Crunchy Data: Row Level Security for Tenants in Postgres](https://www.crunchydata.com/blog/row-level-security-for-tenants-in-postgres)
- PgBouncer transaction pooling marks `SET/RESET`, `LISTEN`, `PREPARE/DEALLOCATE` and session advisory locks as "Never" supported. Protocol-level prepared statements need `max_prepared_statements` > 0. The page does not mention `SET LOCAL`. — [PgBouncer features](https://www.pgbouncer.org/features.html)
- Azure Database for PostgreSQL offers `session_variable` and `login_hook` extensions for holding tenant context. Azure warns RLS "might affect performance" and that extra indexes plus performance testing are needed. — [Azure PostgreSQL multitenant guidance](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/service/postgresql)
- Azure on adoption: RLS needs the tenant identity "propagated through the application and into the data store with each query", which "can be complex to design, implement, test, and maintain. Many multitenant solutions don't use row-level security because of those complexities." — [Azure storage-data approaches (2026)](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)

**Laravel implementations**
- *phpconference.com, "Invisible Isolation: Multi-Tenancy with PostgreSQL RLS" (Grzegorz Drozd, 2 Sep 2026)*:
  - A `SetTenantContext` middleware wraps the request in `DB::transaction()` and runs `SELECT set_config('app.current_tenant_id', ?, true)`. It calls this "the function-form equivalent of SET LOCAL" that can take bound parameters, since `SET`/`SET LOCAL` cannot be prepared.
  - A plain `SET` "persists for the entire database session", so a pooled connection carries a stale tenant into the next request. `SET LOCAL` "works correctly in both modes".
  - Queue jobs use a job middleware (`ApplyTenantContext`) that sets the tenant in a transaction. Artisan commands use a `--tenant` option via a `CommandStarting` listener or a base command.
  - The app role must have neither `SUPERUSER` nor `BYPASSRLS`. Run `pg_dump` and migrations as the owner, because "RLS filters its reads too, so the dump silently comes back short".
  - Use composite indexes with `tenant_id` leading. The author claims RLS "adds zero measurable overhead" with the right indexes (a single EXPLAIN shown, not a benchmark).
  - It argues global scopes become redundant once RLS exists. It does not cover `FORCE ROW LEVEL SECURITY`.
  - [phpconference.com (2026)](https://phpconference.com/blog/software-architecture/postgresql-row-level-security-multi-tenancy/)
- *stancl/tenancy v4 RLS mode*:
  - `PostgresRLSBootstrapper` switches to one dedicated RLS database user (`rls.user`, "one user for all tenants") and sets a session variable (default `my.current_tenant`) on bootstrap, resetting it on revert.
  - `TableRLSManager` follows foreign keys to `tenants` and generates nested `IN (SELECT ...)` policies. `TraitRLSManager` uses the `BelongsToTenant`/`BelongsToPrimaryModel` traits (at most two levels).
  - `tenants:rls` enables and forces RLS. The central user needs `BYPASSRLS`, or forcing must be turned off. Some hosts (Laravel Cloud) cannot grant `BYPASSRLS`.
  - Policies must be dropped before altering tables, and `tenants:rls` re-run after every migration.
  - Deep policies can be slow, so the docs suggest indexes or a direct `tenant_id` column.
  - The docs do not discuss pooling, queues or Octane.
  - [Tenancy for Laravel v4: PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)
- *leojeremy/laravel-postgres-rls* (PHP 8.3+, Laravel 12/13, PostgreSQL 15+, tested on PG18 + PHP 8.5/Laravel 13. **Very new: 0 stars, 2 commits, no releases**):
  - `RlsContext` uses `set_config()` with a bound value. In `transaction` mode it uses `SET LOCAL` semantics, so "the value dies at COMMIT or ROLLBACK". This mode is needed for PgBouncer transaction pooling, where tenant work must run inside `within()`, jobs need `RunAsDispatchingTenant`, and each request is one transaction.
  - The middleware means "a value left on a reused connection (Octane) never reaches a request". The tenant is written into the queue payload, and "The worker sets it before the job and restores what was there afterwards".
  - The policy is `tenant_isolation`, `USING (tenant_id = current_tenant_id())` with matching `WITH CHECK`. `current_tenant_id()` uses `current_setting(..., true)` + `NULLIF`, and the setup "fails closed".
  - `rls:audit` fails if the role is superuser or `BYPASSRLS`, if a tenant table is not `FORCE`d, if a policy is missing or wrong, if an extra PERMISSIVE policy widens access, or if `tenant_id` has no default or stamping. It warns on missing `tenant_id` indexes and on unique indexes without `tenant_id`.
  - It states: "RLS here guards against application bugs, not SQL injection."
  - [GitHub: leojeremy/laravel-postgres-rls](https://github.com/leojeremy/laravel-postgres-rls)
- *Radiergummi/laravel-rls* is a proof of concept. You enable isolation in a migration and choose a scope dimension. Search snippet only, not fetched. — [GitHub: Radiergummi/laravel-rls](https://github.com/Radiergummi/laravel-rls)
- Community posts argue for RLS because Eloquent global scopes are bypassed by `withoutGlobalScopes()` or raw SQL. They use `ENABLE` + `FORCE ROW LEVEL SECURITY` with `app.current_tenant_id`. These are quality-unverified dev.to posts (search snippets only). — [dev.to: Stop Leaking Tenant Data](https://dev.to/iprajapatiparesh/stop-leaking-tenant-data-postgresql-row-level-security-in-laravel-2pkl); [dev.to: Building multi-tenant SaaS with RLS in Laravel](https://dev.to/addwebsolutionpvtltd/building-multi-tenant-saas-with-row-level-security-in-laravel-3kd3)

**Performance**
- Supabase guidance:
  - "Add an index on every column your policies filter on". "An unindexed filter column turns a read into a sequential scan". A column only counts as indexed if it leads a btree index.
  - Wrap functions in `(select ...)` so the planner runs an `initPlan` once per statement "rather than calling the function on each row". This is only valid when the result does not depend on row data.
  - Use `TO <role>` so policies skip other roles. Use `security definer` lookup functions with `search_path = ''`.
  - The page gives no benchmark numbers.
  - [Supabase: Row Level Security](https://supabase.com/docs/guides/database/postgres/row-level-security)
- stancl v4 warns that deep, nested-subquery policies can produce slow queries. — [Tenancy for Laravel v4: PostgreSQL RLS](https://v4.tenancyforlaravel.com/postgres-rls/)

**Defense-in-depth vs replacement: sources disagree**
- The phpconference article (2026) says app-level scopes can be removed once RLS exists. — [phpconference.com](https://phpconference.com/blog/software-architecture/postgresql-row-level-security-multi-tenancy/)
- stancl v4 offers RLS as an alternative mode of single-database tenancy, and its trait manager reuses the existing `BelongsToTenant` traits. — [Tenancy for Laravel v4](https://v4.tenancyforlaravel.com/postgres-rls/)
- leojeremy frames RLS as "the tenant boundary" but scopes its guarantee to "application bugs, not SQL injection". — [laravel-postgres-rls](https://github.com/leojeremy/laravel-postgres-rls)
- Azure notes many solutions skip RLS because of its complexity. — [Azure storage-data](https://learn.microsoft.com/en-us/azure/architecture/guide/multitenant/approaches/storage-data)

### Inferences
- **Recommended shape for Chatvia (my synthesis):** keep the existing app-level owner scoping, where Actions and queries already key on `$agent->user_id` / `$user->workspace()->id`. Add RLS as a backstop on the highest-risk shared tables (conversations, messages, chunks, sources, leads). Removing app scoping would lose readable intent, the 403/404 semantics, and index-friendly explicit predicates. Supabase also advises explicit filters for performance.
- **Use a denormalized owner column**, for example `workspace_id`/`user_id`, on each protected table, with a direct-column policy. Avoid FK-chain subquery policies (stancl's `TableRLSManager` style). PostgreSQL docs and stancl both say row-local policies perform best. Chatvia already keys data to the owner, which helps, but tables reached only via joins (messages → conversation → agent → user) would need the column added. Per the owner's rules, that means editing the create migrations.
- **Roles:** three roles. (1) The app role: not owner, no `BYPASSRLS`, policies `TO app`. (2) The migration/owner role, used by `php artisan migrate`, `pg_dump` and the `chatvia-migrate` Docker step. (3) A platform role with `BYPASSRLS`, or an explicit "central" policy, for Filament admin, Account-Analytics MCP, cross-tenant analytics jobs and maintenance commands. As a backstop, `FORCE ROW LEVEL SECURITY` on every protected table covers the case where the app accidentally connects as owner. That is likely in single-user Docker installs, where one DB user owns everything.
- **Laravel binding detail:** Laravel's pgsql PDO uses native prepares (`ATTR_EMULATE_PREPARES = false` by default; general knowledge, not verified here). So `DB::statement('SET LOCAL app.tenant_id = ?', [...])` fails, and `select set_config('app.tenant_id', ?, true)` is the form to use (consistent with phpconference).
- **Transaction-scoped context vs Chatvia's long SSE streams:** wrapping a whole HTTP request in one transaction (the phpconference and leojeremy transaction mode) would hold a transaction open for the full length of an LLM stream. That means many seconds idle-in-transaction, which hurts vacuum and pins a PgBouncer server connection. Two safer alternatives:
  - (a) Set the tenant per unit of DB work inside short `DB::transaction()` blocks. An `RlsContext::within()`-style helper does this.
  - (b) Use session-level `set_config(..., false)` on a direct (non-pooled) connection, reset in `terminate`/`finally` and at the start of every job. This only works with php-fpm direct connections or PgBouncer *session* mode.
  Chatvia currently runs php-fpm (Octane was reverted), so a request does not reuse a connection across requests unless persistent PDO is on. But **Horizon workers and Reverb are long-lived** and need explicit set/reset per job. A Horizon job without tenant context would read zero rows (fail-closed), which can show up as "silent empty" bugs rather than errors.
- **Global helpers like `MessageAllowance`, analytics rollups and listeners that run across workspaces** would each need either a tenant context or the platform role. That makes RLS a significant cross-cutting change, which falls under the owner's "plan before big changes" rule.
- **Covert channels:** unique constraints (for example, globally unique slugs or external ids) can reveal existence across tenants even with RLS. This is acceptable for UUIDv7 external ids but worth noting for user-chosen slugs.

### Gaps
- No published benchmark (2023–2026) measures RLS overhead for a direct `tenant_id = current_setting(...)` policy on a large table. The "zero measurable overhead" claim is one EXPLAIN in one article. Supabase's separate performance guide (said to have numbers) was not fetched.
- I found no source on how RLS interacts with Laravel's nested `DB::transaction()` savepoints, or with `set_config(..., true)` inside a savepoint that is rolled back. This needs testing.
- stancl v4 docs do not say whether the bootstrapper uses `SET` or `set_config`, or how it behaves under PgBouncer, queues or Octane.
- The PgBouncer docs do not explicitly state that `SET LOCAL` is safe in transaction mode. The claim rests on its transaction scope (phpconference, leojeremy), not on PgBouncer's own docs.
- Nile and Supabase multi-tenant RLS articles were not fetched (tool budget).

---

## 3. Vector search (pgvector / VectorChord) per tenant

### Takeaway
Approximate vector indexes filter *after* the scan, so a selective tenant filter can return far fewer than k
rows. Ways to address this:
- pgvector: iterative index scans (0.8.0+), a B-tree on the filter column for very selective filters, partial indexes for a few values, or partitioning for many values.
- VectorChord (used by Chatvia): `vchordrq.prefilter` (0.4.0+), which pays off when the filter is strict and cheap, as a per-tenant filter usually is.

### Cited Findings
- pgvector: "With approximate indexes, filtering is applied *after* the index is scanned." With HNSW and default `hnsw.ef_search = 40`, a condition matching 10% of rows returns about 4 rows on average. Exact (B-tree) indexes on the filter column suit selective filters. "If filtering by only a few distinct values, consider partial indexing. If filtering by many different values, consider partitioning." — [pgvector README](https://github.com/pgvector/pgvector)
- pgvector iterative index scans (0.8.0+): `hnsw.iterative_scan = strict_order | relaxed_order` keeps scanning until enough rows match. Relaxed "provides better recall". The scan is bounded by `hnsw.max_scan_tuples` (default 20,000) and `hnsw.scan_mem_multiplier`. IVFFlat has `ivfflat.max_probes`. — [pgvector README](https://github.com/pgvector/pgvector)
- VectorChord: without prefilter, the index scans first and PostgreSQL checks filters after, doing "a large amount of redundant computation". `vchordrq.prefilter` (v0.4.0+, default off) lets the index "pre-evaluate the condition and discard non-matching rows before computing their distances". It is "only recommended when the filter is strict (eliminating many rows) and cheap". On LAION-5m, QPS improved by about 200% at 1% selectivity and about 5% at 10%. It is not supported by `vchordg`. — [VectorChord docs: Prefilter](https://docs.vectorchord.ai/vectorchord/usage/prefilter.html)
- VectorChord recall knobs: `vchordrq.probes` (3–10% of `lists` recommended) and `vchordrq.epsilon` (default 1.9, higher = more rerank and recall). — [VectorChord docs: Search](https://docs.vectorchord.ai/vectorchord/usage/search.html)

### Inferences
- With many tenants, a per-tenant or per-agent filter is very selective (well under 1% of chunks), which is exactly where post-filtering hurts recall and where `vchordrq.prefilter` gives the most speedup. For small tenants, an exact scan over that tenant's rows (B-tree on agent or owner, then exact distance) may beat ANN altogether. This is the pgvector "exact indexes for selective filters" advice.
- Per-tenant partial indexes do not scale to many tenants (one index per tenant). Partitioning `chunks` by `HASH(owner)` gives smaller per-partition ANN indexes, but each partition still mixes tenants, so the filter is still needed.
- If RLS is added to `chunks`, the policy predicate becomes one more filter on the vector index scan. Because policy quals run before user quals unless leakproof (PostgreSQL docs), its effect on the vector index plan and on prefilter must be checked with EXPLAIN. Coordinate with the `rag-retrieval-enhancer` eval harness before turning it on.

### Gaps
- I found no source on recall (as opposed to QPS) with `vchordrq.prefilter`, and none on how RLS policies interact with vchordrq or HNSW index scans.

---

## 4. Table partitioning by tenant: when it helps

### Takeaway
Partitioning by tenant helps when the tenant key is in most `WHERE` clauses, when individual tables are too large
for good index and cache locality, or when you want to delete a tenant's data fast with `DETACH`/`DROP`. With
many small tenants, use `HASH` partitioning with a fixed partition count. One partition per tenant (`LIST`)
breaks down past a few thousand partitions.

### Cited Findings
- PostgreSQL 18 docs: partition by the columns that "most commonly appear in WHERE clauses", so pruning works. "Removal of unwanted data is also a factor ... An entire partition can be detached fairly quickly." — [PostgreSQL 18: Table Partitioning, best practices](https://www.postgresql.org/docs/current/ddl-partitioning.html)
- Too few partitions means "indexes remain too large and ... data locality remains poor". Too many means "longer query planning times and higher memory consumption". "The query planner is generally able to handle partition hierarchies with up to a few thousand partitions fairly well, provided that typical queries allow the query planner to prune all but a small number of partitions." Per-session memory grows because "each partition requires its metadata to be loaded into the local memory of each session that touches it". — [PostgreSQL 18: Table Partitioning](https://www.postgresql.org/docs/current/ddl-partitioning.html)
- On per-customer partitions: "if you choose to have one partition per customer and you currently have a small number of large customers, consider the implications if ... you instead find yourself with a large number of small customers. In this case, it may be better to choose to partition by HASH and choose a reasonable number of partitions rather than trying to partition by LIST". — [PostgreSQL 18: Table Partitioning](https://www.postgresql.org/docs/current/ddl-partitioning.html)
- `DROP TABLE` on a partition "can very quickly delete millions of records" but takes an `ACCESS EXCLUSIVE` lock on the parent. `DETACH PARTITION ... CONCURRENTLY` needs only `SHARE UPDATE EXCLUSIVE`. — [PostgreSQL 18: Table Partitioning](https://www.postgresql.org/docs/current/ddl-partitioning.html)
- pgvector recommends partitioning when filtering "by many different values". — [pgvector README](https://github.com/pgvector/pgvector)
- Citus (distributed) uses the tenant column as the distribution key and requires primary and foreign keys to include it. The same constraint applies to native partitioning, where unique constraints must include the partition key. — [Citus multi-tenant docs](https://docs.citusdata.com/en/stable/use_cases/multi_tenant.html)

### Inferences
- For Chatvia, partitioning only becomes worth it for the largest append-heavy tables (`chunks` with embeddings, messages, analytics events) once they are tens of millions of rows. Even then, `HASH(owner_id)` with a fixed count (for example 16–64) is the safe choice. Per-tenant `LIST` partitions bring back the schema-per-tenant catalog problems. Fast "delete my tenant" via `DROP PARTITION` only works with per-tenant `LIST` partitions, so with hash partitioning, tenant deletion stays a keyed `DELETE`.
- Partitioning would force every PK and unique index on those tables to include the owner key. Per the owner's rules, that means editing the create migrations, and it interacts with external_id uniqueness (globally unique uuid7). Treat it as a later scaling step, not part of a tenancy-isolation change.

### Gaps
- I found no 2023–2026 source with measured gains from tenant-hash partitioning of pgvector/VectorChord tables. The pgvector README gives the advice without numbers.
