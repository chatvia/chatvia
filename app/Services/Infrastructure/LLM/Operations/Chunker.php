<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;

class Chunker
{
    public function __construct(
        public LLMProviderInterface $provider,
    )
    {
    }

    public function chunk(string $prompt): string
    {
        return $this->provider->completion([
            'model' => config('ai.models.chunker'),
            'temperature' => 0.0,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => PromptLoader::load('chunking/system'),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ])->json('choices.0.message.content');
    }
}
