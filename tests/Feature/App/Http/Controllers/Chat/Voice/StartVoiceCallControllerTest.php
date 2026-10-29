<?php

use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\StartVoiceCallAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Jobs\JoinVoiceCallJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Conversation\Voice\VoiceCalls;

/**
 * A hosted-chat visitor starts a voice call ({@see StartVoiceCallAction}):
 * the browser posts its WebRTC offer, the server opens the call with OpenAI
 * (the key never reaches the browser) and sends a `voice` worker to join it.
 * Each refusal answers its own status and reason; which calls are refused is
 * the action's rule.
 */
beforeEach(function (): void {
    withLicense();

    connectAiProviders(AiProvider::OpenAi);
    config([
        'chatvia.ai.voice' => 'auto',
        'horizon.defaults.voice.maxProcesses' => 2,
    ]);
    Queue::fake();
    VoiceCalls::fakeOpenAi();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    $this->chat = '/chat/'.$this->agent->slug;
});

it('opens a hosted-chat call in a new conversation, keeps the key on the server and sends a worker to join it', function (): void {
    $response = $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])
        ->assertOk()
        ->assertJsonPath('sdp', 'v=0 answer')
        ->assertJsonStructure(['sdp', 'call_id', 'conversation_id', 'max_seconds', 'join_timeout', 'tools', 'knowledge_tool'])
        ->assertJsonPath('knowledge_tool', 'search_knowledge');

    $conversation = Conversation::query()->where('external_id', $response->json('conversation_id'))->firstOrFail();
    expect($conversation->agent_id)->toBe($this->agent->id);

    $call = app(VoiceCallStore::class)->find($response->json('call_id'));
    expect($call?->provider_call_id)->toBe('rtc_abc123')
        ->and($call?->conversation_id)->toBe($conversation->external_id)
        ->and($call?->max_seconds)->toBe(600);

    Queue::assertPushedOn('voice', JoinVoiceCallJob::class, fn (JoinVoiceCallJob $job): bool => $job->call->call_id === $response->json('call_id'));

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/realtime/calls')
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && strlen($request->header('OpenAI-Safety-Identifier')[0] ?? '') === 64);

    expect(json_encode($response->json()))->not->toContain('test-key');
});

it('answers a refused call with its status and reason', function (Closure $refuse, int $status, string $error): void {
    $this->postJson($this->chat.'/voice'.$refuse($this->agent), ['sdp' => 'v=0 offer'])
        ->assertStatus($status)
        ->assertJsonPath('error', $error);
})->with([
    'voice off for the agent' => [function (Agent $agent): string {
        $agent->setSetting('allow_voice_conversation', false, 'appearance');

        return '';
    }, 403, 'voice_not_available'],
    'the workspace used its monthly messages' => [function (Agent $agent): string {
        MessageLimit::factory()->forWorkspace($agent->workspace)->reached()->create();

        return '';
    }, 429, 'message_limit_reached'],
    'a teammate holds the conversation' => [function (Agent $agent): string {
        $conversationId = test()->postJson("/chat/{$agent->slug}/voice", ['sdp' => 'v=0 offer'])->json('conversation_id');
        Conversation::query()->where('external_id', $conversationId)->update(['handoff_status' => HandoffStatus::Human]);

        return '/'.$conversationId;
    }, 409, 'handed_off'],
    'every voice worker on a call' => [function (): string {
        $slots = app(VoiceCallSlots::class);
        $slots->take('call-a', 600);
        $slots->take('call-b', 600);

        return '';
    }, 503, 'voice_busy'],
    'OpenAI refuses the call' => [function (): string {
        VoiceCalls::refuseNewCalls();

        return '';
    }, 502, 'voice_failed'],
]);
