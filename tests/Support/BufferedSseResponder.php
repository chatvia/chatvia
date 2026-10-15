<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Responses\SseResponder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a chat turn's events with the same envelope as {@see SseResponder}
 * but without its transport work, so a test can read them in-process with
 * TestResponse::streamedContent(). The real responder closes every output
 * buffer before it pumps, PHPUnit's capture buffer included. Bind it with
 * `app()->instance(SseResponder::class, new BufferedSseResponder)`, and read
 * the frames back with {@see events()}.
 */
final class BufferedSseResponder extends SseResponder
{
    public function respond(iterable $events): StreamedResponse
    {
        return new StreamedResponse(function () use ($events): void {
            $seq = 0;

            foreach ($events as $event) {
                echo $event->withSeq($seq++)->toLine();
            }
        }, 200, ['Content-Type' => 'text/event-stream']);
    }

    /**
     * The decoded event frames of a streamed body, in order.
     *
     * @return list<array<string, mixed>>
     */
    public static function events(string $sse): array
    {
        $frames = array_filter(
            explode("\n\n", trim($sse)),
            static fn (string $frame): bool => str_starts_with($frame, 'data: '),
        );

        return array_values(array_map(
            static fn (string $frame): array => (array) json_decode(substr($frame, 6), true, 512, JSON_THROW_ON_ERROR),
            $frames,
        ));
    }
}
