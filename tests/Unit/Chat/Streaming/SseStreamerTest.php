<?php

namespace Tests\Unit\Chat\Streaming;

use Domain\Chat\Services\Streaming\SseStreamer;
use Domain\Chat\Services\Streaming\StreamEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SseStreamerTest extends TestCase
{
    public function test_returns_streamed_response_with_sse_headers(): void
    {
        $streamer = new SseStreamer();

        $response = $streamer->respond((function () {
            yield StreamEvent::delta('msg-1', 'hi');
        })());

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $headers = $response->headers->all();

        $this->assertSame('text/event-stream', $headers['content-type'][0]);
        $this->assertStringContainsString('no-cache', $headers['cache-control'][0]);
        $this->assertSame('no', $headers['x-accel-buffering'][0]);
        $this->assertSame('keep-alive', $headers['connection'][0]);
    }

    public function test_accepts_any_iterable_not_only_generators(): void
    {
        $streamer = new SseStreamer();

        $response = $streamer->respond([StreamEvent::delta('msg-1', 'a')]);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }
}
