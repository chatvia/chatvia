<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete a workspace teammate together with all of their personal
 * data, while leaving everything the *workspace* owns intact (workspace data
 * is keyed to the workspace, never to the teammate who created it).
 *
 * `$employee->delete()` drives most of it through the FK graph:
 *  - CASCADE clears the teammate's own artefacts — the workspace membership,
 *    `user_socials`, `passkeys`, `user_known_devices`, `data_exports`, their
 *    preview chat sessions — plus the Spatie role pivots (the `deleting` hook
 *    on HasRoles).
 *  - SET NULL unlinks them everywhere they appear only as an actor:
 *    `chat_conversations.handoff_user_id`, `agent_activities.user_id`,
 *    `guardrail_events.user_id`,
 *    `security_audit_logs.user_id`, `eval_runs.triggered_by_user_id`. Those rows
 *    belong to the workspace and survive — only the person is detached. The
 *    audit logs keep their denormalised name/email on purpose.
 *
 * Two things the graph can't get right on its own, done here first in one
 * transaction:
 *  1. `team_user` is RESTRICT, so the team pivot blocks the delete until it
 *     is cleared.
 *  2. Sanctum tokens (`personal_access_tokens`, polymorphic — no FK) and the
 *     teammate's HTTP `sessions` rows (no FK) have nothing to cascade them and
 *     would orphan; delete them explicitly.
 *
 * Callers must pass a teammate; owners and chatters are out of scope and must
 * be rejected before this Action.
 */
class DeleteEmployeeAction
{
    public function execute(User $employee): void
    {
        DB::transaction(function () use ($employee): void {
            // 1. team_user is RESTRICT — clear the team pivot first.
            DB::table('team_user')->where('user_id', $employee->id)->delete();

            // 2. No FK reaches these, so they would orphan.
            $employee->tokens()->delete();                                    // Sanctum (polymorphic)
            DB::table('sessions')->where('user_id', $employee->id)->delete(); // HTTP sessions

            // The rest cascades / nulls out via FK constraints.
            $employee->delete();
        });
    }
}
