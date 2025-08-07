<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Models\AgentFile;
use App\Models\User;
use App\Services\Chunker;
use App\Support\TikToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChunkerTest extends TestCase
{
    use RefreshDatabase;

    private AgentFile $agentFile;
    private Chunker $chunker;
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

        $this->chunker = new Chunker($this->agentFile, 700);
        $this->originalContent = file_get_contents('tests/files/animal-farm-ch1.txt');
    }

    #[Test]
    public function it_creates_appropriate_chunks_from_original_content(): void
    {
        // Create parsed data from the original content
        $parsedData = $this->createParsedDataFromContent($this->originalContent);
        $chunks = $this->chunker->createChunks($parsedData);

        // Should create multiple chunks from the large content
        $this->assertGreaterThan(10, $chunks->count());

        // Each chunk should be within token limits
        foreach ($chunks as $chunk) {
            $tokenCount = TikToken::countTokens($chunk->chunk_text);

            $this->assertLessThanOrEqual(1000, $tokenCount);

            // Chunks should not be empty
            $this->assertGreaterThan(0, $tokenCount);
        }
    }

    #[Test]
    public function it_preserves_all_content_when_chunking(): void
    {
        $parsedData = $this->createParsedDataFromContent($this->originalContent);
        $chunks = $this->chunker->createChunks($parsedData);

        // Method 1: Exact content matching (inspired by RecursiveCharacterTextSplitterTest)
        $originalText = str_replace(["\n"], [""], trim(str_replace(" ", "", trim($this->originalContent))));
        $chunksAsText = str_replace(["\n"], [""], trim(str_replace(" ", "", implode("", $chunks->pluck('chunk_text')->toArray()))));

        $this->assertSame($originalText, $chunksAsText, "Chunks should contain exact same content as original");

        // Method 2: Word count verification
        $reconstructed = implode(' ', $chunks->pluck('chunk_text')->toArray());
        $originalWords = str_word_count(preg_replace('/\s+/', ' ', trim($this->originalContent)));
        $reconstructedWords = str_word_count(preg_replace('/\s+/', ' ', trim($reconstructed)));

        $this->assertEquals(
            $originalWords,
            $reconstructedWords,
            "Word count should be preserved exactly"
        );
    }

    #[Test]
    public function it_sets_chunk_relationships_correctly(): void
    {
        $parsedData = $this->createParsedDataFromContent($this->originalContent);
        $chunks = $this->chunker->createChunks($parsedData);

        $totalChunks = $chunks->count();

        foreach ($chunks as $index => $chunk) {
            // Verify metadata
            $this->assertEquals($totalChunks, $chunk->total_chunks);
            $this->assertEquals($index + 1, $chunk->chunk_index);

            // Verify prev/next relationships
            if ($index > 0) {
                $this->assertEquals($chunks->get($index - 1)->chunk_text, $chunk->prev_chunk);
            } else {
                $this->assertEmpty($chunk->prev_chunk);
            }

            if ($index < $totalChunks - 1) {
                $this->assertEquals($chunks->get($index + 1)->chunk_text, $chunk->next_chunk);
            } else {
                $this->assertEmpty($chunk->next_chunk);
            }
        }
    }

    #[Test]
    public function it_combines_small_pages_efficiently(): void
    {
        // Split content into many small pages
        $lines = explode("\n", $this->originalContent);
        $parsedData = [];

        // Create very small pages (5 lines each)
        for ($i = 0; $i < count($lines); $i += 5) {
            $pageLines = array_slice($lines, $i, 5);
            if (!empty($pageLines)) {
                $parsedData[] = [
                    'text' => implode("\n", $pageLines),
                    'images' => []
                ];
            }
        }

        $chunks = $this->chunker->createChunks($parsedData);

        // Should combine small pages into larger chunks
        // With 5 lines per page and ~730 lines total, we get ~146 pages
        // These should be combined into far fewer chunks
        $this->assertLessThan(
            count($parsedData),
            $chunks->count(),
            "Chunker should produce fewer chunks than input pages"
        );

        // More specific: should reduce pages significantly
        $this->assertLessThan(
            100,
            $chunks->count(),
            "Chunker should combine small pages efficiently"
        );
    }

    #[Test]
    public function it_handles_large_pages_correctly(): void
    {
        // Create a single large page with all content
        $parsedData = [
            ['text' => $this->originalContent, 'images' => []]
        ];

        $chunks = $this->chunker->createChunks($parsedData);

        // Should split the large page into multiple chunks
        $this->assertGreaterThan(10, $chunks->count());

        // Each chunk should respect token limits
        foreach ($chunks as $chunk) {
            $tokenCount = TikToken::countTokens($chunk->chunk_text);
            $this->assertLessThanOrEqual(1050, $tokenCount);
        }
    }

    #[Test]
    public function it_handles_images_correctly(): void
    {
        // Create pages with mock images
        $parsedData = [
            [
                'text' => substr($this->originalContent, 0, 1000),
                'images' => [
                    ['path' => 'tests/files/animal-farm-ch1.txt', 'name' => 'image1.jpg'],
                    ['path' => 'tests/files/animal-farm-ch1.txt', 'name' => 'image2.jpg']
                ]
            ],
            [
                'text' => substr($this->originalContent, 1000, 1000),
                'images' => [
                    ['path' => 'tests/files/animal-farm-ch1.txt', 'name' => 'image3.jpg']
                ]
            ]
        ];

        $chunks = $this->chunker->createChunks($parsedData);

        // Images should be preserved with their chunks
        $allImages = $chunks->flatMap(fn($chunk) => $chunk->chunk_images)->unique();
        $this->assertCount(3, $allImages, "All images should be preserved");
    }

    #[Test]
    public function it_handles_empty_and_whitespace_pages(): void
    {
        $parsedData = [
            ['text' => 'First content', 'images' => []],
            ['text' => '', 'images' => []],
            ['text' => '   ', 'images' => []],
            ['text' => "\n\n\n", 'images' => []],
            ['text' => 'Last content', 'images' => []],
        ];

        $chunks = $this->chunker->createChunks($parsedData);

        // Should combine non-empty pages into a single chunk
        $this->assertCount(1, $chunks);

        $chunkText = $chunks->first()->chunk_text;
        $this->assertStringContainsString('First content', $chunkText);
        $this->assertStringContainsString('Last content', $chunkText);
    }

    #[Test]
    public function it_maintains_semantic_boundaries(): void
    {
        // Use a portion of the original content that has clear paragraph breaks
        $sampleText = substr($this->originalContent, 0, 5000);
        $paragraphs = explode("\n\n", $sampleText);

        // Create pages from paragraphs
        $parsedData = array_map(fn($para) => ['text' => $para, 'images' => []], $paragraphs);
        $chunks = $this->chunker->createChunks($parsedData);

        // Each chunk should contain complete paragraphs (no partial sentences at boundaries)
        foreach ($chunks as $chunk) {
            $text = trim($chunk->chunk_text);

            // Should not start with lowercase (unless it's a special case)
            if (!empty($text) && !in_array($text[0], ['(', '[', '"', "'"])) {
                $this->assertMatchesRegularExpression(
                    '/^[A-Z]/',
                    $text,
                    "Chunk should start with a capital letter or punctuation"
                );
            }

            // Should end with proper punctuation (or be a complete word)
            $lastChar = substr($text, -1);

            // Check if it ends with punctuation or is at least a letter/number
            $endsProperlyWithPunctuation = in_array($lastChar, ['.', '!', '?', '"', "'", ')', ']']);
            $endsWithAlphanumeric = ctype_alnum($lastChar);

            $this->assertTrue(
                $endsProperlyWithPunctuation || $endsWithAlphanumeric,
                "Chunk should end with proper punctuation or complete word, but ends with: '$lastChar'"
            );
        }
    }

    #[Test]
    public function it_produces_consistent_results(): void
    {
        $parsedData = $this->createParsedDataFromContent($this->originalContent);

        // Run chunking multiple times
        $results = [];
        for ($i = 0; $i < 3; $i++) {
            $chunks = $this->chunker->createChunks($parsedData);
            $results[] = $chunks->pluck('chunk_text')->toArray();
        }

        // Results should be identical
        $this->assertEquals($results[0], $results[1]);
        $this->assertEquals($results[1], $results[2]);
    }

    /**
     * Helper method to create parsed data from content
     */
    private function createParsedDataFromContent(string $content, int $linesPerPage = 50): array
    {
        $lines = explode("\n", $content);
        $pages = [];

        for ($i = 0; $i < count($lines); $i += $linesPerPage) {
            $pageLines = array_slice($lines, $i, $linesPerPage);
            if (!empty($pageLines)) {
                $pages[] = [
                    'text' => implode("\n", $pageLines),
                    'images' => []
                ];
            }
        }

        return $pages;
    }
}
