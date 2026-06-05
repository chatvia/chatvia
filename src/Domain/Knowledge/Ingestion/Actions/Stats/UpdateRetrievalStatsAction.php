<?php

namespace Domain\Knowledge\Ingestion\Actions\Stats;

use Domain\Analytics\Events\SourceRetrieved;
use Illuminate\Support\Facades\DB;

class UpdateRetrievalStatsAction
{
    /**
     * @param  array<int, array{chunkable_type: string, chunkable_id: int|string}>  $results
     */
    public function execute(array $results, int $agentId): void
    {
        if (empty($results)) {
            return;
        }

        $resultsByType = collect($results)->groupBy('chunkable_type');

        $retrieved = [];

        foreach ($resultsByType as $type => $typeResults) {
            $ids = collect($typeResults)->pluck('chunkable_id')->unique()->values();

            if ($ids->isEmpty() || ! class_exists($type)) {
                continue;
            }

            // All source models (SourceFile/SourceWebsite/SourceText/SourceYoutubeVideo)
            // expose both columns by convention — no information_schema lookup per job.
            $type::query()
                ->whereIn('id', $ids)
                ->update([
                    'retrieval_count' => DB::raw('retrieval_count + 1'),
                    'last_retrieved_at' => now(),
                ]);

            DB::table('agent_sources')
                ->where('agent_id', $agentId)
                ->where('sourceable_type', $type)
                ->whereIn('sourceable_id', $ids)
                ->update([
                    'retrieval_count' => DB::raw('retrieval_count + 1'),
                    'last_retrieved_at' => now(),
                ]);

            foreach ($ids as $id) {
                $retrieved[] = ['type' => $type, 'id' => (int) $id];
            }
        }

        // Per-day retrieval rollup (date-bucketed, unlike the all-time counter
        // updated above) so the Sources analytics page can filter by date range.
        if ($retrieved !== []) {
            // Denormalise the workspace owner onto the rollup so it survives
            // agent deletion and is queryable for account-wide stats.
            $userId = (int) DB::table('agents')->where('id', $agentId)->value('user_id');
            SourceRetrieved::dispatch($agentId, $userId, $retrieved, now());
        }
    }
}
