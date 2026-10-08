<?php

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Exceptions\DocumentLlmUnavailableException;
use Domain\Llm\Services\DocumentLlm;
use Illuminate\Http\Client\Response;

/**
 * Writes a short summary of a whole document, read as a PDF from a signed URL
 * by the configured document-reading LLM (`chatvia.ai.models.summary`).
 */
class Summarizer
{
    public function __construct(
        private readonly DocumentLlm $documentLlm,
    ) {}

    public function fromUrl(string $documentUrl): string
    {
        return (string) $this->complete($documentUrl)->json('choices.0.message.content');
    }

    /**
     * The raw completion response, for callers that also need its `usage`.
     *
     * @throws DocumentLlmUnavailableException
     */
    public function complete(string $documentUrl): Response
    {
        return $this->documentLlm->provider()->completion([
            'model' => (string) config('chatvia.ai.models.summary'),
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
