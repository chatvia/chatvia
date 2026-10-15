<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\TranscribeAudioAction;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * The widget composer's mic: POST /widget/transcribe writes a voice note down
 * for a session of the agent, while the owner allows voice input.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
});

/** @return array{audio: UploadedFile} */
function transcribeRecording(): array
{
    return ['audio' => UploadedFile::fake()->create('recording.webm', 12, 'audio/webm')];
}

it('writes the voice note down for a session of the agent', function (): void {
    $this->mock(TranscribeAudioAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')->once()->withArgs(fn (string $path, string $extension): bool => $extension === 'webm')->andReturn('Hello there');
    });

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', transcribeRecording())
        ->assertOk()
        ->assertExactJson(['text' => 'Hello there']);
});

it('refuses a call without a session of the agent', function (?string $sessionId): void {
    $this->mock(TranscribeAudioAction::class)->shouldNotReceive('execute');

    $this->withHeaders(EmbeddedWidget::headers($this->agent, $sessionId === null ? [] : [WidgetHeaders::SESSION_ID => $sessionId]))
        ->postJson('/widget/transcribe', transcribeRecording())
        ->assertForbidden();
})->with([
    'no session' => [null],
    'unknown session' => ['6f1c1a52-6a43-4a4e-9f0e-6f6b1b3c2d10'],
]);

it('refuses a session of another agent', function (): void {
    $other = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($other);
    $otherSession = EmbeddedWidget::boot($other)['session_id'];
    $this->mock(TranscribeAudioAction::class)->shouldNotReceive('execute');

    $this->withHeaders(EmbeddedWidget::headers($this->agent, [WidgetHeaders::SESSION_ID => $otherSession]))
        ->postJson('/widget/transcribe', transcribeRecording())
        ->assertForbidden();
});

it('refuses while the owner has turned voice input off', function (): void {
    $this->agent->setSetting('allow_voice_input', false, 'appearance');
    $this->mock(TranscribeAudioAction::class)->shouldNotReceive('execute');

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', transcribeRecording())
        ->assertForbidden();
});

it('needs a recording', function (): void {
    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('audio');
});

it('answers 503 while no speech-to-text service is set up', function (): void {
    $this->mock(TranscribeAudioAction::class)->shouldReceive('execute')->andThrow(new TranscriptionNotConfiguredException);

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', transcribeRecording())
        ->assertServiceUnavailable();
});

it('answers 500 when the speech-to-text service fails', function (): void {
    $this->mock(TranscribeAudioAction::class)->shouldReceive('execute')->andThrow(new RuntimeException('provider down'));

    $this->withHeaders(EmbeddedWidget::chatHeaders($this->agent))
        ->postJson('/widget/transcribe', transcribeRecording())
        ->assertInternalServerError();
});
