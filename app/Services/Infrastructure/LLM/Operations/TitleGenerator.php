<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;

class TitleGenerator
{
    public function __construct(
        public LLMProviderInterface $provider
    )
    {
    }


    public function generate(string $messages): string
    {
        return $this->provider->completion([
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
        ])->throw()->json('choices.0.message.content');
    }
}
