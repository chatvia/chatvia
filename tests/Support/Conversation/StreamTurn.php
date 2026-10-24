<?php

declare(strict_types=1);

namespace Tests\Support\Conversation;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Illuminate\Support\Str;

/**
 * Runs one web turn through {@see ChatStreamService} the way the hosted chat
 * and the widget do, and reads its events back. A test of a per-turn gate can
 * {@see stopAtTheRateLimit()} so a turn that passes its gate ends on the
 * agent's per-minute cap instead of calling a model.
 */
final class StreamTurn
{
    /**
     * One turn of the agent, in a new visitor session and conversation unless
     * the request names them. The agent needs a model (`llm_id`).
     *
     * @param  array<string, mixed>  $request  more named StreamRequestData arguments
     * @return list<StreamEvent>
     */
    public static function run(Agent $agent, string $message = 'Hello?', array $request = []): array
    {
        $data = new StreamRequestData(...[
            'agent' => $agent,
            'message' => new StreamRequestMessageData(model: $agent->llm, message: $message),
            'externalSessionId' => (string) Str::uuid(),
            'conversationId' => (string) Str::uuid(),
            ...$request,
        ]);

        return iterator_to_array(app(ChatStreamService::class)->stream($data), preserve_keys: false);
    }

    /**
     * The codes of the turn's error events, in order.
     *
     * @param  list<StreamEvent>  $events
     * @return list<string>
     */
    public static function errorCodes(array $events): array
    {
        return array_column(array_column(self::ofType($events, EventType::Error), 'data'), 'code');
    }

    /**
     * @param  list<StreamEvent>  $events
     * @return list<StreamEvent>
     */
    public static function ofType(array $events, EventType $type): array
    {
        return array_values(array_filter($events, static fn (StreamEvent $event): bool => $event->type === $type));
    }

    /**
     * Use up the agent's per-minute cap, the gate right after the message
     * limits, so its next turn ends there with `rate_limited`.
     */
    public static function stopAtTheRateLimit(Agent $agent): void
    {
        $agent->setSetting('chat_rate_limit_per_minute', 1, 'security');

        $limiter = app(AgentChatRateLimiter::class);
        $limiter->forget($agent);
        $limiter->exceeded($agent);
    }
}
