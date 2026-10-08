<?php

namespace Domain\Knowledge\Ingestion\Actions\Stats;

use Domain\Analytics\Events\SourceRetrieved;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records that a set of sources was retrieved for an agent: bumps the
 * all-time counters and emits the per-day rollup event.
 */
class UpdateRetrievalStatsAction
{
    /**
     * Each entry is a `{chunkable_type, chunkable_id}` pair. Jobs queued by an
     * older release pass full search-result rows (stdClass) instead; only the
     * two keys are read, so both shapes work.
     *
     * @param  array<int, array{chunkable_type: string, chunkable_id: int|string}|object>  $results
     */
    public function execute(array $results, int $agentId): void
    {
        if (empty($results)) {
            return;
        }

        $resultsByType = collect($results)
            ->map(fn (array|object $result): array => [
                'chunkable_type' => (string) data_get($result, 'chunkable_type'),
                'chunkable_id' => data_get($result, 'chunkable_id'),
            ])
            ->groupBy('chunkable_type');

        $retrieved = [];

        foreach ($resultsByType as $type => $typeResults) {
            $ids = collect($typeResults)->pluck('chunkable_id')->unique()->values();

            // chunks.chunkable_type stores the morph alias (e.g. "source_file");
            // resolve it to the model class. agent_sources.sourceable_type stores
            // the same alias, so the $type filter below stays as-is.
            $class = Relation::getMorphedModel($type) ?? $type;

            if ($ids->isEmpty() || ! class_exists($class)) {
                continue;
            }

            // All source models (SourceFile/SourceWebsite/SourceText/SourceYoutubeVideo)
            // expose both columns by convention — no information_schema lookup per job.
            $class::query()
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

            // Denormalise the display label + mime now, while the source still
            // exists, so the analytics rollup keeps showing it after deletion.
            $labels = $this->resolveLabels($type, $class, $ids);

            foreach ($ids as $id) {
                $retrieved[] = [
                    'type' => $type,
                    'id' => (int) $id,
                    'name' => $labels[(int) $id]['name'] ?? null,
                    'mime' => $labels[(int) $id]['mime'] ?? null,
                ];
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

    /**
     * Resolve the display name (and mime, for files) for each retrieved source
     * id of a morph type, so the rollup can store a label that survives source
     * deletion. Keyed by id.
     *
     * @param  class-string  $class
     * @param  Collection<int, int|string>  $ids
     * @return array<int, array{name: ?string, mime: ?string}>
     */
    private function resolveLabels(string $type, string $class, $ids): array
    {
        $columns = match ($type) {
            'source_file' => ['id', 'filename', 'mime_type'],
            'source_website', 'agent_website_url' => ['id', 'url'],
            'source_text' => ['id', 'excerpt'],
            'source_youtube_video' => ['id', 'title', 'url'],
            default => ['id'],
        };

        $out = [];

        foreach ($class::query()->whereIn('id', $ids)->get($columns) as $row) {
            $name = match ($type) {
                'source_file' => (string) $row->filename,
                'source_website', 'agent_website_url' => (string) $row->url,
                'source_text' => Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $row->excerpt)), 80),
                'source_youtube_video' => (string) ($row->title ?: $row->url),
                default => null,
            };

            $out[(int) $row->id] = [
                'name' => $name,
                'mime' => $type === 'source_file' ? $row->mime_type : null,
            ];
        }

        return $out;
    }
}
