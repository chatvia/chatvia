<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\GenerationLimits;

use App\Identity\Workspace\Models\GenerationLimit;
use App\Identity\Workspace\Models\Workspace;

/**
 * Sets or clears a workspace's monthly cap on the pictures and audio files
 * its agents make. Null removes the cap. A number keeps what was already made
 * this month, so lowering the cap can stop new files right away.
 */
class SetGenerationLimitAction
{
    public function execute(Workspace $workspace, ?int $monthlyLimit): void
    {
        if ($monthlyLimit === null) {
            GenerationLimit::query()->where('workspace_id', $workspace->id)->delete();

            return;
        }

        $limit = GenerationLimit::query()->firstOrNew(['workspace_id' => $workspace->id]);

        if (! $limit->exists || ! $limit->isCurrentPeriod()) {
            $limit->period_starts_at = now()->startOfMonth();
            $limit->generations_used = 0;
        }

        $limit->monthly_limit = $monthlyLimit;
        $limit->save();
    }
}
