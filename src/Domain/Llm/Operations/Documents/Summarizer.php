<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Documents;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ChatResponseData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Exceptions\NoSummaryModelException;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\SourceSummaryModel;
use Locale;

/**
 * Writes a short summary of a whole document, read as a PDF from a URL (a
 * `data:` URL holding the PDF itself, or a link the provider can open) by the
 * model for source summaries ({@see SourceSummaryModel}).
 */
class Summarizer
{
    public function __construct(
        private readonly SourceSummaryModel $models,
        private readonly LlmChat $chat,
    ) {}

    /**
     * The summary is the reply's content; its usage prices the call. The
     * document's language (an ISO 639-1 code), when known, is named in the
     * prompt: asked only to keep "the document's language", the model still
     * answers in English.
     *
     * @throws NoSummaryModelException
     */
    public function summarize(string $documentUrl, ?string $language = null): ChatResponseData
    {
        $prompt = prompt('summarization/system', [
            'language' => $language !== null ? Locale::getDisplayLanguage($language, 'en') : null,
        ]);

        return $this->chat->send(new ChatRequestData(
            model: $this->models->model(),
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
