<?php

namespace Domain\Analytics\Actions\Analysis;

use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Collection;

/**
 * Queues the end-of-life analysis ({@see AnalyzeConversationAction}) for every
 * conversation that is old enough (`chatvia.analytics.analysis.scan_age_hours`)
 * and not analysed yet. Idempotent: the job stamps analyzed_at, and a
 * conversation already analysed is skipped by the job itself.
 */
class QueuePendingConversationAnalysisAction
{
    /**
     * @return int how many conversations were queued
     */
    public function execute(int $chunkSize = 100): int
    {
        $cutoff = now()->subHours((int) config('chatvia.analytics.analysis.scan_age_hours', 6));
        $queued = 0;

        // analyzed_at is set by the queued job (async), so it stays NULL for the
        // duration of this run — chunkById paginates by id and won't loop. The
        // conversations_pending_analysis_idx partial index serves this predicate.
        Conversation::query()
            ->whereNull('analyzed_at')
            ->where('created_at', '<', $cutoff)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(max(1, $chunkSize), function (Collection $conversations) use (&$queued): void {
                foreach ($conversations as $conversation) {
                    AnalyzeConversationJob::dispatch($conversation);
                    $queued++;
                }
            });

        return $queued;
    }
}
