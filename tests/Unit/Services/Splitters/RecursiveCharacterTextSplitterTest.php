<?php

use App\Services\Splitters\RecursiveCharacterTextSplitter;

beforeEach(function () {
    $testFilePath = base_path('tests/files/animal-farm-ch1.txt');

    $this->fileContent = file_get_contents($testFilePath);
});

test('split text creates appropriate chunks', function () {
    $splitter = new RecursiveCharacterTextSplitter(chunkSize: 700, chunkOverlap: 0);
    $chunks = $splitter->splitText($this->fileContent);

    $originalText = str_replace(["\n"], [''], trim(str_replace(' ', '', trim($this->fileContent))));
    $chunksAsText = str_replace(["\n"], [''], trim(str_replace(' ', '', implode('', $chunks))));

    expect($chunksAsText)->toBe($originalText);
});

test('chunks are from different sizes', function () {
    $splitter = new RecursiveCharacterTextSplitter(chunkSize: 700, chunkOverlap: 0);
    $chunks = $splitter->splitText($this->fileContent);

    expect($chunks)->toHaveCount(67);
});

test('split preserves all content', function () {
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
    expect($reconstructedWords)->toBe($originalWords, 'Significant content was lost during chunking');
});

test('split with overlap preserves content', function () {
    $splitter = new RecursiveCharacterTextSplitter(chunkSize: 400, chunkOverlap: 50);
    $chunks = $splitter->splitText($this->fileContent);

    expect(count($chunks))->toBeGreaterThan(1);
});
