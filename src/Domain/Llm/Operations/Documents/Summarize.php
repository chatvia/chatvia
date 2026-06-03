<?php

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Contracts\LlmProviderInterface;

class Summarize
{
    public function __construct(
        public LlmProviderInterface $provider
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
