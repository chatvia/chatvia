<?php

namespace Domain\Guardrail\Actions;

use Domain\Guardrail\Models\GuardrailEvent;

/**
 * Deletes guardrail trigger rows older than the retention window.
 */
class PruneGuardrailEventsAction
{
    /**
     * @return int how many rows were deleted
     */
    public function execute(int $days): int
    {
        return GuardrailEvent::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
