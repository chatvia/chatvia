<?php

namespace Domain\Knowledge\Ingestion\QueryBuilders;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Queries over knowledge chunks: source scoping, website chunks no page
 * links to any more, and the agent-scoped question/content samples used by
 * the Setup and Evals pages. The knowledge-base search itself lives in
 * {@see HybridChunkSearch}.
 *
 * @template TModel of Chunk
 *
 * @extends Builder<TModel>
 */
class ChunkQueryBuilder extends Builder
{
    /** How many question-bearing chunks are read when mining questions. */
    private const int QUESTION_CHUNKS_SCANNED = 200;

    /** Longest question kept: starters and eval case names are 255-char columns. */
    private const int MAX_QUESTION_LENGTH = 255;

    /** Characters kept per chunk in a content sample, so a sample fits one LLM call. */
    private const int SAMPLE_CHARS = 600;

    /**
     * Every chunk of one source, its summary chunk (index 0) included.
     *
     * @return self<TModel>
     */
    public function ofSource(Model $source): self
    {
        return $this
            ->where('chunks.chunkable_type', $source->getMorphClass())
            ->where('chunks.chunkable_id', $source->getKey());
    }

    /**
     * Page chunks that no `chunk_references` row points at any more. Website
     * chunks are shared by the pages that produced them, so a chunk is only
     * dead once its last page is gone. The summary chunk (index 0) never has
     * references and is kept.
     *
     * @return self<TModel>
     */
    public function unreferenced(): self
    {
        return $this
            ->whereNot('chunks.chunk_index', 0)
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->select(DB::raw(1))
                    ->from('chunk_references as r')
                    ->whereColumn('r.chunk_id', 'chunks.id');
            });
    }

    /**
     * Distinct candidate questions for an agent, drawn from the LLM-generated
     * `questions` on its ingested chunks (each chunk holds a newline-delimited
     * block). Used to suggest conversation starters in the agent Setup UI.
     * Returns an empty array when the agent has no question-bearing chunks.
     *
     * @return array<int, string>
     */
    public function questionsForAgent(Agent $agent, int $limit = 60): array
    {
        $blocks = $this->agentChunks($agent, ['questions'], self::QUESTION_CHUNKS_SCANNED, $this->questionBearing(...))
            ->pluck('questions');

        return $blocks
            ->flatMap(fn (string $block): array => Str::of($block)->split('/\r\n|\r|\n/')->all())
            ->map(fn (string $question): string => Str::trim($question))
            ->filter()
            // The starters endpoint caps each entry at 255 chars; never suggest
            // one that would fail validation when the user saves it.
            ->filter(fn (string $question): bool => Str::length($question) <= self::MAX_QUESTION_LENGTH)
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Like questionsForAgent, but keeps each question's owning chunk — the
     * Evals "From knowledge" picker needs the linkage to fetch the chunk text
     * as a reference answer at case-creation time. Splitting happens in PHP
     * because each chunk holds a newline-delimited block of questions.
     *
     * @return list<array{chunk_id: int, question: string, source_type: string}>
     */
    public function questionCandidatesForAgent(Agent $agent, int $limit = 200): array
    {
        $rows = $this->agentChunks($agent, ['id', 'questions', 'chunkable_type'], self::QUESTION_CHUNKS_SCANNED, $this->questionBearing(...))
            ->get();

        $seen = [];
        $candidates = [];

        foreach ($rows as $row) {
            foreach (Str::of((string) $row->questions)->split('/\r\n|\r|\n/') as $question) {
                $question = Str::trim($question);

                // Same 255-char cap as questionsForAgent — here because the
                // question doubles as the eval case name, a 255-char column.
                if ($question === '' || Str::length($question) > self::MAX_QUESTION_LENGTH || isset($seen[Str::lower($question)])) {
                    continue;
                }

                $seen[Str::lower($question)] = true;
                $candidates[] = ['chunk_id' => (int) $row->id, 'question' => $question, 'source_type' => (string) $row->chunkable_type];

                if (count($candidates) >= $limit) {
                    return $candidates;
                }
            }
        }

        return $candidates;
    }

    /**
     * Whether the agent has any ingested knowledge-base content (chunks). Gates
     * the Setup → Starters "Generate with AI" button.
     */
    public function hasContentForAgent(Agent $agent): bool
    {
        return $this->agentChunks($agent, ['id'], 1)->exists();
    }

    /**
     * A representative text sample of the agent's knowledge base — each chunk's
     * summary, falling back to its body — for grounding LLM starter generation.
     * Each entry is capped so the combined sample stays within one LLM call.
     *
     * @return array<int, string>
     */
    public function contentSampleForAgent(Agent $agent, int $limit = 30): array
    {
        return $this->agentChunks($agent, ['summary', 'chunk'], $limit)
            ->get()
            ->map(fn (object $row): string => Str::trim((string) ($row->summary ?: $row->chunk)))
            ->filter()
            ->map(fn (string $text): string => Str::substr($text, 0, self::SAMPLE_CHARS))
            ->values()
            ->all();
    }

    /**
     * Up to `$limit` chunks of the sources the agent uses, read source by
     * source: the agent's `agent_sources` rows come from their primary key,
     * then each source's chunks from an index that starts with
     * (chunkable_type, chunkable_id), such as `chunks_page_idx` (a LATERAL
     * join, at most `$limit` per source). Chunks belong to sources, not agents, so this is
     * how agent scoping works. Starting from the chunks instead lets the
     * planner walk every chunk of a big workspace to find the few of a small
     * agent. The workspace filter keeps the lookup on the workspace's own
     * chunks; the hybrid search inlines the same scoping in its SQL.
     *
     * @param  list<string>  $columns  chunk columns to return
     * @param  (Closure(QueryBuilder): mixed)|null  $filter  extra conditions on the chunks
     */
    private function agentChunks(Agent $agent, array $columns, int $limit, ?Closure $filter = null): QueryBuilder
    {
        $table = $this->getModel()->getTable();

        $chunks = DB::table($table)
            ->select(array_map(fn (string $column): string => "{$table}.{$column}", $columns))
            ->whereColumn("{$table}.chunkable_type", 's.sourceable_type')
            ->whereColumn("{$table}.chunkable_id", 's.sourceable_id')
            ->where("{$table}.workspace_id", $agent->workspace_id)
            ->limit($limit);

        if ($filter instanceof Closure) {
            $filter($chunks);
        }

        return DB::table('agent_sources as s')
            ->joinLateral($chunks, 'c')
            ->where('s.agent_id', $agent->id)
            ->select(array_map(fn (string $column): string => "c.{$column}", $columns))
            ->limit($limit);
    }

    /** Chunks that carry LLM-generated questions (a newline-delimited block). */
    private function questionBearing(QueryBuilder $chunks): void
    {
        $chunks->whereNotNull('questions')->where('questions', '!=', '');
    }
}
