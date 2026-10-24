<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\EndVoiceCallAction;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * Pressing End hangs the call up with OpenAI at once and flags it ended for
 * the worker on it; only the visitor's own call can be ended. OpenAI's
 * realtime endpoint is faked.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto']);
    VoiceCalls::fakeOpenAi();

    $this->agent = Agent::factory()->create();
    $this->call = VoiceCalls::data($this->agent, (string) Str::uuid(), (string) Str::uuid(), providerCallId: 'rtc_abc123');
    app(VoiceCallStore::class)->put($this->call);
});

it('hangs up the visitor\'s own call and flags it ended', function (): void {
    app(EndVoiceCallAction::class)->execute($this->agent, $this->call->session_id, $this->call->call_id);

    expect(app(VoiceCallStore::class)->isEnded($this->call->call_id))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/realtime/calls/rtc_abc123/hangup'));
});

it('does not end a call of another session, another agent or no call at all', function (string $who): void {
    [$agent, $sessionId, $callId] = match ($who) {
        'another session' => [$this->agent, (string) Str::uuid(), $this->call->call_id],
        'another agent' => [Agent::factory()->create(), $this->call->session_id, $this->call->call_id],
        'an unknown call' => [$this->agent, $this->call->session_id, Str::uuid7()->toString()],
    };

    expect(function () use ($agent, $sessionId, $callId): void {
        app(EndVoiceCallAction::class)->execute($agent, $sessionId, $callId);
    })->toThrow(ModelNotFoundException::class)
        ->and(app(VoiceCallStore::class)->isEnded($this->call->call_id))->toBeFalse();
    Http::assertNothingSent();
})->with(['another session', 'another agent', 'an unknown call']);
