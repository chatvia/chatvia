<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Speech\MessageSpeechFiles;
use Domain\Llm\Enums\SpeechFormat;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Saved replies read aloud live under the agent's folder, one file per
 * message, service and voice, and go with their conversation or message.
 */
beforeEach(function (): void {
    Storage::fake(MessageSpeechFiles::DISK);
    $this->agent = Agent::factory()->create();
    $this->files = app(MessageSpeechFiles::class);
    $this->conversation = (string) Str::uuid();
});

it('keeps one file per message, service and voice under the agent', function (): void {
    $path = $this->files->pathFor($this->agent, $this->conversation, 'm1', 'openai tts', 'marin', SpeechFormat::Mp3);

    expect($path)->toStartWith($this->agent->settingsStorageRoot().'/speech/'.$this->conversation.'/m1-')
        ->and($path)->toEndWith('.mp3')
        ->and($this->files->pathFor($this->agent, $this->conversation, 'm1', 'openai tts', 'cedar', SpeechFormat::Mp3))->not->toBe($path)
        ->and($this->files->pathFor($this->agent, $this->conversation, 'm1', 'elevenlabs v4', 'marin', SpeechFormat::Mp3))->not->toBe($path);
});

it('forgets one message, in every voice, and keeps the others', function (): void {
    $disk = Storage::disk(MessageSpeechFiles::DISK);
    $disk->put($a = $this->files->pathFor($this->agent, $this->conversation, 'm1', 's', 'v1', SpeechFormat::Mp3), 'a');
    $disk->put($b = $this->files->pathFor($this->agent, $this->conversation, 'm1', 's', 'v2', SpeechFormat::Wav), 'b');
    $disk->put($c = $this->files->pathFor($this->agent, $this->conversation, 'm2', 's', 'v1', SpeechFormat::Mp3), 'c');

    $this->files->forgetMessage($this->agent, $this->conversation, 'm1');

    expect($disk->exists($a))->toBeFalse()
        ->and($disk->exists($b))->toBeFalse()
        ->and($disk->exists($c))->toBeTrue();
});

it('forgets whole conversations, of a deleted agent too', function (): void {
    $disk = Storage::disk(MessageSpeechFiles::DISK);
    $disk->put($path = $this->files->pathFor($this->agent, $this->conversation, 'm1', 's', 'v1', SpeechFormat::Mp3), 'a');
    $this->agent->delete();

    $this->files->forgetConversations([(object) ['agent_id' => $this->agent->id, 'external_id' => $this->conversation]]);

    expect($disk->exists($path))->toBeFalse();
});
