<?php

use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\StartVoiceCallAction;
use Domain\Conversation\Data\Voice\VoiceCallerData;
use Domain\Conversation\Data\Voice\VoiceCallStartedData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Exceptions\VoiceCallRefusedException;
use Domain\Conversation\Jobs\JoinVoiceCallJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Enums\AiProvider;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Services\VisitorFingerprint;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * Starting a voice call opens it with OpenAI for the browser's offer, keeps
 * the call and one voice worker slot for it, and sends a worker to join; a
 * call that may not or could not start is refused before anything is kept.
 * OpenAI's realtime endpoint is faked.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto', 'horizon.defaults.voice.maxProcesses' => 1]);
    Queue::fake();

    VoiceCalls::fakeOpenAi();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    $this->conversation = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();
    $this->session = $this->conversation->session;
});

function svcCaller(?string $sessionId = null, ?string $conversationId = null): VoiceCallerData
{
    return new VoiceCallerData(
        agent: test()->agent,
        surface: ConversationSource::HostedChat,
        sessionId: $sessionId ?? test()->session->external_id,
        conversationId: $conversationId ?? test()->conversation->external_id,
    );
}

function svcRefusal(Closure $start): ?VoiceCallRefusal
{
    try {
        $start();
    } catch (VoiceCallRefusedException $e) {
        return $e->refusal;
    }

    return null;
}

it('opens the call, keeps it and sends a worker to join it', function (): void {
    $started = app(StartVoiceCallAction::class)->execute(svcCaller(), 'v=0 offer');

    $call = app(VoiceCallStore::class)->find($started->call_id);

    expect($started->sdp)->toBe('v=0 answer')
        ->and($started->conversation_id)->toBe($this->conversation->external_id)
        ->and($started->max_seconds)->toBe(600)
        ->and($call?->provider_call_id)->toBe('rtc_abc123')
        ->and($call?->session_id)->toBe($this->session->external_id)
        // The only worker is now taken by this call.
        ->and(app(VoiceCallSlots::class)->take((string) Str::uuid7(), 60))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/realtime/calls'));
    Queue::assertPushed(JoinVoiceCallJob::class, fn (JoinVoiceCallJob $job): bool => $job->call->call_id === $started->call_id);
});

it('never lets a call run longer than the hard cap, whatever the setting says', function (): void {
    config(['chatvia.voice.max_call_minutes' => 90]);

    expect(app(StartVoiceCallAction::class)->execute(svcCaller(), 'v=0 offer')->max_seconds)->toBe(25 * 60);
});

it('refuses the call when voice is off for the agent, without calling OpenAI', function (): void {
    $this->agent->setSetting('allow_voice_conversation', false, 'appearance');

    expect(svcRefusal(fn (): VoiceCallStartedData => app(StartVoiceCallAction::class)->execute(svcCaller(), 'v=0 offer')))->toBe(VoiceCallRefusal::NotAvailable);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('refuses the call while a teammate holds the conversation', function (): void {
    $this->conversation->update(['handoff_status' => HandoffStatus::Human, 'handoff_user_id' => $this->agent->workspace->owner->id]);

    expect(svcRefusal(fn (): VoiceCallStartedData => app(StartVoiceCallAction::class)->execute(svcCaller(), 'v=0 offer')))->toBe(VoiceCallRefusal::HandedOff);
    Http::assertNothingSent();
});

it('refuses the call from a visitor an admin blocked, without calling OpenAI', function (): void {
    BlockedVisitor::factory()->create([
        'workspace_id' => $this->agent->workspace_id,
        'visitor_key' => app(VisitorFingerprint::class)->for($this->agent->workspace_id, '203.0.113.60', 'Safari/18'),
    ]);

    $caller = new VoiceCallerData(
        agent: $this->agent,
        surface: ConversationSource::HostedChat,
        sessionId: $this->session->external_id,
        conversationId: $this->conversation->external_id,
        ipAddress: '203.0.113.60',
        userAgent: 'Safari/18',
    );

    expect(svcRefusal(fn (): VoiceCallStartedData => app(StartVoiceCallAction::class)->execute($caller, 'v=0 offer')))->toBe(VoiceCallRefusal::Blocked);
    Http::assertNothingSent();
});

it('refuses the call while every voice worker is busy', function (): void {
    app(VoiceCallSlots::class)->take('another-call', 600);

    expect(svcRefusal(fn (): VoiceCallStartedData => app(StartVoiceCallAction::class)->execute(svcCaller(), 'v=0 offer')))->toBe(VoiceCallRefusal::Busy);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('refuses the call once the workspace used its monthly messages, without calling OpenAI', function (): void {
    MessageLimit::factory()->forWorkspace($this->agent->workspace)->reached()->create();

    expect(svcRefusal(fn (): VoiceCallStartedData => app(StartVoiceCallAction::class)->execute(svcCaller(), 'v=0 offer')))->toBe(VoiceCallRefusal::MessageLimitReached);
    Http::assertNothingSent();
});

it('frees the worker slot and refuses the call when OpenAI fails', function (): void {
    VoiceCalls::refuseNewCalls();

    expect(svcRefusal(fn (): VoiceCallStartedData => app(StartVoiceCallAction::class)->execute(svcCaller(), 'v=0 offer')))->toBe(VoiceCallRefusal::Failed)
        ->and(app(VoiceCallSlots::class)->take((string) Str::uuid7(), 60))->toBeTrue();
    Queue::assertNothingPushed();
});

it('does not open a call in a conversation of another session', function (): void {
    $other = Session::factory()->create(['agent_id' => $this->agent->id]);

    expect(fn (): VoiceCallStartedData => app(StartVoiceCallAction::class)->execute(svcCaller(sessionId: $other->external_id), 'v=0 offer'))
        ->toThrow(ModelNotFoundException::class);
    Http::assertNothingSent();
});
