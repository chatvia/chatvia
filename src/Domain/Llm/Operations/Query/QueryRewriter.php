<?php

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Contracts\LlmProviderInterface;

class QueryRewriter
{
    public function __construct(public LlmProviderInterface $provider) {}

    public function rewrite(string $userQuery, $previousQuery = null): array
    {
        if (config('chatvia.ai.query.rewriter.enabled') === false) {
            return [
                'lang' => null,
                'query' => $userQuery,
            ];
        }

        $response = $this->provider->completion([
            'model' => config('chatvia.ai.query.rewriter.model'),
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => prompt('knowledge/rewrite-query'),
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
            'lang' => $lang !== '' ? $lang : 'en',
            'query' => $query,
        ];
    }
}
