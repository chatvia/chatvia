<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Services\Infrastructure\LLM\Mistral;
use App\Services\Prompts\PromptLoader;

class Summarizer
{
    public function __construct(public Mistral $mistral) {}

    public function fromUrl(string $documentUrl): string
    {
        return $this->mistral->completion([
            'model' => 'mistral-large-latest',
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => PromptLoader::load('summarization/system'),
                        ],
                        [
                            'type' => 'document_url',
                            'document_url' => $documentUrl,
                        ],
                    ],
                ],
            ],
        ])->json('choices.0.message.content');
    }
}
