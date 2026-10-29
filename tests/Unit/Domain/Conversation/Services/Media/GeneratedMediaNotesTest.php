<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaNotes;
use Illuminate\Support\Str;

/**
 * The model's notes about the files a reply asked for are read only from the
 * conversation of the given agent, never from another agent's.
 */
it('never reads a conversation of another agent', function (): void {
    $media = GeneratedMedia::factory()->ready()->create();
    $reply = new MessageData(
        id: (string) Str::uuid7(),
        role: MessageRole::Assistant,
        content: 'Here it is.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        metadata: ['artifacts' => [['kind' => 'image', 'data' => ['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A red bicycle']]]],
    );

    [$message] = app(GeneratedMediaNotes::class)->forLlm([$reply], Agent::factory()->create(), $media->conversation->external_id);

    expect($message->message_for_llm)->toBeNull();
});
