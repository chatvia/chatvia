<?php

declare(strict_types=1);

namespace Domain\Form\Actions;

use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\DB;
use Support\License\Entitlements;

/**
 * Deletes captured leads (submitted `form_submissions`) older than the lead
 * retention a workspace chose in its security policy. Workspaces that left it
 * blank keep their leads, and so does every workspace while the install has
 * no Enterprise key (custom retention is an Enterprise feature). Pending and abandoned forms are not touched here:
 * {@see PruneAbandonedFormSubmissionsAction} removes those.
 *
 * Rows go in batches, so one run never holds a huge delete, and nothing
 * references a lead, so a plain delete is enough.
 */
class PruneWorkspaceLeadsAction
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    /**
     * @return int how many leads were deleted
     */
    public function execute(int $batchSize = 5000): int
    {
        if (! $this->entitlements->enterprise()) {
            return 0;
        }

        // Never below the floor, even for a value saved before it was raised.
        $floor = (int) config('chatvia.retention.leads_floor_days', 1);
        $batchSize = max(1, $batchSize);

        $windows = WorkspaceSecurityPolicy::query()
            ->whereNotNull('retention_leads_days')
            ->pluck('retention_leads_days', 'workspace_id');

        $deleted = 0;

        foreach ($windows as $workspaceId => $days) {
            $cutoff = now()->subDays(max($floor, (int) $days));

            do {
                // Served by the partial index form_submissions_workspace_submitted_idx.
                $batch = DB::table('form_submissions')
                    ->whereIn('id', fn ($query) => $query->select('id')
                        ->from('form_submissions')
                        ->where('workspace_id', $workspaceId)
                        ->where('status', FormSubmissionStatus::Submitted->value)
                        ->where('submitted_at', '<', $cutoff)
                        ->limit($batchSize))
                    ->delete();

                $deleted += $batch;
            } while ($batch > 0);
        }

        return $deleted;
    }
}
