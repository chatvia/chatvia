<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\Chunking\Chunker;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Chunker::enhancePage() asks the Smart model for a page summary + questions.
 * Its cost must come from the provider's reported token usage, priced with
 * the model's catalog prices (or the configured ones when it has none), and
 * the raw model output (derived from customer content) must not be written to
 * the logs. The summary and questions must stay in the page's own language.
 */
beforeEach(function (): void {
    config([
        'chatvia.ai.pricing.chunker_input_per_million_tokens' => 1.0,
        'chatvia.ai.pricing.chunker_output_per_million_tokens' => 2.0,
        'chatvia.ai.tasks.enhancement' => testLlm('smart-model')->external_name,
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

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'smart-model');
});

it('prices the call with the model\'s catalog prices when it has them', function (): void {
    config(['chatvia.ai.tasks.enhancement' => testLlm('priced-model', attributes: [
        'input_price_per_1m_tokens_in_micro_cents' => 300_000_000,
        'output_price_per_1m_tokens_in_micro_cents' => 1_000_000_000,
    ])->external_name]);

    Http::fake(['*' => Http::response(chunkerResponse([
        'prompt_tokens' => 10_000,
        'completion_tokens' => 1_000,
    ]))]);

    $chunk = app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Oceans cover most of Earth.', checksum: 'c'));

    // 10,000 × 3.0 / 1M × 100 = 3.0 cents; 1,000 × 10.0 / 1M × 100 = 1.0 cent.
    expect($chunk->cost)->toEqualWithDelta(4.0, 1e-9);
});

it('estimates the cost when the provider reports no usage', function (): void {
    Http::fake(['*' => Http::response(chunkerResponse(null))]);

    $chunk = app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Oceans cover most of Earth.', checksum: 'c'));

    expect($chunk->cost)->toBeGreaterThan(0.0);
});

it('does not log the model output, at any level', function (): void {
    Http::fake(['*' => Http::response(chunkerResponse(['prompt_tokens' => 1, 'completion_tokens' => 1]))]);

    // Every log write, whatever its level or channel, raises MessageLogged.
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Secret customer page.', checksum: 'c'));

    expect(implode("\n", $logged))->not->toContain('A page about oceans.');
});

it('asks for the summary and questions in the page\'s own language', function (): void {
    Http::fake(['*' => Http::response(chunkerResponse(['prompt_tokens' => 1, 'completion_tokens' => 1]))]);

    app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Dania er syv år gammel.', checksum: 'c'));

    Http::assertSent(fn (Request $request): bool => str_contains(
        $request['messages'][0]['content'],
        'Write the summary and every question in the language of TargetPage',
    ));
});
