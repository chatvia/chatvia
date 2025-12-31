<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Data\Chunking\DocumentChunk;
use App\Data\Ingestion\OCR\PageData;
use App\Services\Prompts\PromptLoader;
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
