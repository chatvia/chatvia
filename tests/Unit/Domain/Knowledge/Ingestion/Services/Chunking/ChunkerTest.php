<?php

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\Chunking\Chunker;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Chunker::enhancePage() asks the enhancement model for a page summary +
 * questions. The raw model output (derived from customer content) must not be
 * written to the logs.
 */
beforeEach(function (): void {
    config(['chatvia.ai.tasks.enhancement' => (string) testLlm('smart-model')->id]);
});

function chkResponse(?array $usage): array
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

it('returns the summary, questions and model of the enhancement call', function (): void {
    Http::fake(['*' => Http::response(chkResponse(null))]);

    $chunk = app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Oceans cover most of Earth.', checksum: 'c'));

    expect($chunk->summary)->toBe('A page about oceans.')
        ->and($chunk->questions)->toBe(['q1', 'q2', 'q3', 'q4', 'q5'])
        ->and($chunk->model)->toBe('smart-model');

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'smart-model');
});

it('does not log the model output, at any level', function (): void {
    Http::fake(['*' => Http::response(chkResponse(['prompt_tokens' => 1, 'completion_tokens' => 1]))]);

    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    app(Chunker::class)->enhancePage(new PageData(pageNumber: 1, content: 'Secret customer page.', checksum: 'c'));

    expect(implode("\n", $logged))->not->toContain('A page about oceans.');
});
