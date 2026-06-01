<?php

namespace App\LLM\Operations\Documents;

use App\LLM\Contracts\LLMProviderInterface;

class Tables
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function describe(array $tables): string
    {
        $tables = collect($tables)->implode("\n\n");

        $prompt = prompt('tables/describe', [
            'tables' => $tables,
        ]);

        $response = $this->provider->completion([
            'model' => config('ai.models.tables'),
            'temperature' => 0.0,
            'max_tokens' => 512,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ])->json();

        return $response['choices'][0]['message']['content'];
    }
}
