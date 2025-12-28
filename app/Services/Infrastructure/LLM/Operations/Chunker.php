<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;

class Chunker
{
    public function __construct(
        public LLMProviderInterface $provider,
    ) {}

    public function chunk(string $prompt, string $systemPrompt = ''): string
    {
        if (! $systemPrompt) {
            $systemPrompt = PromptLoader::load('chunking/system');
        }

        $res = $this->provider->completion([
            'model' => config('ai.models.chunker'),
            'temperature' => 0.0,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        // Try to get content from standard field
        return $res->json('choices.0.message.content');
    }
}
