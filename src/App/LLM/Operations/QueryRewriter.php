<?php

namespace App\LLM\Operations;

use App\LLM\Contracts\LLMProviderInterface;
use App\Prompts\PromptLoader;

class QueryRewriter
{
    public function __construct(public LLMProviderInterface $provider) {}

    public function rewrite(string $userQuery, $previousQuery = null): array
    {
        if (config('ai.query.rewriter.enabled') === false) {
            return [
                'lang' => null,
                'query' => $userQuery,
            ];
        }

        $response = $this->provider->completion([
            'model' => config('ai.query.rewriter.model'),
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
            'query' => $query,
        ];
    }
}
