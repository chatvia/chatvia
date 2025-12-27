<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;

class Tables
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function describe(array $tables): string
    {
        $tables = collect($tables)->implode("\n\n");

        $prompt = PromptLoader::load('tables/describe', [
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
