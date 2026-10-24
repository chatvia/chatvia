<?php

declare(strict_types=1);

namespace Tests\Support\Http;

use App\Http\Responses\SseResponder;
use Illuminate\Support\Str;
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
        return Str::of($sse)
            ->trim()
            ->explode("\n\n")
            ->filter(static fn (string $frame): bool => Str::startsWith($frame, 'data: '))
            ->map(static fn (string $frame): array => (array) json_decode(Str::chopStart($frame, 'data: '), true, 512, JSON_THROW_ON_ERROR))
            ->values()
            ->all();
    }
}
