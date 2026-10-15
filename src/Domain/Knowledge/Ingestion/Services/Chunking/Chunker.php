<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking;

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkEnhancementData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ChatUsageData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\InvalidStructuredReplyException;
use Domain\Llm\Operations\Pricing\CostCalculator;
use Domain\Llm\Services\StructuredCompletion;
use Domain\Llm\Services\TaskModels;
use Domain\Llm\Services\TikToken;

/**
 * Asks the AI enhancement model (LlmTask::Enhancement) for a summary of one page
 * and the questions it answers, with the neighbouring pages as context. The
 * reply must follow a strict JSON schema. The summary is later prepended to the
 * chunk before embedding ("contextual retrieval"); the questions get their own
 * embedding.
 */
class Chunker
{
    public function __construct(
        private readonly StructuredCompletion $completion,
        private readonly CostCalculator $costCalculator,
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

        // Bill from the provider's own token counts; estimate only when the
        // response carries no usage block.
        $usage = $reply->response->usage ?? ChatUsageData::fromUsage([
            'prompt_tokens' => TikToken::countTokens($systemPrompt.$userPrompt),
            'completion_tokens' => TikToken::countTokens($reply->response->content),
        ]);

        return new ChunkEnhancementData(
            summary: (string) ($data['summary'] ?? ''),
            questions: array_values(array_filter((array) ($data['questions'] ?? []), is_string(...))),
            cost: $this->costCalculator->costInCentsFromUsage(
                usage: $usage,
                // The model's catalog prices when the admin set them.
                pricePerInputMilToken: (float) ($model->input_price_per_1m_tokens ?? config('chatvia.ai.pricing.chunker_input_per_million_tokens')),
                pricePerOutputMilToken: (float) ($model->output_price_per_1m_tokens ?? config('chatvia.ai.pricing.chunker_output_per_million_tokens')),
            ),
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
                    'description' => '2-5 sentences capturing the target page\'s key ideas in natural language.',
                ],
                'questions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 5,
                    'maxItems' => 7,
                    'description' => '5-7 distinct questions the target page answers. Each question is its own array item — do NOT number them.',
                ],
            ],
            'required' => ['summary', 'questions'],
            'additionalProperties' => false,
        ];
    }
}
