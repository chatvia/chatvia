<?php

use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\Media\GeneratedMediaArtifacts;
use Illuminate\Support\Str;

/**
 * A reply's image and audio artifacts are stored while the file is still
 * being made; every load brings them up to date from their rows, and only
 * from rows of the reply's own conversation.
 */
function gmaReply(string $kind, string $id, array $more = []): MessageData
{
    return new MessageData(
        id: (string) Str::uuid(),
        role: MessageRole::Assistant,
        content: 'Here it is.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        metadata: ['artifacts' => [['kind' => $kind, 'data' => ['id' => $id, 'status' => 'pending', 'prompt' => 'A red bicycle']], ...$more]],
    );
}

it('shows a made file with its link and type, keeping what the tool stored', function (): void {
    $media = GeneratedMedia::factory()->ready()->create();

    [$message] = app(GeneratedMediaArtifacts::class)->forHistory([gmaReply('image', $media->external_id)], $media->conversation);

    expect($message->metadata['artifacts'][0]['data'])->toMatchArray([
        'id' => $media->external_id,
        'status' => 'ready',
        'url' => $media->url,
        'mime_type' => 'image/png',
        'failure' => null,
        'prompt' => 'A red bicycle',
    ]);
});

it('shows why a file was not made', function (): void {
    $media = GeneratedMedia::factory()->failed(GeneratedMediaFailure::Refused)->create();

    [$message] = app(GeneratedMediaArtifacts::class)->forHistory([gmaReply('image', $media->external_id)], $media->conversation);

    expect($message->metadata['artifacts'][0]['data'])->toMatchArray(['status' => 'failed', 'failure' => 'refused', 'url' => null]);
});

it('never shows a file of another conversation', function (): void {
    $elsewhere = GeneratedMedia::factory()->ready()->create();
    $conversation = Conversation::factory()->create();

    [$message] = app(GeneratedMediaArtifacts::class)->forHistory([gmaReply('image', $elsewhere->external_id)], $conversation);

    expect($message->metadata['artifacts'][0]['data'])->toBe(['id' => $elsewhere->external_id, 'status' => 'pending', 'prompt' => 'A red bicycle']);
});

it('leaves other artifacts and a mismatched kind alone', function (): void {
    $media = GeneratedMedia::factory()->ready()->create();
    $chart = ['kind' => 'echarts_option', 'data' => ['series' => []]];

    [$message] = app(GeneratedMediaArtifacts::class)->forHistory([gmaReply('audio', $media->external_id, [$chart])], $media->conversation);

    expect($message->metadata['artifacts'][0]['data']['status'])->toBe('pending')
        ->and($message->metadata['artifacts'][1])->toBe($chart);
});

it('updates chat-log rows in place without saving them', function (): void {
    $media = GeneratedMedia::factory()->audio()->ready()->create();
    $reply = Message::factory()->create([
        'chat_conversation_id' => $media->chat_conversation_id,
        'metadata' => ['artifacts' => [['kind' => 'audio', 'data' => ['id' => $media->external_id, 'status' => 'pending']]]],
    ]);

    app(GeneratedMediaArtifacts::class)->forChatLog([$reply]);

    expect($reply->metadata['artifacts'][0]['data'])->toMatchArray(['status' => 'ready', 'mime_type' => 'audio/mpeg'])
        ->and($reply->fresh()->metadata['artifacts'][0]['data']['status'])->toBe('pending');
});
