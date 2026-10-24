<?php

declare(strict_types=1);

namespace Tests\Support\Conversation\Voice;

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Knowledge\Ingestion\Models\Chunk;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Voice calls for tests: the call a hosted-chat visitor started, what it left
 * in the conversation, an agent with knowledge to search, and OpenAI's
 * realtime endpoints faked. The faked endpoint opens every call as
 * `rtc_abc123` until {@see refuseNewCalls()} makes it fail.
 */
final class VoiceCalls
{
    private bool $refusesNewCalls = false;

    /** A ten-minute hosted-chat call of the agent, started now. */
    public static function data(Agent $agent, string $sessionId = 'session-1', string $conversationId = 'conversation-1', string $providerCallId = 'rtc_test'): VoiceCallData
    {
        return new VoiceCallData(
            call_id: Str::uuid7()->toString(),
            provider_call_id: $providerCallId,
            agent_id: $agent->id,
            workspace_id: (int) $agent->workspace_id,
            surface: ConversationSource::HostedChat,
            session_id: $sessionId,
            conversation_id: $conversationId,
            model: 'gpt-realtime-2.1',
            voice: 'marin',
            started_at: now()->getTimestamp(),
            max_seconds: 600,
        );
    }

    /** The call of the conversation's visitor, kept in the call store as a started call is. */
    public static function started(Conversation $conversation, string $providerCallId = 'rtc_test'): VoiceCallData
    {
        $call = self::data($conversation->agent, $conversation->session->external_id, $conversation->external_id, $providerCallId);
        app(VoiceCallStore::class)->put($call);

        return $call;
    }

    /**
     * The call's conversation as the chat shows it.
     *
     * @return list<MessageData>
     */
    public static function messages(VoiceCallData $call, Agent $agent): array
    {
        return app(ChatMemory::class)
            ->withScope(new ChatScopeData($agent, $call->session_id, $call->conversation_id))
            ->cachedMessages();
    }

    /** Give the agent a file with one chunk, so its calls search knowledge. */
    public static function giveAgentKnowledge(Agent $agent): void
    {
        $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
        $agent->files()->attach($file->id);
        Chunk::factory()->forSource($file)->create(['chunk' => 'We open at nine.']);
    }

    /** Fake OpenAI's realtime API: a new call opens as `rtc_abc123`, and hang-ups succeed. */
    public static function fakeOpenAi(): void
    {
        app()->instance(self::class, new self);

        Http::fake([
            'api.openai.com/v1/realtime/calls' => fn (): PromiseInterface => app(self::class)->refusesNewCalls
                ? Http::response(['error' => ['message' => 'down']], 500)
                : Http::response('v=0 answer', 201, ['Location' => '/v1/realtime/calls/rtc_abc123']),
            'api.openai.com/*' => Http::response('', 200),
        ]);
    }

    /** Make the faked OpenAI refuse every new call from now on. */
    public static function refuseNewCalls(): void
    {
        app(self::class)->refusesNewCalls = true;
    }
}
