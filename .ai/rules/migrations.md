---
paths:
  - 'database/migrations/**'
  - 'database/settings/**'
---

# Migrations and the database

## Migrations only move forward
The app is in production, and installs upgrade from the last tag. A schema change is a new migration (`add_*`, `alter_*`, `drop_*`). Never edit a migration that is in a tagged release: it has already run, so the edit reaches new installs only and the two drift apart. A data fix an upgrade needs is a migration too. Never read `.env` in a migration, apart from the embedding columns below.

Test a migration against the last tag's schema with rows in it, not only on an empty database. Keep it safe on a large table: say so in the change if it locks or rewrites one. Dropping a column or table destroys data, so ask first, and stop reading it for a release before dropping it. Keep `down()` undoing what `up()` did.

## Name tables and columns by convention
Tables are plural snake_case. A pivot table joins the two singular model names in alphabetical order (`agent_chatter`) and has no timestamps. Foreign keys are `{model}_id`, qualified by role when needed (`triggered_by_user_id`). Date columns are `{verb}_at`. New boolean columns read as a question (`is_enabled`, `has_answer`). Never rename an existing table or column without a plan.

## Public ids and timestamps
Public ids are `$table->uuid('external_id')->unique()` (NOT NULL), filled with uuid7 by `HasExternalId`. Use `timestamp`, not `timestampTz`.

## Every foreign key says what happens on delete
Write `cascadeOnDelete()`, `nullOnDelete()` or `restrictOnDelete()` on every foreign key. Tenant child tables carry `workspace_id` with a composite key `(workspace_id, x_id) → parent(workspace_id, id)`, and the parent has `unique(['workspace_id', 'id'])`.

## Index what you filter and sort on
Postgres does not index a foreign key by itself: add `->index()` or a composite index that starts with it. Order composite indexes by the leftmost-prefix rule (`(agent_id, created_at)`). Give mostly-NULL columns a partial index (`WHERE col IS NOT NULL`). A case-insensitive lookup needs an expression index (`lower(email)`).

## Enforce invariants in the database
Use unique, partial-unique and expression-unique indexes and `CHECK` constraints, written with `DB::statement()` inside the migration. Enum columns use `$table->enum()`, which becomes a `CHECK`; a new enum case needs a new migration that drops that constraint and adds it again with the new list.

## JSON only for schemaless data
Store JSON as `jsonb`, and only for data without a fixed shape. If you query a key, make it a real column.

## Embedding columns come from the install
Embedding columns use `$table->embedding()`; the type and size are chosen at install by `EmbeddingStorage`. Never hard-code `vector`, `halfvec` or a dimension.

## Admin options are settings, not columns or .env
Anything an admin may change is a spatie settings property in `App\Admin\Settings` plus a line in its `database/settings` migration, not a new table column or an `.env` value.
