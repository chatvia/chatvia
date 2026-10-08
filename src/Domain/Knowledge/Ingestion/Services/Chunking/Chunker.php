<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking;

use Domain\Knowledge\Ingestion\Data\Chunking\DocumentChunk;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Operations\Pricing\CostCalculator;
use Domain\Llm\Services\TikToken;

class Chunker
{
    public function __construct(
        public LlmProviderInterface $provider,
    ) {}

    public function chunkDocument(PageData $page, ?PageData $prevPage = null, ?PageData $nextPage = null): DocumentChunk
    {
        $systemPrompt = prompt('chunking/document-chunking-system');

        $userPrompt = prompt('chunking/document-chunking-user', [
            'previous_page' => $prevPage?->content,
            'target_page' => $page->content,
            'next_page' => $nextPage?->content,
        ]);

        $res = $this->provider->completion([
            'model' => config('chatvia.ai.models.chunker'),
            'temperature' => 0.0,
            'max_tokens' => 8096,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'document_chunk',
                    'strict' => true,
                    'schema' => $this->documentChunkSchema(),
                ],
            ],
        ])->throw();

        $content = (string) $res->json('choices.0.message.content');

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        // Bill from the provider's own token counts; estimate only when the
        // response carries no usage block.
        $usage = $res->json('usage') ?: [
            'prompt_tokens' => TikToken::countTokens($systemPrompt.$userPrompt),
            'completion_tokens' => TikToken::countTokens($content),
        ];

        return DocumentChunk::from([
            'chunk' => $page->content,
            'summary' => $decoded['summary'],
            'questions' => $decoded['questions'],
            'cost' => app(CostCalculator::class)->costInCentsFromUsage(
                usage: $usage,
                pricePerInputMilToken: (float) config('chatvia.ai.pricing.chunker_input_per_million_tokens'),
                pricePerOutputMilToken: (float) config('chatvia.ai.pricing.chunker_output_per_million_tokens'),
            ),
        ]);
    }

    /**
     * Strict JSON schema for the chunker response — the LLM MUST return
     * exactly this shape, no XML tags, no closing-tag truncation risk.
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
