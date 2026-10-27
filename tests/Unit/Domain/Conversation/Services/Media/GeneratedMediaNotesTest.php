<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Conversation\Services\Media\GeneratedMediaNotes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The model's history gets a note for each file a reply asked for, with the
 * status read from the file's row, and never the file itself.
 */
function gmnReply(string $kind, string $id, string $prompt): MessageData
{
    return new MessageData(
        id: (string) Str::uuid7(),
        role: MessageRole::Assistant,
        content: 'Here it is.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
        metadata: ['artifacts' => [['kind' => $kind, 'data' => ['id' => $id, 'status' => 'pending', 'prompt' => $prompt]]]],
    );
}

/**
 * @return string|null the reply's LLM-facing text after the notes are added
 */
function gmnNoted(GeneratedMedia $media, string $prompt): ?string
{
    [$message] = app(GeneratedMediaNotes::class)->forLlm(
        [gmnReply($media->kind->value, $media->external_id, $prompt)],
        $media->agent,
        $media->conversation->external_id,
    );

    return $message->message_for_llm;
}

it('notes a ready picture after the reply', function (): void {
    $media = GeneratedMedia::factory()->ready()->create();

    expect(gmnNoted($media, 'A red bicycle'))->toBe(
        "Here it is.\n\n[You asked for a picture from the description \"A red bicycle\". It is ready, and the chat shows it. You have not seen it.]"
    );
});

it('notes a video that is still being made', function (): void {
    $media = GeneratedMedia::factory()->video()->create();

    expect(gmnNoted($media, 'Waves at sunset'))->toBe(
        "Here it is.\n\n[You asked for a video from the description \"Waves at sunset\". It is still being made, and the chat shows it by itself when it is ready. You have not seen it.]"
    );
});

it('notes why a file was not made', function (): void {
    $media = GeneratedMedia::factory()->failed(GeneratedMediaFailure::Refused)->create();

    expect(gmnNoted($media, 'A red bicycle'))->toContain('It was not made: the service refused the request.');
});

it('notes the text an audio file reads aloud', function (): void {
    $media = GeneratedMedia::factory()->audio()->ready()->create();

    expect(gmnNoted($media, 'Welcome to our shop.'))->toBe(
        "Here it is.\n\n[You asked for an audio file reading \"Welcome to our shop.\" aloud. It is ready, and the chat shows it. You have not heard it.]"
    );
});

it('leaves a history without files alone, without a query', function (): void {
    $message = new MessageData(id: 'm1', role: MessageRole::Assistant, content: 'Hello', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp);
    $agent = Agent::factory()->create();

    DB::enableQueryLog();
    $messages = app(GeneratedMediaNotes::class)->forLlm([$message], $agent, (string) Str::uuid7());

    expect($messages)->toBe([$message])
        ->and(DB::getQueryLog())->toBe([]);
});

it('never reads a conversation of another agent', function (): void {
    $media = GeneratedMedia::factory()->ready()->create();

    [$message] = app(GeneratedMediaNotes::class)->forLlm(
        [gmnReply('image', $media->external_id, 'A red bicycle')],
        Agent::factory()->create(),
        $media->conversation->external_id,
    );

    expect($message->message_for_llm)->toBeNull();
});
