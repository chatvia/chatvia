<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;
use Illuminate\Support\Facades\Log;

class Chunker
{
    public function __construct(
        public LLMProviderInterface $provider,
    ) {}

    public function chunk(string $prompt): string
    {
        $res = $this->provider->completion([
            'model' => config('ai.models.chunker'),
            'temperature' => 0.0,
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
        ]);

        // Try to get content from standard field
        return $res->json('choices.0.message.content');
    }

}
