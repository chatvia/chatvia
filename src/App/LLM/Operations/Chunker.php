<?php

namespace App\LLM\Operations;

use App\LLM\Contracts\LLMProviderInterface;
use App\LLM\Mistral;
use App\Prompts\PromptLoader;
use Domain\Ingestion\Data\Chunking\DocumentChunk;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Services\TikToken;
use Illuminate\Support\Facades\Log;

class Chunker
{
    public function __construct(
        public LLMProviderInterface $provider,
    ) {}

    public function chunkDocument(PageData $page, ?PageData $prevPage = null, ?PageData $nextPage = null): DocumentChunk
    {
        $systemPrompt = PromptLoader::load('chunking/document-chunking-system');

        $userPrompt = PromptLoader::load('chunking/document-chunking-user');

        $userPrompt = template($userPrompt, [
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
        $systemPrompt = PromptLoader::load('chunking/web');

        $userPrompt = PromptLoader::load('chunking/user-web');

        $userPrompt = template($userPrompt, [
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
        ])->throw();

        return $this->parseHtmlChunks($res->json('choices.0.message.content'));
    }

    public function parseHtmlChunks(string $llmResponse): array
    {
        // Handle empty response
        if (empty(trim($llmResponse)) || trim($llmResponse) === '""') {
            return [];
        }

        $chunks = [];

        // Match all <Chunk>...</Chunk> blocks (case-insensitive, handles multiline)
        $pattern = '/<Chunk>(.*?)<\/Chunk>/si';

        if (preg_match_all($pattern, $llmResponse, $matches)) {
            foreach ($matches[1] as $content) {
                $trimmed = trim($content);

                // Only add non-empty chunks
                if (! empty($trimmed) && TikToken::countTokens($trimmed) >= 128) {
                    $chunks[] = $trimmed;
                }
            }
        }

        return $chunks;
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
