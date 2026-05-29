<?php

namespace App\LLM\Operations;

use App\LLM\Mistral;

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
                            'text' => prompt('summarization/system'),
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
