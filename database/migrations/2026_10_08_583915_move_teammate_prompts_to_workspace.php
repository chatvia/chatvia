<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reusable prompts are a workspace resource: every read and write is now scoped
 * to the workspace owner. Prompts that a teammate created before that rule
 * existed are still keyed to the teammate, so they would vanish from the
 * Prompts page and be deleted with the teammate's account. Move them to the
 * teammate's workspace.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE agent_prompts
            SET user_id = users.workspace_id
            FROM users
            WHERE agent_prompts.user_id = users.id
              AND users.type = 'employee'
              AND users.workspace_id IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        // The original author is not recorded, so the move cannot be undone.
        // Leaving the prompts with the workspace is the correct state anyway.
    }
};
