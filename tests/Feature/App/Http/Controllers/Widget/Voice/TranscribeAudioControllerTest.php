<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Contracts\Transcriber;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\UploadedFile;
use Tests\Support\Llm\FakeTranscriber;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The widget composer's mic: POST /widget/transcribe writes a voice note down
 * for a session of the agent, taking only recordings in a browser audio
 * format and naming its working copy by the detected format, never by the
 * uploaded name. The speech-to-text service is faked.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
    app()->instance(Transcriber::class, new FakeTranscriber('Hello there'));
});

/** @return array{audio: UploadedFile} */
function tacRecording(): array
{
    return ['audio' => UploadedFile::fake()->create('recording.webm', 12, 'audio/webm')];
}

it('refuses a call without a session of the agent', function (Closure $session): void {
    $this->withHeaders(EmbeddedWidget::headers($this->agent, $session->call($this)))
        ->postJson('/widget/transcribe', tacRecording())
        ->assertForbidden();
})->with([
    'an unknown session' => [fn (): array => [WidgetHeaders::SESSION_ID => '6f1c1a52-6a43-4a4e-9f0e-6f6b1b3c2d10']],
    'a session of another agent' => [function (): array {
        $other = Agent::factory()->create(['is_public' => true]);
        EmbeddedWidget::allowSite($other);

        return [WidgetHeaders::SESSION_ID => EmbeddedWidget::boot($other)['session_id']];
    }],
]);

it('refuses a file that is not a recording in a browser audio format', function (UploadedFile $file): void {
    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', ['audio' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audio');
})->with([
    'a playlist named like audio' => [fn (): UploadedFile => UploadedFile::fake()->create('recording.webm', 1, 'text/plain')],
    'a video format no recorder writes' => [fn (): UploadedFile => UploadedFile::fake()->create('recording.mov', 12, 'video/quicktime')],
]);

it('writes the voice note down for a session of the agent, naming the working copy by the detected format', function (): void {
    $extensions = [];
    app()->instance(Transcriber::class, new FakeTranscriber('Hello there', function (string $extension) use (&$extensions): bool {
        $extensions[] = $extension;

        return true;
    }));

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', ['audio' => UploadedFile::fake()->create('recording.webm', 12, 'audio/mp4')])
        ->assertOk()
        ->assertExactJson(['text' => 'Hello there']);

    expect($extensions)->toBe(['mp4']);
});
