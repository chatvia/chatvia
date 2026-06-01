<?php

namespace Domain\Ingestion\Actions\Stats;

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
        }
    }
}
