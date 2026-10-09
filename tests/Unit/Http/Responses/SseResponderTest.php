<?php

use App\Http\Responses\SseResponder;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The App-layer SSE transport: the domain yields StreamEvents, this turns them
 * into a `text/event-stream` response.
 */
it('returns a streamed response with the event-stream headers', function (): void {
    $response = new SseResponder()->respond((function () {
        yield StreamEvent::delta('msg-1', 'hi');
    })());

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toBe('text/event-stream')
        ->and($response->headers->get('cache-control'))->toContain('no-cache')
        ->and($response->headers->get('x-accel-buffering'))->toBe('no')
        ->and($response->headers->get('connection'))->toBe('keep-alive');
});

it('accepts any iterable, not only generators', function (): void {
    expect(new SseResponder()->respond([StreamEvent::delta('msg-1', 'a')]))
        ->toBeInstanceOf(StreamedResponse::class);
});
