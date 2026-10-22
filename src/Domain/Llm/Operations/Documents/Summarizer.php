<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ChatResponseData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Exceptions\DocumentLlmUnavailableException;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Services\LlmChat;
use Locale;

/**
 * Writes a short summary of a whole document, read as a PDF from a URL (a
 * `data:` URL holding the PDF itself, or a link the provider can open) by the
 * configured document-reading LLM (`chatvia.ai.models.summary`).
 */
class Summarizer
{
    public function __construct(
        private readonly DocumentLlm $documentLlm,
        private readonly LlmChat $chat,
    ) {}

    /**
     * The summary is the reply's content; its usage prices the call. The
     * document's language (an ISO 639-1 code), when known, is named in the
     * prompt: asked only to keep "the document's language", the model still
     * answers in English.
     *
     * @throws DocumentLlmUnavailableException
     */
    public function summarize(string $documentUrl, ?string $language = null): ChatResponseData
    {
        $prompt = prompt('summarization/system', [
            'language' => $language !== null ? Locale::getDisplayLanguage($language, 'en') : null,
        ]);

        return $this->chat->using($this->documentLlm->provider())->send(new ChatRequestData(
            model: $this->documentLlm->model((string) config('chatvia.ai.models.summary')),
            messages: [
                ChatMessageData::userWithAttachment($prompt, [
                    'type' => 'document_url',
                    'document_url' => $documentUrl,
                ]),
            ],
            tag: AiCallTag::DocumentSummary,
            maxTokens: 1024,
        ));
    }
}
