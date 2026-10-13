<?php

declare(strict_types=1);

namespace Domain\Guardrail\Actions;

use Domain\Guardrail\Models\GuardrailEvent;

/**
 * Deletes guardrail trigger rows older than the retention window. Rows go in
 * batches, so one run never holds a huge delete; the BRIN index on
 * `created_at` finds them.
 */
class PruneGuardrailEventsAction
{
    /**
     * @return int how many rows were deleted
     */
    public function execute(int $days, int $batchSize = 10000): int
    {
        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $batch = GuardrailEvent::query()
                ->whereIn('id', GuardrailEvent::query()->select('id')->where('created_at', '<', $cutoff)->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
