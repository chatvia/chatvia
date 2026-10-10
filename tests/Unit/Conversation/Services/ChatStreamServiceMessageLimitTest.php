<?php

use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * A turn is refused before any model work when the agent's workspace or the
 * signed-in chatter has used this month's message limit.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Bus::fake();
});

function limitedStreamCodes(Agent $agent, ?int $chatterUserId = null): array
{
    $events = iterator_to_array(app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $agent->llm, message: 'Hello?'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        chatterUserId: $chatterUserId,
    )), preserve_keys: false);

    return collect($events)
        ->filter(fn ($event): bool => $event->type === EventType::Error)
        ->map(fn ($event): string => $event->data['code'])
        ->values()
        ->all();
}

function reachedLimit(int $userId): void
{
    MessageLimit::create([
        'user_id' => $userId,
        'monthly_limit' => 3,
        'messages_used' => 3,
        'period_starts_at' => now()->startOfMonth(),
    ]);
}

it('refuses the turn when the workspace reached its monthly limit', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    reachedLimit($agent->user_id);

    expect(limitedStreamCodes($agent))->toBe([ErrorCode::MessageLimitReached->value]);
});

it('refuses the turn when the signed-in chatter reached their monthly limit', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $chatter = User::factory()->chatter($agent->user)->create();
    reachedLimit($chatter->id);

    expect(limitedStreamCodes($agent, $chatter->id))->toBe([ErrorCode::ChatterMessageLimitReached->value]);
});
