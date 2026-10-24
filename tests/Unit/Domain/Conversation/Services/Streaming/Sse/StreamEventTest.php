<?php

declare(strict_types=1);

use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Illuminate\Support\Str;

/**
 * Each stream event is one `data:` line of versioned JSON that the chat
 * clients read.
 */

/** @return array<string, mixed> the JSON of the event's line */
function seEventPayload(StreamEvent $event): array
{
    return json_decode(Str::of($event->toLine())->chopStart('data: ')->chopEnd("\n\n")->toString(), true);
}

it('encodes a delta as a single data line', function (): void {
    $line = StreamEvent::delta('msg-1', 'hello')->withSeq(3)->toLine();

    expect($line)->toStartWith('data: ')
        ->and($line)->toEndWith("\n\n")
        ->and(seEventPayload(StreamEvent::delta('msg-1', 'hello')->withSeq(3)))->toBe([
            'v' => 1,
            'seq' => 3,
            'id' => 'msg-1',
            'type' => 'delta',
            'data' => ['text' => 'hello'],
        ]);
});

it('emits a typed error event distinct from a delta', function (): void {
    $payload = seEventPayload(StreamEvent::error('msg-1', ErrorCode::MessageLimitReached, 'Try again next month.'));

    expect($payload['type'])->toBe('error')
        ->and($payload['data']['code'])->toBe('message_limit_reached')
        ->and($payload['data']['message'])->toBe('Try again next month.')
        ->and($payload['data']['retryable'])->toBeFalse();
});

it('keeps unicode and slashes unescaped', function (): void {
    expect(StreamEvent::delta('msg-1', 'héllo / 你好')->toLine())->toContain('héllo / 你好');
});

it('carries the reason and timing on the done event', function (): void {
    $payload = seEventPayload(StreamEvent::done('msg-1', 'complete', 1234));

    expect($payload['type'])->toBe('done')
        ->and($payload['data']['reason'])->toBe('complete')
        ->and($payload['data']['response_time_ms'])->toBe(1234)
        ->and($payload['data']['finished_at'])->toBeString();
});

it('carries the ids and the replay flag on the start event', function (): void {
    $payload = seEventPayload(StreamEvent::start('msg-1', [
        'assistant_message_id' => 'msg-1',
        'user_message_id' => 'user-9',
        'request_id' => 'req-42',
        'language' => 'en',
        'replayed' => true,
    ]));

    expect($payload['type'])->toBe('start')
        ->and($payload['data'])->toBe([
            'assistant_message_id' => 'msg-1',
            'user_message_id' => 'user-9',
            'request_id' => 'req-42',
            'language' => 'en',
            'replayed' => true,
        ]);
});

it('sends a piece of thinking as a reasoning event', function (): void {
    $event = StreamEvent::reasoning('asst-1', 'Weighing it up.');

    expect($event->type)->toBe(EventType::Reasoning)
        ->and($event->data)->toBe(['text' => 'Weighing it up.']);
});
