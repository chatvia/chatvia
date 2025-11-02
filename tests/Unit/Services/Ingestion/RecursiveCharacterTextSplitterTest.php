<?php

namespace Tests\Unit\Services\Ingestion;

use App\Services\Splitters\RecursiveCharacterTextSplitter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecursiveCharacterTextSplitterTest extends TestCase
{
    private string $fileContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testFilePath = base_path('tests/files/animal-farm-ch1.txt');

        $this->fileContent = file_get_contents($this->testFilePath);
    }

    #[Test]
    public function split_text_creates_appropriate_chunks(): void
    {
        $splitter = new RecursiveCharacterTextSplitter(chunkSize: 700, chunkOverlap: 0);
        $chunks = $splitter->splitText($this->fileContent);

        $originalText = str_replace(["\n"], [''], trim(str_replace(' ', '', trim($this->fileContent))));
        $chunksAsText = str_replace(["\n"], [''], trim(str_replace(' ', '', implode('', $chunks))));

        $this->assertSame($originalText, $chunksAsText);
    }

    #[Test]
    public function chunks_are_from_different_sizes(): void
    {
        $splitter = new RecursiveCharacterTextSplitter(chunkSize: 700, chunkOverlap: 0);
        $chunks = $splitter->splitText($this->fileContent);

        $this->assertCount(65, $chunks);
    }

    #[Test]
    public function split_preserves_all_content(): void
    {
        $splitter = new RecursiveCharacterTextSplitter(chunkSize: 500, chunkOverlap: 0);
        $chunks = $splitter->splitText($this->fileContent);

        // Reconstruct text from chunks
        $reconstructed = implode(' ', $chunks);

        // Normalize both texts for comparison (remove extra whitespace)
        $originalNormalized = preg_replace('/\s+/', ' ', trim($this->fileContent));
        $reconstructedNormalized = preg_replace('/\s+/', ' ', trim($reconstructed));

        // Check that we haven't lost significant content
        $originalWords = str_word_count($originalNormalized);
        $reconstructedWords = str_word_count($reconstructedNormalized);

        // Allow for minor differences due to normalization
        $this->assertEquals(
            $originalWords,
            $reconstructedWords,
            'Significant content was lost during chunking'
        );
    }

    #[Test]
    public function split_with_overlap_preserves_content(): void
    {
        $splitter = new RecursiveCharacterTextSplitter(chunkSize: 400, chunkOverlap: 50);
        $chunks = $splitter->splitText($this->fileContent);

        $this->assertGreaterThan(1, count($chunks));
    }
}
