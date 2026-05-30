<?php

namespace Domain\Ingestion\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class UpdateRetrievalStatsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $results,
        public int $agentId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->results)) {
            return;
        }

        $resultsByType = collect($this->results)->groupBy('chunkable_type');

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
                ->where('agent_id', $this->agentId)
                ->where('sourceable_type', $type)
                ->whereIn('sourceable_id', $ids)
                ->update([
                    'retrieval_count' => DB::raw('retrieval_count + 1'),
                    'last_retrieved_at' => now(),
                ]);
        }
    }
}
