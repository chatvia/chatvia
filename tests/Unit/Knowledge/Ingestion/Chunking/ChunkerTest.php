<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\Chunking\Chunker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chunker::enhancePage() asks the LLM for a page summary + questions. Its
 * cost must come from the provider's reported token usage, and the raw model
 * output (derived from customer content) must not be written to the logs.
 */
beforeEach(function (): void {
    config([
        'services.scalewayai.api_key' => 'test-key',
        'chatvia.ai.provider' => 'scaleway',
        'chatvia.ai.pricing.chunker_input_per_million_tokens' => 1.0,
        'chatvia.ai.pricing.chunker_output_per_million_tokens' => 2.0,
    ]);
});

function chunkerResponse(?array $usage): array
{
    $body = [
        'choices' => [[
            'message' => [
                'content' => json_encode([
                    'summary' => 'A page about oceans.',
                    'questions' => ['q1', 'q2', 'q3', 'q4', 'q5'],
                ]),
            ],
        ]],
    ];

    if ($usage !== null) {
        $body['usage'] = $usage;
    }

    return $body;
}

it('prices the call from the provider usage tokens', function (): void {
    Http::fake(['*' => Http::response(chunkerResponse([
        'prompt_tokens' => 10_000,
        'completion_tokens' => 1_000,
    ]))]);

    $chunk = app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Oceans cover most of Earth.', checksum: 'c'));

    // 10,000 × 1.0 / 1M × 100 = 1.0 cent; 1,000 × 2.0 / 1M × 100 = 0.2 cents.
    expect($chunk->cost)->toEqualWithDelta(1.2, 1e-9)
        ->and($chunk->summary)->toBe('A page about oceans.');
});

it('estimates the cost when the provider reports no usage', function (): void {
    Http::fake(['*' => Http::response(chunkerResponse(null))]);

    $chunk = app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Oceans cover most of Earth.', checksum: 'c'));

    expect($chunk->cost)->toBeGreaterThan(0.0);
});

it('does not log the model output', function (): void {
    Http::fake(['*' => Http::response(chunkerResponse(['prompt_tokens' => 1, 'completion_tokens' => 1]))]);
    Log::spy();

    app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Secret customer page.', checksum: 'c'));

    Log::shouldNotHaveReceived('info');
});
