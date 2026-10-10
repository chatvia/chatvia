<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete a workspace teammate (an `employee` sub-user) together with
 * all of their personal data, while leaving everything the *workspace* owns intact.
 *
 * `$employee->delete()` drives most of it through the FK graph:
 *  - CASCADE clears the employee's own auth artefacts — `user_socials`,
 *    `passkeys`, `user_known_devices`, `data_exports` — plus the Spatie role
 *    pivots (the `deleting` hook on HasRoles).
 *  - SET NULL unlinks them everywhere they appear only as an actor:
 *    `chat_conversations.handoff_user_id`, `agent_activity.user_id`,
 *    `guardrail_events.user_id`, `security_audit_log.user_id`,
 *    `eval_runs.triggered_by_user_id`, `leads.assigned_to`. Those rows belong to
 *    the workspace and survive — only the person is detached. The audit logs
 *    keep their denormalised name/email on purpose.
 *
 * Three things the graph can't get right on its own, done here first in one
 * transaction:
 *  1. Workspace resources an employee authored are keyed to that employee
 *     (`agent_prompts`, `source_*`, `functions`, `mcp_servers`, `topics`) and
 *     would CASCADE away with them — or, for `mcp_servers` (SET NULL), silently
 *     turn into a global built-in. Hand them back to the workspace owner.
 *  2. `team_user` is RESTRICT, so the membership pivot blocks the delete until
 *     it is cleared.
 *  3. Sanctum tokens (`personal_access_tokens`, polymorphic — no FK) and the
 *     employee's HTTP `sessions` rows (no FK) have nothing to cascade them and
 *     would orphan; delete them explicitly.
 *
 * No Redis chat-memory flush: chat memory is keyed to chatters via
 * `chat_sessions.user_id`, and an employee never owns a chat session — their
 * only conversation footprint is `handoff_user_id`, which nulls automatically.
 *
 * Callers must pass an `employee`-type user; owners / admins / chatters are out
 * of scope and must be rejected before this Action.
 */
class DeleteEmployeeAction
{
    /**
     * Workspace-owned resource tables that may carry an employee's `user_id`
     * (historically, before ownership was keyed to the workspace at creation).
     * Every one is `user_id`-cascading except `mcp_servers` (SET NULL) — both
     * lose the row's workspace value on delete, so reassign before deleting.
     */
    private const array REASSIGNABLE_TABLES = [
        'agent_prompts',
        'source_files',
        'source_texts',
        'source_websites',
        'source_youtube_videos',
        'functions',
        'mcp_servers',
        'topics',
    ];

    public function execute(User $employee): void
    {
        $ownerId = $employee->workspace_id;

        DB::transaction(function () use ($employee, $ownerId): void {
            // 1. Hand any workspace resources this employee authored back to the
            //    owner before the cascade can take them.
            if ($ownerId !== null) {
                foreach (self::REASSIGNABLE_TABLES as $table) {
                    DB::table($table)
                        ->where('user_id', $employee->id)
                        ->update(['user_id' => $ownerId]);
                }
            }

            // 2. team_user is RESTRICT — clear the membership pivot first.
            DB::table('team_user')->where('user_id', $employee->id)->delete();

            // 3. No FK reaches these, so they would orphan.
            $employee->tokens()->delete();                                    // Sanctum (polymorphic)
            DB::table('sessions')->where('user_id', $employee->id)->delete(); // HTTP sessions

            // The rest cascades / nulls out via FK constraints.
            $employee->delete();
        });
    }
}
