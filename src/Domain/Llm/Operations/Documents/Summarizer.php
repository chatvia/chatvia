<?php

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Providers\Mistral;
use Illuminate\Http\Client\Response;

class Summarizer
{
    public function __construct(public Mistral $mistral) {}

    public function fromUrl(string $documentUrl): string
    {
        return (string) $this->complete($documentUrl)->json('choices.0.message.content');
    }

    /**
     * The raw completion response, for callers that also need its `usage`.
     */
    public function complete(string $documentUrl): Response
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
        ]);
    }
}
