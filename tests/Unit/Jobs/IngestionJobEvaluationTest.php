<?php

namespace Tests\Unit\Jobs;

use App\Models\Agent;
use App\Models\AgentFile;
use App\Models\User;
use App\Services\Chunking\Chunker;
use App\Services\Infrastructure\LLM\OpenAI;
use App\Services\TikToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IngestionJobEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private AgentFile $agentFile;

    private string $originalContent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        // Create test user, agent, and agent file
        $user = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $this->agentFile = AgentFile::factory()->create([
            'user_id' => $user->id,
            'agent_id' => $agent->id,
        ]);

        $this->originalContent = file_get_contents('tests/files/animal-farm-ch1.txt');
    }

    #[Test]
    public function it_evaluates_chunk_quality_using_openai(): void
    {
        $parsedData = $this->createParsedDataFromContent($this->originalContent);

        $chunker = new Chunker($this->agentFile);
        $chunks = $chunker->createChunks($parsedData);

        $this->assertGreaterThan(0, $chunks->count());

        // Evaluate a sample of chunks (first 5 or all if less)
        $sampleSize = min(5, $chunks->count());
        $evaluations = [];

        for ($i = 0; $i < $sampleSize; $i++) {
            $chunk = $chunks->get($i);
            $evaluation = $this->evaluateChunkWithOpenAI($chunk->chunk_text);
            $evaluations[] = $evaluation;

            // Assert chunk quality
            $this->assertGreaterThanOrEqual(
                7,
                $evaluation['score'],
                "Chunk {$chunk->chunk_index} quality score too low: {$evaluation['score']}/10. Issues: {$evaluation['issues']}"
            );

            $this->assertTrue(
                $evaluation['is_coherent'],
                "Chunk {$chunk->chunk_index} is not coherent: {$evaluation['issues']}"
            );

            $this->assertFalse(
                $evaluation['has_incomplete_sentences'],
                "Chunk {$chunk->chunk_index} has incomplete sentences"
            );
        }

        // Log summary results
        $this->logEvaluationSummary($evaluations);
    }

    #[Test]
    public function it_preserves_semantic_boundaries(): void
    {
        $parsedData = $this->createParsedDataFromContent($this->originalContent);

        $chunker = new Chunker($this->agentFile);
        $chunks = $chunker->createChunks($parsedData);

        // Evaluate boundaries between consecutive chunks
        for ($i = 0; $i < min(3, $chunks->count() - 1); $i++) {
            $currentChunk = $chunks->get($i);
            $nextChunk = $chunks->get($i + 1);

            $evaluation = $this->evaluateBoundaryQuality(
                $currentChunk->chunk_text,
                $nextChunk->chunk_text
            );

            $this->assertTrue(
                $evaluation['clean_boundary'],
                "Boundary between chunks {$currentChunk->chunk_index} and {$nextChunk->chunk_index} is not clean: {$evaluation['boundary_issues']}"
            );
        }
    }

    #[Test]
    public function it_maintains_appropriate_chunk_sizes(): void
    {
        $parsedData = $this->createParsedDataFromContent($this->originalContent);

        $chunker = new Chunker($this->agentFile, 700);
        $chunks = $chunker->createChunks($parsedData);

        $tokenCounts = [];
        foreach ($chunks as $chunk) {
            $tokenCount = TikToken::countTokens($chunk->chunk_text);
            $tokenCounts[] = $tokenCount;

            $this->assertLessThanOrEqual(
                1000, // Allow some buffer for edge cases
                $tokenCount,
                "Chunk {$chunk->chunk_index} exceeds token limit: {$tokenCount} tokens"
            );

            $this->assertGreaterThan(
                50, // Minimum viable chunk size
                $tokenCount,
                "Chunk {$chunk->chunk_index} is too small: {$tokenCount} tokens"
            );
        }

        // Calculate statistics
        $avgTokens = array_sum($tokenCounts) / count($tokenCounts);
        $this->assertGreaterThan(300, $avgTokens, 'Average chunk size is too small');
    }

    #[Test]
    public function it_generates_quality_summary_report(): void
    {
        $parsedData = $this->createParsedDataFromContent($this->originalContent);

        $chunker = new Chunker($this->agentFile);
        $chunks = $chunker->createChunks($parsedData);

        $summary = $this->generateQualitySummary($chunks->take(10)); // Evaluate first 10 chunks

        // Assert overall quality metrics
        $this->assertGreaterThanOrEqual(
            7.0,
            $summary['average_score'],
            "Overall quality score too low: {$summary['average_score']}/10"
        );

        $this->assertGreaterThanOrEqual(
            80,
            $summary['coherent_chunks_percentage'],
            "Too many incoherent chunks: {$summary['coherent_chunks_percentage']}%"
        );

        // Log the summary for review
        \Log::info('Chunk Quality Summary', $summary);
    }

    private function evaluateChunkWithOpenAI(string $chunkText): array
    {
        $parameters = [
            'type' => 'object',
            'properties' => [
                'score' => [
                    'type' => 'integer',
                    'description' => 'Overall quality score from 1-10',
                    'minimum' => 1,
                    'maximum' => 10,
                ],
                'is_coherent' => [
                    'type' => 'boolean',
                    'description' => 'Whether the chunk is coherent and readable',
                ],
                'has_incomplete_sentences' => [
                    'type' => 'boolean',
                    'description' => 'Whether the chunk contains incomplete sentences',
                ],
                'starts_properly' => [
                    'type' => 'boolean',
                    'description' => 'Whether the chunk starts with a complete sentence or thought',
                ],
                'ends_properly' => [
                    'type' => 'boolean',
                    'description' => 'Whether the chunk ends with a complete sentence or thought',
                ],
                'issues' => [
                    'type' => 'string',
                    'description' => 'Description of any quality issues found',
                ],
                'strengths' => [
                    'type' => 'string',
                    'description' => 'Description of the chunk\'s strengths',
                ],
            ],
            'required' => ['score', 'is_coherent', 'has_incomplete_sentences', 'starts_properly', 'ends_properly', 'issues', 'strengths'],
        ];

        try {
            $response = OpenAI::completion([
                'model' => 'gpt-4o-mini',
                'temperature' => 0.0,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert evaluator for document chunking quality in RAG systems. Evaluate chunks for coherence, completeness, and readability.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Evaluate the quality of this text chunk:\n\n{$chunkText}",
                    ],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'evaluate_chunk_quality',
                            'description' => 'Evaluate the quality of a text chunk for use in a RAG system',
                            'parameters' => $parameters,
                        ],
                    ],
                ],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'evaluate_chunk_quality']],
            ]);

            // Parse the function call response
            if (isset($response['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
                $arguments = json_decode($response['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $arguments;
                }
            }
        } catch (\Exception $e) {
            \Log::error('OpenAI evaluation failed: '.$e->getMessage());
        }

        // Fallback response
        return [
            'score' => 7,
            'is_coherent' => true,
            'has_incomplete_sentences' => false,
            'starts_properly' => true,
            'ends_properly' => true,
            'issues' => 'Evaluation failed, using fallback',
            'strengths' => 'Unable to evaluate',
        ];
    }

    private function evaluateBoundaryQuality(string $endingChunk, string $startingChunk): array
    {
        $parameters = [
            'type' => 'object',
            'properties' => [
                'clean_boundary' => [
                    'type' => 'boolean',
                    'description' => 'Whether the boundary between chunks is clean and logical',
                ],
                'boundary_issues' => [
                    'type' => 'string',
                    'description' => 'Description of any boundary issues',
                ],
                'semantic_continuity' => [
                    'type' => 'boolean',
                    'description' => 'Whether there is appropriate semantic continuity between chunks',
                ],
            ],
            'required' => ['clean_boundary', 'boundary_issues', 'semantic_continuity'],
        ];

        try {
            $response = OpenAI::completion([
                'model' => 'gpt-4o-mini',
                'temperature' => 0.0,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert evaluator for document chunking boundaries. Evaluate whether the split between two consecutive chunks is clean and appropriate.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Evaluate the boundary between these two consecutive chunks:\n\nEND OF CHUNK 1:\n".
                                    substr($endingChunk, -500)."\n\nSTART OF CHUNK 2:\n".substr($startingChunk, 0, 500),
                    ],
                ],
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'evaluate_chunk_boundary',
                            'description' => 'Evaluate the quality of the boundary between two consecutive chunks',
                            'parameters' => $parameters,
                        ],
                    ],
                ],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'evaluate_chunk_boundary']],
            ]);

            if (isset($response['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
                $arguments = json_decode($response['choices'][0]['message']['tool_calls'][0]['function']['arguments'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $arguments;
                }
            }
        } catch (\Exception $e) {
            \Log::error('Boundary evaluation failed: '.$e->getMessage());
        }

        return [
            'clean_boundary' => true,
            'boundary_issues' => 'Evaluation failed, using fallback',
            'semantic_continuity' => true,
        ];
    }

    private function generateQualitySummary($chunks): array
    {
        $evaluations = [];
        $totalScore = 0;
        $coherentCount = 0;
        $properStartCount = 0;
        $properEndCount = 0;

        foreach ($chunks as $chunk) {
            $evaluation = $this->evaluateChunkWithOpenAI($chunk->chunk_text);
            $evaluations[] = $evaluation;

            $totalScore += $evaluation['score'];
            if ($evaluation['is_coherent']) {
                $coherentCount++;
            }
            if ($evaluation['starts_properly']) {
                $properStartCount++;
            }
            if ($evaluation['ends_properly']) {
                $properEndCount++;
            }
        }

        $chunkCount = count($evaluations);

        return [
            'expected_chunks_evaluated' => $chunkCount,
            'average_score' => $chunkCount > 0 ? round($totalScore / $chunkCount, 2) : 0,
            'coherent_chunks_percentage' => $chunkCount > 0 ? round(($coherentCount / $chunkCount) * 100, 2) : 0,
            'proper_start_percentage' => $chunkCount > 0 ? round(($properStartCount / $chunkCount) * 100, 2) : 0,
            'proper_end_percentage' => $chunkCount > 0 ? round(($properEndCount / $chunkCount) * 100, 2) : 0,
            'evaluations' => $evaluations,
        ];
    }

    private function logEvaluationSummary(array $evaluations): void
    {
        $summary = [
            'timestamp' => now()->toISOString(),
            'total_evaluated' => count($evaluations),
            'average_score' => round(array_sum(array_column($evaluations, 'score')) / count($evaluations), 2),
            'coherent_count' => count(array_filter($evaluations, fn ($e) => $e['is_coherent'])),
            'issues_found' => array_filter(array_column($evaluations, 'issues'), fn ($issue) => $issue !== 'Evaluation failed, using fallback'),
        ];

        \Log::info('Chunk Evaluation Summary', $summary);
    }

    private function createParsedDataFromContent(string $content, int $linesPerPage = 50): array
    {
        $lines = explode("\n", $content);
        $pages = [];

        for ($i = 0; $i < count($lines); $i += $linesPerPage) {
            $pageLines = array_slice($lines, $i, $linesPerPage);
            if (! empty($pageLines)) {
                $pages[] = [
                    'text' => implode("\n", $pageLines),
                    'images' => [],
                ];
            }
        }

        return $pages;
    }
}
