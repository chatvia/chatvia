<?php

use App\Identity\Workspace\Services\MessageAllowance;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\EndVoiceCallAction;
use Domain\Conversation\Actions\Voice\StartVoiceCallAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Jobs\JoinVoiceCallJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Enums\AiProvider;
use Domain\Widget\WidgetHeaders;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Voice conversations over HTTP ({@see StartVoiceCallAction},
 * {@see EndVoiceCallAction}): the browser posts its WebRTC offer, the server
 * opens the call with OpenAI (the key never reaches the browser) and sends a
 * `voice` worker to join it. Covers the hosted chat and the widget, every
 * refusal (voice off, limits, a teammate in the chat, all workers busy, the
 * provider failing), hanging up, and the composer flag on both surfaces.
 */
beforeEach(function (): void {
    connectAiProviders(AiProvider::OpenAi);
    config([
        'chatvia.ai.voice' => 'auto',
        'chatvia.widget.always_allowed_origins' => [],
        'horizon.defaults.voice.maxProcesses' => 2,
    ]);
    Queue::fake();
    // OpenAI's answer to the offer; a test swaps it to make OpenAI fail.
    $this->openAiAnswer = fn () => Http::response('v=0 answer', 201, ['Location' => '/v1/realtime/calls/rtc_abc123']);
    Http::fake([
        'api.openai.com/v1/realtime/calls' => fn () => ($this->openAiAnswer)(),
        'api.openai.com/*' => Http::response('', 200),
    ]);

    $this->agent = Agent::factory()->create(['is_public' => true, 'system_instructions' => 'You are the Acme helper.']);
    $this->agent->setSetting('allow_voice_conversation', true, 'appearance');
    $this->agent->setSetting('sites', ['customer.example'], 'widget');
    $this->chat = '/chat/'.$this->agent->slug;
});

/** The multipart `session` field OpenAI received, decoded. */
function sentVoiceSession(): array
{
    $session = null;

    Http::assertSent(function (Request $request) use (&$session): bool {
        if (! str_ends_with($request->url(), '/realtime/calls')) {
            return false;
        }

        $part = collect($request->data())->firstWhere('name', 'session');
        $session = json_decode((string) ($part['contents'] ?? ''), true);

        return true;
    });

    return (array) $session;
}

it('opens a hosted-chat call in a new conversation and sends a worker to join it', function (): void {
    $response = $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])
        ->assertOk()
        ->assertJsonPath('sdp', 'v=0 answer')
        ->assertJsonStructure(['sdp', 'call_id', 'conversation_id', 'max_seconds', 'join_timeout']);

    $conversation = Conversation::query()->where('external_id', $response->json('conversation_id'))->firstOrFail();
    expect($conversation->agent_id)->toBe($this->agent->id);

    $call = app(VoiceCallStore::class)->find($response->json('call_id'));
    expect($call?->provider_call_id)->toBe('rtc_abc123')
        ->and($call?->conversation_id)->toBe($conversation->external_id)
        ->and($call?->max_seconds)->toBe(600);

    Queue::assertPushedOn('voice', JoinVoiceCallJob::class, fn (JoinVoiceCallJob $job): bool => $job->call->call_id === $response->json('call_id'));
});

it('starts the call silent, with the agent instructions, voice, transcription and knowledge search', function (): void {
    $this->agent->setSetting('voice', 'cedar', 'appearance');

    $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])->assertOk();

    $session = sentVoiceSession();

    expect($session['model'])->toBe('gpt-realtime-2.1')
        ->and($session['instructions'])->toContain('You are the Acme helper.')
        ->and($session['instructions'])->toContain('live voice call')
        ->and($session['audio']['input']['turn_detection'])->toBeNull()
        ->and($session['audio']['input']['transcription']['model'])->toBe('gpt-4o-mini-transcribe')
        ->and($session['audio']['output']['voice'])->toBe('cedar')
        ->and(collect($session['tools'])->pluck('name')->all())->toBe(['search_knowledge']);
});

it('sends OpenAI the offer with its line endings, though form input is trimmed', function (): void {
    $this->postJson($this->chat.'/voice', ['sdp' => "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\na=mid:0\r\n"])->assertOk();

    Http::assertSent(function (Request $request): bool {
        $sdp = collect($request->data())->firstWhere('name', 'sdp')['contents'] ?? null;

        return $sdp === "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\na=mid:0\r\n";
    });
});

it('sends the key and an anonymous caller id to OpenAI, never to the browser', function (): void {
    $response = $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])->assertOk();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/realtime/calls')
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && strlen($request->header('OpenAI-Safety-Identifier')[0] ?? '') === 64);

    expect(json_encode($response->json()))->not->toContain('test-key');
});

it('opens the call in the given conversation of the visitor', function (): void {
    $conversationId = $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])->json('conversation_id');

    $this->postJson($this->chat.'/voice/'.$conversationId, ['sdp' => 'v=0 offer'])
        ->assertOk()
        ->assertJsonPath('conversation_id', $conversationId);
});

it('refuses a conversation of another visitor with a 404', function (): void {
    $other = Conversation::factory()->create([
        'agent_id' => $this->agent->id,
        'chat_session_id' => Session::factory()->create(['agent_id' => $this->agent->id])->id,
    ]);

    $this->postJson($this->chat.'/voice/'.$other->external_id, ['sdp' => 'v=0 offer'])->assertNotFound();

    Http::assertNothingSent();
});

it('refuses when the agent has voice conversations off', function (): void {
    $this->agent->setSetting('allow_voice_conversation', false, 'appearance');

    $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])
        ->assertForbidden()
        ->assertJsonPath('error', 'voice_not_available');

    Http::assertNothingSent();
});

it('refuses when voice is not set up on this install', function (): void {
    config(['chatvia.ai.voice' => 'none']);

    $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])
        ->assertForbidden()
        ->assertJsonPath('error', 'voice_not_available');
});

it('refuses once the workspace reached its message limit', function (): void {
    $this->mock(MessageAllowance::class, fn ($mock) => $mock->shouldReceive('canSend')->andReturnFalse());

    $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])
        ->assertStatus(429)
        ->assertJsonPath('error', 'message_limit_reached');

    Http::assertNothingSent();
});

it('refuses while a teammate has taken over the conversation', function (): void {
    $conversationId = $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])->json('conversation_id');
    Conversation::query()->where('external_id', $conversationId)->update(['handoff_status' => HandoffStatus::Human]);

    $this->postJson($this->chat.'/voice/'.$conversationId, ['sdp' => 'v=0 offer'])
        ->assertStatus(409)
        ->assertJsonPath('error', 'handed_off');
});

it('refuses when every voice worker is on a call', function (): void {
    $slots = app(VoiceCallSlots::class);
    $slots->take('call-a', 600);
    $slots->take('call-b', 600);

    $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])
        ->assertStatus(503)
        ->assertJsonPath('error', 'voice_busy');

    Queue::assertNothingPushed();
});

it('frees the worker slot when OpenAI refuses the call', function (): void {
    config(['horizon.defaults.voice.maxProcesses' => 1]);
    $this->openAiAnswer = fn () => Http::response(['error' => ['message' => 'nope']], 500);

    $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])
        ->assertStatus(502)
        ->assertJsonPath('error', 'voice_failed');

    expect(app(VoiceCallSlots::class)->take('next-call', 60))->toBeTrue();
});

it('validates the offer', function (): void {
    $this->postJson($this->chat.'/voice', [])->assertUnprocessable()->assertJsonValidationErrors('sdp');
});

it('hangs up the visitor\'s own call and 404s another one', function (): void {
    $callId = $this->postJson($this->chat.'/voice', ['sdp' => 'v=0 offer'])->json('call_id');

    $this->deleteJson($this->chat.'/voice/calls/'.$callId)->assertNoContent();
    expect(app(VoiceCallStore::class)->isEnded($callId))->toBeTrue();
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/realtime/calls/rtc_abc123/hangup'));

    $this->deleteJson($this->chat.'/voice/calls/'.Str::uuid7())->assertNotFound();
});

it('shows the voice button on the hosted chat only while voice is on for the agent', function (): void {
    $this->get($this->chat)->assertInertia(fn (Assert $page): Assert => $page->where('voiceConversation', true));

    $this->agent->setSetting('allow_voice_conversation', false, 'appearance');

    $this->get($this->chat)->assertInertia(fn (Assert $page): Assert => $page->where('voiceConversation', false));
});

it('shows the dictation mic only while speech to text is set up', function (): void {
    config(['chatvia.ai.transcriber' => 'none']);
    $this->get($this->chat)->assertInertia(fn (Assert $page): Assert => $page->where('allowVoiceInput', false));

    config(['chatvia.ai.transcriber' => 'openai']);
    $this->get($this->chat)->assertInertia(fn (Assert $page): Assert => $page->where('allowVoiceInput', true));

    $this->agent->setSetting('allow_voice_input', false, 'appearance');
    $this->get($this->chat)->assertInertia(fn (Assert $page): Assert => $page->where('allowVoiceInput', false));
});

it('answers dictation with a message the composer can show when speech to text is off', function (): void {
    config(['chatvia.ai.transcriber' => 'none']);

    $this->post($this->chat.'/transcribe', ['audio' => UploadedFile::fake()->create('clip.webm', 10, 'audio/webm')], ['Accept' => 'application/json'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Voice input is not available right now.');
});

describe('widget', function (): void {
    /** @return array<string, string> */
    function voiceWidgetHeaders(Agent $agent, array $extra = []): array
    {
        return [WidgetHeaders::AGENT_ID => $agent->external_id, 'Origin' => 'https://customer.example', ...$extra];
    }

    beforeEach(function (): void {
        $this->boot = $this->withHeaders(voiceWidgetHeaders($this->agent))->postJson('/widget/sessions/init')->assertSuccessful()->json();
        $this->headers = voiceWidgetHeaders($this->agent, [
            WidgetHeaders::SESSION_ID => $this->boot['session_id'],
            WidgetHeaders::CONVERSATION_ID => $this->boot['conversation_id'],
        ]);
    });

    it('opens a call in the visitor\'s widget conversation', function (): void {
        $this->withHeaders($this->headers)
            ->postJson('/widget/voice', ['sdp' => 'v=0 offer'])
            ->assertOk()
            ->assertJsonPath('conversation_id', $this->boot['conversation_id'])
            ->assertJsonPath('sdp', 'v=0 answer');

        Queue::assertPushedOn('voice', JoinVoiceCallJob::class);
    });

    it('refuses a session of another agent', function (): void {
        $other = Agent::factory()->create(['is_public' => true]);
        $other->setSetting('sites', ['customer.example'], 'widget');
        $other->setSetting('allow_voice_conversation', true, 'appearance');

        $this->withHeaders([...$this->headers, WidgetHeaders::AGENT_ID => $other->external_id])
            ->postJson('/widget/voice', ['sdp' => 'v=0 offer'])
            ->assertForbidden();

        Http::assertNothingSent();
    });

    it('refuses when the widget surface is off', function (): void {
        $this->agent->setSetting('widget', false, 'surfaces');

        $this->withHeaders($this->headers)
            ->postJson('/widget/voice', ['sdp' => 'v=0 offer'])
            ->assertForbidden()
            ->assertJsonPath('error', 'voice_not_available');
    });

    it('hangs up the visitor\'s call', function (): void {
        $callId = $this->withHeaders($this->headers)->postJson('/widget/voice', ['sdp' => 'v=0 offer'])->json('call_id');

        $this->withHeaders($this->headers)->deleteJson('/widget/voice/calls/'.$callId)->assertNoContent();

        expect(app(VoiceCallStore::class)->isEnded($callId))->toBeTrue();
    });

    it('tells the widget to hide the dictation mic while speech to text is off', function (): void {
        config(['chatvia.ai.transcriber' => 'none']);
        $this->withHeaders($this->headers)->getJson('/widget/agents/settings')->assertOk()->assertJsonPath('allow_voice_input', false);

        config(['chatvia.ai.transcriber' => 'openai']);
        $this->withHeaders($this->headers)->getJson('/widget/agents/settings')->assertOk()->assertJsonPath('allow_voice_input', true);
    });

    it('tells the widget whether to show the voice button', function (): void {
        $this->withHeaders($this->headers)->getJson('/widget/agents/settings')->assertOk()->assertJsonPath('voice_conversation', true);

        config(['chatvia.ai.voice' => 'none']);

        $this->withHeaders($this->headers)->getJson('/widget/agents/settings')->assertOk()->assertJsonPath('voice_conversation', false);
    });
});
