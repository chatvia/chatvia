<?php

use App\Http\Responses\SseResponder;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

/** A responder whose browser leaves after the given number of events. */
function sseLeavingAfter(int $events): SseResponder
{
    return new class($events) extends SseResponder
    {
        private int $sent = 0;

        public function __construct(private readonly int $leaveAfter) {}

        protected function clientGone(): bool
        {
            return $this->sent >= $this->leaveAfter;
        }

        /** Counts what reached the browser, as the real write does. */
        public function count(): void
        {
            $this->sent++;
        }
    };
}

it('numbers the events it can encode, skips one it cannot, and reports a generator crash', function (): void {
    $output = sseOutput((function (): Generator {
        yield StreamEvent::delta('msg-1', 'before');
        yield StreamEvent::delta('msg-1', "bad \xB1 bytes");
        yield StreamEvent::delta('msg-1', 'after');

        throw new RuntimeException('provider exploded');
    })());

    $envelopes = BufferedSseResponder::events($output);

    expect(array_column($envelopes, 'seq'))->toBe([0, 2])
        ->and(array_column(array_column($envelopes, 'data'), 'text'))->toBe(['before', 'after']);
    Log::shouldHaveReceived('error')->twice();
    Log::shouldHaveReceived('error')->with('SSE envelope encoding failed', Mockery::on(
        fn (array $context): bool => $context['type'] === 'delta',
    ));
    Log::shouldHaveReceived('error')->with('SSE stream generator crashed', [
        'exception' => RuntimeException::class,
        'message' => 'provider exploded',
    ]);
    Exceptions::assertReported(JsonException::class);
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'provider exploded');
});

it('reads a hosted-chat turn to the end after the browser left, tells other turns, and reports nothing', function (): void {
    $hostedResponder = sseLeavingAfter(1);
    $read = [];
    $hostedTurn = (function () use (&$read, $hostedResponder): Generator {
        foreach (['a', 'b', 'c'] as $text) {
            $read[] = $text;
            yield StreamEvent::delta('msg-1', $text);
            $hostedResponder->count();
        }
    })();
    $otherResponder = sseLeavingAfter(1);
    $told = false;
    $otherTurn = (function () use (&$told, $otherResponder): Generator {
        try {
            yield StreamEvent::delta('msg-1', 'a');
            $otherResponder->count();
            yield StreamEvent::delta('msg-1', 'b');
        } catch (ClientDisconnected $disconnected) {
            $told = true;

            throw $disconnected;
        }
    })();

    ob_start();
    Closure::bind(function (Generator $events): void {
        $this->pumpToEnd($events);
    }, $hostedResponder, SseResponder::class)($hostedTurn);
    $hostedOutput = (string) ob_get_clean();
    ob_start();
    Closure::bind(function (Generator $events): void {
        $this->pumpGenerator($events);
    }, $otherResponder, SseResponder::class)($otherTurn);
    ob_end_clean();

    expect($read)->toBe(['a', 'b', 'c'])
        ->and(Str::substrCount($hostedOutput, '"type":"delta"'))->toBe(1)
        ->and($told)->toBeTrue();
    Log::shouldNotHaveReceived('error');
    Exceptions::assertNothingReported();
});
