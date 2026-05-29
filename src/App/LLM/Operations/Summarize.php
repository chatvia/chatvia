<?php

namespace App\LLM\Operations;

use App\LLM\Contracts\LLMProviderInterface;

class Summarize
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function summarize(string $content): string
    {
        $response = $this->provider->completion([
            'model' => 'qwen3-235b-a22b-instruct-2507',
            'temperature' => 0.0,
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => prompt('summarization/system'),
                ],
                [
                    'role' => 'user',
                    'content' => prompt('summarization/user', ['content' => $content]),
                ],
            ],
        ])->json('choices.0.message.content');

        return $response['choices'][0]['message']['content'] ?? '';
    }
}
