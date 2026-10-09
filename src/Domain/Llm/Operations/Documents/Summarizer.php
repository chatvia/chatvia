<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ChatResponseData;
use Domain\Llm\Exceptions\DocumentLlmUnavailableException;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Services\LlmChat;

/**
 * Writes a short summary of a whole document, read as a PDF from a signed URL
 * by the configured document-reading LLM (`chatvia.ai.models.summary`).
 */
class Summarizer
{
    public function __construct(
        private readonly DocumentLlm $documentLlm,
        private readonly LlmChat $chat,
    ) {}

    /**
     * The summary is the reply's content; its usage prices the call.
     *
     * @throws DocumentLlmUnavailableException
     */
    public function summarize(string $documentUrl): ChatResponseData
    {
        return $this->chat->using($this->documentLlm->provider())->send(new ChatRequestData(
            model: (string) config('chatvia.ai.models.summary'),
            messages: [
                ChatMessageData::userWithAttachment(prompt('summarization/system'), [
                    'type' => 'document_url',
                    'document_url' => $documentUrl,
                ]),
            ],
            maxTokens: 1024,
        ));
    }
}
