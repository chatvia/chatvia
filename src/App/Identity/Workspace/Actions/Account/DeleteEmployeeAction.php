<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
 * Their own files (`workspaces/{workspace}/members/{external_id}/…`: data
 * export ZIPs, profile photo, settings images) have no row left to clean them, so the prefix is wiped on
 * every disk such files live on once the rows are gone.
 *
 * Callers must pass a teammate; owners and chatters are out of scope and must
 * be rejected before this Action.
 */
class DeleteEmployeeAction
{
    /**
     * The disks a person's own files can live on: data exports on `local`,
     * the profile photo and settings images on the assets disk, and the
     * default (`private`) disk, which is the S3 bucket when Settings → Storage
     * picks one.
     */
    private const array OWN_FILE_DISKS = ['local', User::PROFILE_IMAGE_DISK, 'private'];

    public function execute(User $employee): void
    {
        $storageRoot = $employee->storageRoot();

        DB::transaction(function () use ($employee): void {
            // 1. team_user is RESTRICT — clear the team pivot first.
            DB::table('team_user')->where('user_id', $employee->id)->delete();

            // 2. No FK reaches these, so they would orphan.
            $employee->tokens()->delete();                                    // Sanctum (polymorphic)
            DB::table('sessions')->where('user_id', $employee->id)->delete(); // HTTP sessions

            // The rest cascades / nulls out via FK constraints.
            $employee->delete();
        });

        $this->wipeOwnFiles($storageRoot);
    }

    /**
     * Best-effort, disk by disk: the teammate is already gone, and a storage
     * outage must not turn a finished delete into an error.
     */
    private function wipeOwnFiles(string $storageRoot): void
    {
        foreach (self::OWN_FILE_DISKS as $disk) {
            try {
                Storage::disk($disk)->deleteDirectory($storageRoot);
            } catch (Throwable $e) {
                Log::warning('Teammate deletion: failed to wipe their storage tree.', [
                    'disk' => $disk,
                    'prefix' => $storageRoot,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
