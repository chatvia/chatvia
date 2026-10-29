<?php

use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\StartVoiceCallAction;
use Domain\Conversation\Data\Voice\VoiceCallerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Exceptions\VoiceCallRefusedException;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Llm\Enums\AiProvider;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Services\VisitorFingerprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * A voice call that may not start (voice off, a teammate holds the chat, a
 * blocked visitor, every worker busy, the monthly messages used) is refused
 * before OpenAI is called or a worker is sent; one OpenAI fails to open is
 * refused and gives its worker slot back. The plain start is covered by the
 * StartVoiceCall controller tests. OpenAI's realtime endpoint is faked.
 */
beforeEach(function (): void {
    withLicense();
    connectAiProviders(AiProvider::OpenAi);
    config(['chatvia.ai.voice' => 'auto', 'horizon.defaults.voice.maxProcesses' => 1]);
    Queue::fake();
    VoiceCalls::fakeOpenAi();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    $this->conversation = Conversation::factory()->forAgent($this->agent)->hostedChat()->create();
    $this->session = $this->conversation->session;
});

function svcCaller(?string $ipAddress = null, ?string $userAgent = null): VoiceCallerData
{
    return new VoiceCallerData(
        agent: test()->agent,
        surface: ConversationSource::HostedChat,
        sessionId: test()->session->external_id,
        conversationId: test()->conversation->external_id,
        ipAddress: $ipAddress,
        userAgent: $userAgent,
    );
}

function svcRefusal(VoiceCallerData $caller): ?VoiceCallRefusal
{
    try {
        app(StartVoiceCallAction::class)->execute($caller, 'v=0 offer');
    } catch (VoiceCallRefusedException $e) {
        return $e->refusal;
    }

    return null;
}

it('refuses a call that may not start, without calling OpenAI or sending a worker', function (string $case, VoiceCallRefusal $refusal): void {
    match ($case) {
        'voice off' => $this->agent->setSetting('allow_voice_conversation', false, 'appearance'),
        'handed off' => $this->conversation->update(['handoff_status' => HandoffStatus::Human, 'handoff_user_id' => $this->agent->workspace->owner->id]),
        'blocked' => BlockedVisitor::factory()->create([
            'workspace_id' => $this->agent->workspace_id,
            'visitor_key' => app(VisitorFingerprint::class)->for($this->agent->workspace_id, '203.0.113.60', 'Safari/18'),
        ]),
        'busy' => app(VoiceCallSlots::class)->take('another-call', 600),
        'message limit' => MessageLimit::factory()->forWorkspace($this->agent->workspace)->reached()->create(),
    };

    expect(svcRefusal(svcCaller('203.0.113.60', 'Safari/18')))->toBe($refusal);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([
    'voice is off for the agent' => ['voice off', VoiceCallRefusal::NotAvailable],
    'a teammate holds the conversation' => ['handed off', VoiceCallRefusal::HandedOff],
    'an admin blocked the visitor' => ['blocked', VoiceCallRefusal::Blocked],
    'every voice worker is busy' => ['busy', VoiceCallRefusal::Busy],
    'the workspace used its monthly messages' => ['message limit', VoiceCallRefusal::MessageLimitReached],
]);

it('frees the worker slot and refuses the call when OpenAI fails', function (): void {
    VoiceCalls::refuseNewCalls();

    expect(svcRefusal(svcCaller()))->toBe(VoiceCallRefusal::Failed)
        ->and(app(VoiceCallSlots::class)->take((string) Str::uuid7(), 60))->toBeTrue();
    Queue::assertNothingPushed();
});
