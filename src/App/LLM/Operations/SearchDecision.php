<?php

namespace App\LLM\Operations;

use App\LLM\Contracts\LLMProviderInterface;

class SearchDecision
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    /**
     * Determine if knowledge base search is needed for the given query
     * Handles both initial queries (no chunks) and follow-up queries (with chunks)
     */
    public function shouldSearch(array $chunks, string $userQuery): bool
    {
        $response = $this->provider->completion([
            'model' => config('ai.models.search_decision'),
            'temperature' => 0,
            'max_tokens' => 10,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => prompt('knowledge/follow-up/system'),
                ],
                [
                    'role' => 'user',
                    'content' => prompt('knowledge/follow-up/user', [
                        'retrieved_chunks' => empty($chunks) ? '' : collect($chunks)->implode("\n\n"),
                        'user_query' => $userQuery,
                    ]),
                ],
            ],
        ])->json('choices.0.message.content');

        $response = strtolower(trim($response ?? ''));

        return str_contains($response, 'search');
    }
}
