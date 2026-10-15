---
paths:
  - 'database/migrations/**'
  - 'database/settings/**'
---

# Migrations and the database

## One create migration per table until release
The app is not released yet: each table has one `create_*_table` migration that holds its whole schema. A schema change edits that migration. Never add `add_*` / `alter_*`, data-fix or `.env`-reading migrations, and no compatibility shims. Keep `down()` dropping what `up()` created.

## Name tables and columns by convention
Tables are plural snake_case. A pivot table joins the two singular model names in alphabetical order (`agent_chatter`) and has no timestamps. Foreign keys are `{model}_id`, qualified by role when needed (`triggered_by_user_id`). Date columns are `{verb}_at`. New boolean columns read as a question (`is_enabled`, `has_answer`). Never rename an existing table or column without a plan.

## Public ids and timestamps
Public ids are `$table->uuid('external_id')->unique()` (NOT NULL), filled with uuid7 by `HasExternalId`. Use `timestamp`, not `timestampTz`.

## Every foreign key says what happens on delete
Write `cascadeOnDelete()`, `nullOnDelete()` or `restrictOnDelete()` on every foreign key. Tenant child tables carry `workspace_id` with a composite key `(workspace_id, x_id) → parent(workspace_id, id)`, and the parent has `unique(['workspace_id', 'id'])`.

## Index what you filter and sort on
Postgres does not index a foreign key by itself: add `->index()` or a composite index that starts with it. Order composite indexes by the leftmost-prefix rule (`(agent_id, created_at)`). Give mostly-NULL columns a partial index (`WHERE col IS NOT NULL`). A case-insensitive lookup needs an expression index (`lower(email)`).

## Enforce invariants in the database
Use unique, partial-unique and expression-unique indexes and `CHECK` constraints, written with `DB::statement()` inside the create migration. Enum columns use `$table->enum()`, which becomes a `CHECK`; a new enum case must be added to that list too.

## JSON only for schemaless data
Store JSON as `jsonb`, and only for data without a fixed shape. If you query a key, make it a real column.

## Embedding columns come from the install
Embedding columns use `$table->embedding()`; the type and size are chosen at install by `EmbeddingStorage`. Never hard-code `vector`, `halfvec` or a dimension.

## Admin options are settings, not columns or .env
Anything an admin may change is a spatie settings property in `App\Admin\Settings` plus a line in its `database/settings` migration, not a new table column or an `.env` value.
