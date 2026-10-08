<?php

namespace Domain\Llm\Operations\Query;

use Domain\Llm\Contracts\LlmProviderInterface;
use Throwable;

class QueryRewriter
{
    public function __construct(public LlmProviderInterface $provider) {}

    /**
     * @return array{lang: string|null, query: string}
     */
    public function rewrite(string $userQuery, $previousQuery = null): array
    {
        if (config('chatvia.ai.query.rewriter.enabled') === false) {
            return [
                'lang' => null,
                'query' => $userQuery,
            ];
        }

        try {
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
        } catch (Throwable $e) {
            // Rewriting only improves retrieval; a failed call must not fail the chat turn.
            report($e);

            return ['lang' => null, 'query' => $userQuery];
        }

        return $this->parse(is_string($response) ? $response : '', $userQuery);
    }

    /**
     * The prompt asks for `<iso-639-1>|<rewritten query>`. Models do not always
     * follow it, so anything else falls back to the original query.
     *
     * @return array{lang: string|null, query: string}
     */
    private function parse(string $response, string $userQuery): array
    {
        $response = trim($response);

        if (! str_contains($response, '|')) {
            return ['lang' => null, 'query' => $response !== '' ? $response : $userQuery];
        }

        [$lang, $query] = array_map(trim(...), explode('|', $response, 2));

        return [
            'lang' => preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $lang) === 1 ? $lang : 'en',
            'query' => $query !== '' ? $query : $userQuery,
        ];
    }
}
