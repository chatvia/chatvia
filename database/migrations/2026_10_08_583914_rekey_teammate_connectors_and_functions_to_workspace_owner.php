<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Connectors (mcp_servers) and webhook functions are workspace resources, but
 * the connector OAuth callback used to save them under the teammate who
 * clicked "Connect". Those rows were invisible to the workspace's agents and
 * to everyone else on the team, and a teammate's deletion would orphan them.
 *
 * Move every row owned by a teammate (users.type = 'employee') to that
 * teammate's workspace owner. A row is skipped — left where it is — when the
 * owner already has a row with the same slug / function name (the unique
 * indexes forbid a duplicate); if several teammates hold the same slug, only
 * the most recently updated one moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            WITH candidates AS (
                SELECT s.id,
                       u.workspace_id AS owner_id,
                       ROW_NUMBER() OVER (PARTITION BY u.workspace_id, s.slug ORDER BY s.updated_at DESC NULLS LAST, s.id DESC) AS rn
                FROM mcp_servers s
                JOIN users u ON u.id = s.user_id
                WHERE u.type = 'employee'
                  AND u.workspace_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM mcp_servers o WHERE o.user_id = u.workspace_id AND o.slug = s.slug
                  )
            )
            UPDATE mcp_servers s
            SET user_id = c.owner_id, updated_at = NOW()
            FROM candidates c
            WHERE s.id = c.id AND c.rn = 1
            SQL);

        DB::statement(<<<'SQL'
            WITH candidates AS (
                SELECT f.id,
                       u.workspace_id AS owner_id,
                       ROW_NUMBER() OVER (PARTITION BY u.workspace_id, f.internal_name ORDER BY f.updated_at DESC NULLS LAST, f.id DESC) AS rn
                FROM functions f
                JOIN users u ON u.id = f.user_id
                WHERE u.type = 'employee'
                  AND u.workspace_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM functions o WHERE o.user_id = u.workspace_id AND o.internal_name = f.internal_name
                  )
            )
            UPDATE functions f
            SET user_id = c.owner_id, updated_at = NOW()
            FROM candidates c
            WHERE f.id = c.id AND c.rn = 1
            SQL);
    }

    public function down(): void
    {
        // The original teammate owner is not recorded; moving rows back would
        // hide them from the workspace again.
    }
};
