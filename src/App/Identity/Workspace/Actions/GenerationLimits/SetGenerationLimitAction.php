<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\GenerationLimits;

use App\Identity\Workspace\Data\GenerationLimits\GenerationLimitData;
use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\Workspace;
use Support\Radar\IssueTypeKey;
use Support\Radar\ServiceKey;
use Support\Radar\ServiceRecovered;

/**
 * Sets or clears a workspace's monthly caps on what its agents make: one on
 * pictures and audio files, one on videos. A null cap leaves that kind
 * uncapped; both null removes the row. A number keeps what was already made
 * this month, so lowering a cap can stop new files right away.
 */
class SetGenerationLimitAction
{
    public function execute(Workspace $workspace, GenerationLimitData $limits): void
    {
        // A new limit closes Radar's "reached its generation limit" issue.
        ServiceRecovered::dispatch(ServiceKey::WORKSPACES, (string) $workspace->id, IssueTypeKey::GENERATION_LIMIT_REACHED);

        if ($limits->isEmpty()) {
            GenerationLimit::query()->where('workspace_id', $workspace->id)->delete();

            return;
        }

        $limit = GenerationLimit::query()->firstOrNew(['workspace_id' => $workspace->id]);

        if (! $limit->exists || ! $limit->isCurrentPeriod()) {
            $limit->period_starts_at = now()->startOfMonth();
            $limit->generations_used = 0;
            $limit->videos_used = 0;
        }

        $limit->monthly_limit = $limits->monthlyLimit;
        $limit->monthly_video_limit = $limits->monthlyVideoLimit;
        $limit->save();
    }
}
