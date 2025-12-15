<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;

class QueryRewriter
{
    public function __construct(public LLMProviderInterface $provider)
    {
    }

    public function rewrite(string $userQuery, $previousQuery = null): array
    {
        $response = $this->provider->completion([
            'model' => 'llama-3.1-8b-instruct',
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => PromptLoader::load('knowledge/rewrite-query'),
                ],
                [
                    'role' => 'user',
                    'content' => '
                        User Query is: <UserQuery>'.$userQuery.'</UserQuery>.
                        Previous Query is: <PreviousQuery>'.$previousQuery.'</PreviousQuery>.
                    ',
                ],
            ],
        ])->json('choices.0.message.content');

        [$lang, $query] = explode('|', $response);

        return [
            'lang' => $lang ?? 'en',
            'query' => $query ?? $userQuery,
        ];
    }
}
