<?php

namespace Domain\Llm\Operations\Documents;

use Domain\Knowledge\Ingestion\Data\Chunking\DocumentChunk;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Services\TikToken;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Operations\Pricing\CostCalculator;
use Domain\Llm\Providers\Mistral;
use Illuminate\Support\Facades\Log;

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
            'model' => config('ai.models.chunker'),
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

        $content = $res->json('choices.0.message.content');

        Log::info($content);

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return DocumentChunk::from([
            'chunk' => $page->content,
            'summary' => $decoded['summary'],
            'questions' => $decoded['questions'],
            'cost' => $this->calculateCost(
                input: $systemPrompt.$userPrompt,
                output: $res,
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

    public function chunkHtml($html): array
    {
        $systemPrompt = prompt('chunking/web');

        $userPrompt = prompt('chunking/user-web', [
            'html' => $html,
        ]);

        $res = app(Mistral::class)->completion([
            'model' => 'mistral-large-latest',
            'temperature' => 0.0,
            'max_tokens' => 10000,
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
                    'name' => 'web_chunks',
                    'strict' => true,
                    'schema' => $this->webChunksSchema(),
                ],
            ],
        ])->throw();

        $decoded = json_decode(
            $res->json('choices.0.message.content') ?? '',
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        // Drop empty or sub-threshold chunks. 128 tokens matches the
        // minimum used by the legacy XML-tag parser — chunks smaller than
        // that don't carry enough context for useful retrieval.
        return collect($decoded['chunks'] ?? [])
            ->map(fn (string $chunk): string => trim($chunk))
            ->filter(fn (string $chunk): bool => $chunk !== '' && TikToken::countTokens($chunk) >= 128)
            ->values()
            ->all();
    }

    /**
     * Strict JSON schema for the HTML chunker — replaces the legacy
     * <Chunk>...</Chunk> tag protocol so missing closing tags, markdown
     * fences, or nested tags can't silently drop chunks.
     */
    private function webChunksSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'chunks' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Semantically coherent, self-contained chunks extracted from the input. Empty array when the input has no meaningful content.',
                ],
            ],
            'required' => ['chunks'],
            'additionalProperties' => false,
        ];
    }

    public function calculateCost($input, $output): float
    {
        return app(CostCalculator::class)
            ->calculateCostInCents(
                inputText: $input,
                outputText: $output,
                pricePerInputMilToken: config('ai.pricing.chunker_input_per_million_tokens'),
                pricePerOutputMilToken: config('ai.pricing.chunker_output_per_million_tokens'),
            );
    }
}
