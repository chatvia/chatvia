<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;
use function Livewire\off;

class Summarize
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function summarize(string $content): string
    {
        office()->toPdf();

        $response = $this->provider->completion([
            'model' => 'qwen3-235b-a22b-instruct-2507',
            'temperature' => 0.0,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => PromptLoader::load('summarization/system'),
                ],
                [
                    'role' => 'user',
                    'content' => template(PromptLoader::load('summarization/user'), ['content' => $content]),
                ],
            ],
        ])->json();

        return $response['choices'][0]['message']['content'] ?? '';
    }
}
