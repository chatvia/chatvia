<?php

use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFitTextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\LocalTextSplitter;
use Domain\Llm\Services\TikToken;

/**
 * The local splitter needs no outside service; it packs paragraphs into
 * chunks of about `chatvia.ai.local_splitter.chunk_tokens` tokens. The hosted
 * splitter only adapts the chunk.fit client to the same contract.
 */
it('returns no chunks for empty or blank text', function (): void {
    expect((new LocalTextSplitter)->split(''))->toBe([])
        ->and((new LocalTextSplitter)->split("  \n\n "))->toBe([]);
});

it('keeps short text as one chunk', function (): void {
    expect((new LocalTextSplitter)->split('Refunds take five days.'))->toBe(['Refunds take five days.']);
});

it('splits long text into ordered chunks within the configured size', function (): void {
    config(['chatvia.ai.local_splitter.chunk_tokens' => 30, 'chatvia.ai.local_splitter.overlap_tokens' => 0]);

    $paragraphs = [];
    for ($i = 1; $i <= 8; $i++) {
        $paragraphs[] = "Paragraph {$i} explains one part of the refund policy in a few plain words.";
    }

    $chunks = (new LocalTextSplitter)->split(implode("\n\n", $paragraphs));

    expect(count($chunks))->toBeGreaterThan(1)
        ->and($chunks)->each->not->toBe('');

    foreach ($chunks as $chunk) {
        expect(TikToken::countTokens($chunk))->toBeLessThanOrEqual(30);
    }

    // Every paragraph survives, in reading order.
    expect(implode("\n\n", $chunks))->toBe(implode("\n\n", $paragraphs));
});

it('names itself "local"', function (): void {
    expect((new LocalTextSplitter)->name())->toBe('local');
});

it('delegates the chunker splitter to the chunker client', function (): void {
    $client = new class extends ChunkFitClient
    {
        public function split(string $text): array
        {
            return ['# A', 'B'];
        }
    };

    $splitter = new ChunkFitTextSplitter($client);

    expect($splitter->split('# A'."\n\n".'B'))->toBe(['# A', 'B'])
        ->and($splitter->name())->toBe('chunkfit');
});
