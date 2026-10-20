<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking;

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkEnhancementData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\InvalidStructuredReplyException;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;

/**
 * Asks the AI enhancement model (LlmTask::Enhancement) for a summary of one page
 * and the questions it answers, with the neighbouring pages as context. The
 * reply must follow a strict JSON schema. The summary is later prepended to the
 * chunk before embedding ("contextual retrieval"); the questions get their own
 * embedding. Both are written in the page's own language: the keyword
 * search only matches words in the language a chatter asks in.
 */
class Chunker
{
    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly TaskModels $models,
    ) {}

    /**
     * @throws InvalidStructuredReplyException when the reply holds no JSON
     */
    public function enhancePage(PageData $page, ?PageData $previousPage = null, ?PageData $nextPage = null): ChunkEnhancementData
    {
        $systemPrompt = prompt('chunking/document-chunking-system');

        $userPrompt = prompt('chunking/document-chunking-user', [
            'previous_page' => $previousPage?->content,
            'target_page' => $page->content,
            'next_page' => $nextPage?->content,
        ]);

        $model = $this->models->model(LlmTask::Enhancement);

        $reply = $this->completion->send(new ChatRequestData(
            model: $model,
            messages: [
                ChatMessageData::system($systemPrompt),
                ChatMessageData::user($userPrompt),
            ],
            temperature: 0.0,
            maxTokens: 8096,
            responseSchema: new ResponseSchemaData('document_chunk', $this->documentChunkSchema()),
        ));

        $data = $reply->requireData();

        return new ChunkEnhancementData(
            summary: (string) ($data['summary'] ?? ''),
            questions: array_values(array_filter((array) ($data['questions'] ?? []), is_string(...))),
            model: $model->external_name,
        );
    }

    /**
     * Strict JSON schema for the chunker response — the LLM MUST return
     * exactly this shape, no XML tags, no closing-tag truncation risk.
     *
     * @return array<string, mixed>
     */
    private function documentChunkSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => [
                    'type' => 'string',
                    'description' => '2-5 sentences capturing the target page\'s key ideas in natural language, written in the target page\'s own language.',
                ],
                'questions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 5,
                    'maxItems' => 7,
                    'description' => '5-7 distinct questions the target page answers, written in the target page\'s own language. Each question is its own array item — do NOT number them.',
                ],
            ],
            'required' => ['summary', 'questions'],
            'additionalProperties' => false,
        ];
    }
}
