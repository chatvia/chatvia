<?php

namespace Tests\Unit\Chat\Streaming;

use Domain\Chat\Services\Streaming\ErrorCode;
use Domain\Chat\Services\Streaming\EventType;
use Domain\Chat\Services\Streaming\StreamEvent;
use PHPUnit\Framework\TestCase;

class StreamEventTest extends TestCase
{
    public function test_encodes_delta_as_single_data_line(): void
    {
        $event = StreamEvent::delta('msg-1', 'hello')->withSeq(3);
        $line = $event->toLine();

        $this->assertStringStartsWith('data: ', $line);
        $this->assertStringEndsWith("\n\n", $line);

        $payload = json_decode(substr($line, 6, -2), true);

        $this->assertSame(1, $payload['v']);
        $this->assertSame(3, $payload['seq']);
        $this->assertSame('msg-1', $payload['id']);
        $this->assertSame('delta', $payload['type']);
        $this->assertSame(['text' => 'hello'], $payload['data']);
    }

    public function test_emits_typed_error_event_distinct_from_delta(): void
    {
        $event = StreamEvent::error('msg-1', ErrorCode::SubscriptionExpired, 'Renew to continue.');
        $payload = json_decode(substr($event->toLine(), 6, -2), true);

        $this->assertSame('error', $payload['type']);
        $this->assertSame('subscription_expired', $payload['data']['code']);
        $this->assertSame('Renew to continue.', $payload['data']['message']);
        $this->assertFalse($payload['data']['retryable']);
    }

    public function test_marks_upstream_and_rate_limit_as_retryable(): void
    {
        $this->assertTrue(ErrorCode::UpstreamUnavailable->isRetryable());
        $this->assertTrue(ErrorCode::RateLimited->isRetryable());
        $this->assertFalse(ErrorCode::SubscriptionExpired->isRetryable());
        $this->assertFalse(ErrorCode::Internal->isRetryable());
    }

    public function test_preserves_unicode_and_slashes(): void
    {
        $event = StreamEvent::delta('msg-1', "héllo / 你好");
        $line = $event->toLine();

        $this->assertStringContainsString("héllo / 你好", $line);
    }

    public function test_done_event_carries_reason_and_timing(): void
    {
        $event = StreamEvent::done('msg-1', 'complete', 1234);
        $payload = json_decode(substr($event->toLine(), 6, -2), true);

        $this->assertSame('done', $payload['type']);
        $this->assertSame('complete', $payload['data']['reason']);
        $this->assertSame(1234, $payload['data']['response_time_ms']);
        $this->assertIsString($payload['data']['finished_at']);
    }

    public function test_start_event_carries_ids_and_replay_flag(): void
    {
        $event = StreamEvent::start('msg-1', [
            'assistant_message_id' => 'msg-1',
            'user_message_id' => 'user-9',
            'request_id' => 'req-42',
            'language' => 'en',
            'replayed' => true,
        ]);

        $payload = json_decode(substr($event->toLine(), 6, -2), true);

        $this->assertSame('start', $payload['type']);
        $this->assertSame('msg-1', $payload['data']['assistant_message_id']);
        $this->assertSame('user-9', $payload['data']['user_message_id']);
        $this->assertSame('req-42', $payload['data']['request_id']);
        $this->assertSame('en', $payload['data']['language']);
        $this->assertTrue($payload['data']['replayed']);
    }

    public function test_event_type_enum_is_closed(): void
    {
        $this->assertCount(7, EventType::cases());
    }
}
