<?php

namespace Domain\Knowledge\Retrieval\Actions;

use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Knowledge\Retrieval\Data\SourceRefData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records that a set of sources was retrieved for an agent: bumps the all-time
 * counters on each source and on its `agent_sources` row, and emits the
 * per-day rollup event for the Sources analytics page.
 */
class UpdateRetrievalStatsAction
{
    /** Longest text-source label kept in the rollup. */
    private const int TEXT_LABEL_LENGTH = 80;

    /**
     * @param  Collection<int, SourceRefData>  $sources
     */
    public function execute(Collection $sources, int $agentId): void
    {
        $retrieved = [];

        foreach ($this->idsByType($sources) as $type => $ids) {
            // chunks.chunkable_type stores the morph alias (e.g. "source_file");
            // agent_sources.sourceable_type stores the same alias.
            $class = Relation::getMorphedModel($type);

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $this->bumpCounters($class, $type, $ids, $agentId);

            // Denormalise the display label + mime now, while the source still
            // exists, so the rollup keeps showing it after deletion.
            $labels = $this->labels($type, $class, $ids);

            foreach ($ids as $id) {
                $retrieved[] = ['type' => $type, 'id' => $id, ...($labels[$id] ?? ['name' => null, 'mime' => null])];
            }
        }

        if ($retrieved === []) {
            return;
        }

        // The workspace owner is denormalised onto the rollup so it survives
        // agent deletion and is queryable for account-wide stats. A turn that
        // ends just after its agent was deleted still counts for the workspace.
        $workspaceId = (int) Agent::query()->withTrashed()->whereKey($agentId)->value('workspace_id');

        SourceRetrieved::dispatch($agentId, $workspaceId, $retrieved, now());
    }

    /**
     * @param  Collection<int, SourceRefData>  $sources
     * @return Collection<string, Collection<int, int>> distinct source ids per morph alias
     */
    private function idsByType(Collection $sources): Collection
    {
        return $sources
            ->groupBy(fn (SourceRefData $source): string => $source->type)
            ->map(fn (Collection $group): Collection => $group
                ->map(fn (SourceRefData $source): int => $source->id)
                ->unique()
                ->values());
    }

    /**
     * Every source model has both columns, so no schema lookup is needed. The
     * source rows are updated through the base query: a retrieval is not an
     * edit, so it must not move the source's `updated_at` (the dashboard shows
     * it as the last change).
     *
     * @param  class-string<Model>  $class
     * @param  Collection<int, int>  $ids
     */
    private function bumpCounters(string $class, string $type, Collection $ids, int $agentId): void
    {
        $bump = [
            'retrieval_count' => DB::raw('retrieval_count + 1'),
            'last_retrieved_at' => now(),
        ];

        $class::query()->whereIn('id', $ids)->toBase()->update($bump);

        AgentSource::query()
            ->where('agent_id', $agentId)
            ->where('sourceable_type', $type)
            ->whereIn('sourceable_id', $ids)
            ->update($bump);
    }

    /**
     * The display name (and mime, for files) of each source, keyed by id.
     *
     * @param  class-string<Model>  $class
     * @param  Collection<int, int>  $ids
     * @return array<int, array{name: ?string, mime: ?string}>
     */
    private function labels(string $type, string $class, Collection $ids): array
    {
        $columns = match ($type) {
            'source_file' => ['id', 'filename', 'mime_type'],
            'source_website' => ['id', 'url'],
            'source_text' => ['id', 'excerpt'],
            'source_youtube_video' => ['id', 'title', 'url'],
            default => ['id'],
        };

        return $class::query()
            ->whereIn('id', $ids)
            ->get($columns)
            ->mapWithKeys(fn (Model $row): array => [(int) $row->getKey() => [
                'name' => $this->label($type, $row),
                'mime' => $type === 'source_file' ? $row->getAttribute('mime_type') : null,
            ]])
            ->all();
    }

    private function label(string $type, Model $row): ?string
    {
        return match ($type) {
            'source_file' => (string) $row->getAttribute('filename'),
            'source_website' => (string) $row->getAttribute('url'),
            'source_text' => Str::of((string) $row->getAttribute('excerpt'))->replaceMatches('/\s+/', ' ')->trim()->limit(self::TEXT_LABEL_LENGTH)->toString(),
            'source_youtube_video' => (string) ($row->getAttribute('title') ?: $row->getAttribute('url')),
            default => null,
        };
    }
}
