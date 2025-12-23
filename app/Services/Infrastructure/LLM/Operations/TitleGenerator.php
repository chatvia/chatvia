<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;

class TitleGenerator
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function generate(string $messages): string
    {

        $response = $this->provider->completion([
            'model' => config('ai.models.title'),
            'max_tokens' => 50,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    You are an AI writer. Your task is to find a meaningful title for the given text.
                    You must return your response in plain text format.
                    Make it concise
                    ',
                ],
                [
                    'role' => 'user',
                    'content' => 'The text is: '.$messages,
                ],
            ],
        ])->throw();

        // Handle both direct string response and nested JSON structure
        $result = $response->json('choices.0.message.content') ?? $response->json('content');

        // If still null, try to get the full response and extract content
        if ($result === null) {
            $json = $response->json();
            $result = $json['choices'][0]['message']['content'] ?? $json['content'] ?? '';
        }

        return is_array($result) ? ($result['content'] ?? '') : (string) $result;
    }
}
