<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;

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
                    'content' => PromptLoader::load('knowledge/follow-up/system'),
                ],
                [
                    'role' => 'user',
                    'content' => PromptLoader::load('knowledge/follow-up/user', [
                        'retrieved_chunks' => empty($chunks) ? '' : collect($chunks)->implode("\n\n"),
                        'user_query' => $userQuery,
                    ]),
                ],
            ],
        ])->json('choices.0.message.content');

        $response = strtolower(trim($response ?? ''));
        ray($response);
        return str_contains($response, 'search');
    }
}
