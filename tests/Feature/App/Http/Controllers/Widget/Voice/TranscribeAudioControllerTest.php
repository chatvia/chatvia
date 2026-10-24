<?php

use Domain\Agent\Models\Agent;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Operations\Media\NullTranscriber;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\UploadedFile;
use Tests\Support\Llm\FakeTranscriber;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The widget composer's mic: POST /widget/transcribe writes a voice note down
 * for a session of the agent, while the owner allows voice input. The
 * speech-to-text service is faked; with none set up the call answers 503,
 * and a failing service 500.
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

it('writes the voice note down for a session of the agent', function (): void {
    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', tacRecording())
        ->assertOk()
        ->assertExactJson(['text' => 'Hello there']);
});

it('refuses a call without a session of the agent', function (Closure $session): void {
    $this->withHeaders(EmbeddedWidget::headers($this->agent, $session->call($this)))
        ->postJson('/widget/transcribe', tacRecording())
        ->assertForbidden();
})->with([
    'no session' => [fn (): array => []],
    'an unknown session' => [fn (): array => [WidgetHeaders::SESSION_ID => '6f1c1a52-6a43-4a4e-9f0e-6f6b1b3c2d10']],
    'a session of another agent' => [function (): array {
        $other = Agent::factory()->create(['is_public' => true]);
        EmbeddedWidget::allowSite($other);

        return [WidgetHeaders::SESSION_ID => EmbeddedWidget::boot($other)['session_id']];
    }],
]);

it('refuses while the owner has turned voice input off', function (): void {
    $this->agent->setSetting('allow_voice_input', false, 'appearance');

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', tacRecording())
        ->assertForbidden();
});

it('needs a recording', function (): void {
    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audio');
});

it('answers 503 while no speech-to-text service is set up', function (): void {
    app()->instance(Transcriber::class, app(NullTranscriber::class));

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', tacRecording())
        ->assertServiceUnavailable();
});

it('answers 500 when the speech-to-text service fails', function (): void {
    app()->instance(Transcriber::class, new class implements Transcriber
    {
        public function transcribe(string $filePath): string
        {
            throw new RuntimeException('provider down');
        }

        public function takesAsIs(string $extension, int $bytes): bool
        {
            return true;
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function name(): string
        {
            return 'failing';
        }
    });

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', tacRecording())
        ->assertInternalServerError();
});
