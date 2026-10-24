<?php

use App\Http\Responses\SseResponder;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Tests\Support\Http\BufferedSseResponder;

/**
 * The App-layer SSE transport: the domain yields StreamEvents, this turns them
 * into a `text/event-stream` response, numbers them, and keeps going past an
 * event it cannot encode. A crash of the event generator is logged and
 * reported; a disconnect the generator handled itself is not.
 */

/**
 * Run the loop respond() hands a generator to and return what it wrote.
 * respond() first closes every output buffer (the test runner's too) so the
 * stream reaches the browser at once, which a test cannot capture, so the
 * loop is run on its own here, through the private method (respond()
 * offers no way in that leaves the buffers alone).
 */
function sseOutput(Generator $events): string
{
    ob_start();

    try {
        (function (Generator $events): void {
            $this->pumpGenerator($events);
        })->call(new SseResponder, $events);
    } finally {
        $output = (string) ob_get_clean();
    }

    return $output;
}

beforeEach(function (): void {
    Exceptions::fake();
    Log::spy();
});

it('returns a streamed response with the event-stream headers', function (): void {
    $response = new SseResponder()->respond((function (): Generator {
        yield StreamEvent::delta('msg-1', 'hi');
    })());

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toBe('text/event-stream')
        ->and($response->headers->get('cache-control'))->toContain('no-cache')
        ->and($response->headers->get('x-accel-buffering'))->toBe('no')
        ->and($response->headers->get('connection'))->toBe('keep-alive');
});

it('numbers the events from zero in the order they come', function (): void {
    $output = sseOutput((function (): Generator {
        yield StreamEvent::delta('msg-1', 'Hel');
        yield StreamEvent::delta('msg-1', 'lo');
        yield StreamEvent::done('msg-1', 'stop');
    })());

    $envelopes = BufferedSseResponder::events($output);

    expect(array_column($envelopes, 'seq'))->toBe([0, 1, 2])
        ->and(array_column($envelopes, 'type'))->toBe(['delta', 'delta', 'done'])
        ->and($envelopes[1]['data'])->toBe(['text' => 'lo']);
});

it('skips and logs an event it cannot encode, then carries on', function (): void {
    $output = sseOutput((function (): Generator {
        yield StreamEvent::delta('msg-1', 'before');
        yield StreamEvent::delta('msg-1', "bad \xB1 bytes");
        yield StreamEvent::delta('msg-1', 'after');
    })());

    $envelopes = BufferedSseResponder::events($output);

    expect(array_column($envelopes, 'seq'))->toBe([0, 2])
        ->and(array_column(array_column($envelopes, 'data'), 'text'))->toBe(['before', 'after']);

    Log::shouldHaveReceived('error')->once()->with('SSE envelope encoding failed', Mockery::on(
        fn (array $context): bool => $context['type'] === 'delta',
    ));
    Exceptions::assertReported(JsonException::class);
});

it('logs and reports a crash of the event generator and stops', function (): void {
    $output = sseOutput((function (): Generator {
        yield StreamEvent::delta('msg-1', 'partial');

        throw new RuntimeException('provider exploded');
    })());

    expect(array_column(BufferedSseResponder::events($output), 'seq'))->toBe([0]);

    Log::shouldHaveReceived('error')->once()->with('SSE stream generator crashed', [
        'exception' => RuntimeException::class,
        'message' => 'provider exploded',
    ]);
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'provider exploded');
});

it('treats a disconnect the generator handled itself as a normal end', function (): void {
    $output = sseOutput((function (): Generator {
        yield StreamEvent::delta('msg-1', 'partial');

        throw new ClientDisconnected;
    })());

    expect(array_column(BufferedSseResponder::events($output), 'seq'))->toBe([0]);

    Log::shouldNotHaveReceived('error');
    Exceptions::assertNothingReported();
});
