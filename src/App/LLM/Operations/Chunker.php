<?php

namespace App\LLM\Operations;

use App\LLM\Contracts\LLMProviderInterface;
use Domain\Ingestion\Data\Chunking\DocumentChunk;
use Domain\Ingestion\Data\OCR\PageData;
use App\LLM\Mistral;
use App\Prompts\PromptLoader;
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
            'max_tokens' => 1024,
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

        $result = $res->json('choices.0.message.content');

        // Try to get content from standard field
        $data = $this->extractSummaryAndQuestions(
            $result,
            $page->content
        );

        Log::info($result);

        $data['cost'] = $this->calculateCost(
            input: $systemPrompt.$userPrompt,
            output: $res,
        );

        return DocumentChunk::from($data);
    }

    public function chunkHtml($html)
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

    public function calculateCost($input, $output)
    {
        return app(CostCalculator::class)
            ->calculateCostInCents(
                inputText: $input,
                outputText: $output,
                pricePerInputMilToken: config('ai.pricing.chunker_input_per_million_tokens'),
                pricePerOutputMilToken: config('ai.pricing.chunker_output_per_million_tokens'),
            );
    }

    /**
     * Extract <Chunk>, <Summary>, and <Questions> XML tags and return clean chunk
     */
    private function extractSummaryAndQuestions(string $llmResponse, $originalChunk): array
    {
        $chunk = null;
        $summary = '';
        $questions = [];

        // Extract <Chunk> tag
        if (preg_match('/<EnhancedChunk>(.*?)<\/EnhancedChunk>/s', $llmResponse, $chunkMatches)) {
            $chunk = trim($chunkMatches[1]);
        }

        // Extract <Summary> tag
        if (preg_match('/<Summary>(.*?)<\/Summary>/s', $llmResponse, $summaryMatches)) {
            $summary = trim($summaryMatches[1]);
        }

        // Extract <Questions> tag
        if (preg_match('/<Questions>(.*?)<\/Questions>/s', $llmResponse, $questionsMatches)) {
            $questionsText = trim($questionsMatches[1]);
            // Split questions by newlines and filter out empty lines
            $questions = array_values(array_filter(
                array_map('trim', explode("\n", $questionsText)),
                fn ($q) => ! empty($q)
            ));
        }

        return [
            'chunk' => $chunk ?? $originalChunk,
            'summary' => $summary,
            'questions' => $questions,
        ];
    }
}
