<?php

namespace Domain\Ingestion\Jobs;

use Domain\Ingestion\Models\Chunk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $documentIds = collect($this->results)->pluck('id')->toArray();

        // Update chunk retrieval counts
        Chunk::query()
            ->whereIn('id', $documentIds)
            ->update([
                'last_retrieved_at' => now(),
                'retrieval_count' => DB::raw('retrieval_count + 1'),
            ]);

        // Update retrieval counts for different chunkable types
        $resultsByType = collect($this->results)->groupBy('chunkable_type');

        foreach ($resultsByType as $type => $typeResults) {
            $ids = collect($typeResults)->pluck('chunkable_id')->unique()->toArray();

            if (empty($ids) || ! class_exists($type)) {
                continue;
            }

            // Update the source model's retrieval count if it has the column
            $this->updateSourceModelRetrievalCount($type, $ids);

            // Update agent_sources retrieval stats for this specific agent (works for all types)
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

    /**
     * Update retrieval count on the source model if it has the column
     */
    private function updateSourceModelRetrievalCount(string $modelClass, array $ids): void
    {
        try {
            $model = new $modelClass;
            $table = $model->getTable();

            // Check if the table has a retrieval_count column
            if (Schema::hasColumn($table, 'retrieval_count')) {
                $updateData = ['retrieval_count' => DB::raw('retrieval_count + 1')];

                // Also update last_retrieved_at if column exists
                if (Schema::hasColumn($table, 'last_retrieved_at')) {
                    $updateData['last_retrieved_at'] = now();
                }

                $modelClass::query()
                    ->whereIn('id', $ids)
                    ->update($updateData);
            }
        } catch (\Exception $e) {
            // Silently fail if model doesn't support retrieval_count
            // The agent_sources table will still be updated
        }
    }
}
