<?php

namespace App\Support;

use App\Models\AgentFile;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeBase
{
    private int $cacheTtl;

    private string $cachePrefix;

    private int $candidateLimit;

    public function __construct()
    {
        $this->cacheTtl = config('vector_search.cache.ttl', 3600);
        $this->cachePrefix = config('vector_search.cache.prefix', 'rag_search:');
        $this->candidateLimit = config('vector_search.search.candidate_limit', 500);
    }

    public function search(int|\App\Models\Agent $agent, string $query, int $limit = 3): array
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

        $embedding = embed($query);
        $vectorString = '['.implode(',', $embedding).']';

        // Set search path to include required schemas for BM25
        DB::statement('SET search_path = public, bm25_catalog, tokenizer_catalog');

        $sql = "
WITH vector_candidates AS (
    SELECT id, chunk, embedding, agent_file_id,
           binary_quantize(embedding)::bit(1536) <~> binary_quantize(?::vector(1536)) as binary_distance
    FROM documents
    WHERE agent_id = ?
    ORDER BY binary_quantize(embedding)::bit(1536) <~> binary_quantize(?::vector(1536))
    LIMIT ?
),
vector_ranked AS (
    SELECT id, chunk, agent_file_id,
           embedding <=> ?::vector(1536) as vector_distance,
           'vector' as search_type
    FROM vector_candidates
    ORDER BY embedding <=> ?::vector(1536)
    LIMIT ?
),
bm25_ranked AS (
    SELECT id,
        chunk, agent_file_id,
           embedding_bm25 <&> bm25_catalog.to_bm25query('documents_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2')) AS bm25_rank,
           'bm25' as search_type
    FROM documents
    WHERE agent_id = ?
    ORDER BY bm25_rank
    LIMIT ?
),
combined_results AS (
    SELECT id, chunk, agent_file_id, vector_distance as score, search_type FROM vector_ranked
    UNION ALL
    SELECT id, chunk, agent_file_id, bm25_rank as score, search_type FROM bm25_ranked
)
SELECT DISTINCT ON (id) id, chunk, agent_file_id, search_type
FROM combined_results
ORDER BY id, score
LIMIT ?
";

        $results = DB::select($sql, [
            $vectorString,
            $agent,
            $vectorString,
            $this->candidateLimit,
            $vectorString,
            $vectorString,
            $limit,
            $query,
            $agent,
            $limit,
            $limit + $limit,
        ]);

        if ($results) {
            Document::query()
                ->whereIn('id', collect($results)->pluck('id')->toArray())
                ->increment('retrieval_count');

            AgentFile::query()
                ->whereIn('id', collect($results)->pluck('agent_file_id')->unique()->toArray())
                ->increment('retrieval_count');
        }

        $rerankedDocumentsIds = app(Cohere::class)->rerank(
            $query,
            collect($results)->pluck('chunk')->toArray(),
        );

        return collect($results)
            ->filter(function ($result, $index) use ($rerankedDocumentsIds) {
                return in_array($index, $rerankedDocumentsIds, true);
            })
            ->sortBy(function ($result, $index) use ($rerankedDocumentsIds) {
                return array_search($index, $rerankedDocumentsIds, true);
            })
            ->map(function ($result) {
                $result->chunk = $this->removeXmlTags($result->chunk);

                return $result;
            })
            ->values()
            ->toArray();
    }

    private function removeXmlTags(string $text): string
    {
        // Remove XML tags and their content using regex
        // This pattern matches opening tag, content, and closing tag
        $pattern = '/<[^>]+>.*?<\/[^>]+>/s';
        $text = preg_replace($pattern, '', $text);

        // Also remove self-closing tags
        $text = preg_replace('/<[^>]+\/>/s', '', $text);

        // Clean up extra whitespace that might be left behind
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }

    public function chunks(int|\App\Models\Agent $agent, string $query, int $limit = 3): Collection
    {
        if ($agent instanceof \App\Models\Agent) {
            $agent = $agent->id;
        }

        $results = $this->search($agent, $query, $limit);

        return collect($results)->pluck('chunk');
    }

    public function searchInKnowledgeBaseIfNeeded(\App\Models\Agent $agent, string $userMessage)
    {
        if ($agent->isKnowledgeable()) {
            $chunks = $this->chunks($agent->id, $userMessage);

            return Str::of("### User Question:\n")
                ->append($userMessage."\n")
                ->append("### Retrieved Context:\n")
                ->append($chunks->implode("\n\n"));
        }

        return $userMessage;
    }

    public function shouldContinueSearching(array $chunks, $userQuery): bool
    {
        $res = app(OpenAI::class)->completion([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => config('prompts.follow-up-system-prompt'),
                ],
                [
                    'role' => 'user',
                    'content' => template(config('prompts.follow-up'), [
                        'retrieved_chunks' => collect($chunks)->implode("\n\n"),
                        'user_query' => $userQuery,
                    ]),
                ],
            ],
        ]);

        return str_contains($res, 'search');
    }

    public function rewriteQuery(string $userQuery, $previousQuery = null): string
    {
        return app(OpenAI::class)->completion([
            'model' => 'gpt-4o-mini',
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
You are a query rewriting assistant for a Retrieval-Augmented Generation (RAG) system. Your job is to rewrite the current user query to improve document retrieval by making it clear, explicit, and semantically aligned with the way information is stored in the knowledge base.

If there are acronyms or words you are not familiar with, do not try to rephrase them.

You may also receive the user’s previous query. If there is a correlation between the current and previous queries, incorporate the necessary context to make the rewritten query self-contained and unambiguous.

Do not invent or assume contexts or meanings that are not present in the input or conversation history. Do not generate hypothetical, generalized, or open-ended questions.

If the query is already clear and unambiguous, return it as-is.

Return only the rewritten query in plain text. Do not include any prefixes, suffixes, quotes, or formatting — just the clean, rewritten query.

',
                ],
                [
                    'role' => 'user',
                    'content' => '
                        User Query is: <UserQuery>'.$userQuery.'</UserQuery>.
                        Previous Query is: <PreviousQuery>'.$previousQuery.'</PreviousQuery>.
                    ',
                ],
            ],
        ]);
    }
}
