<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A turn is refused before any model work when the agent's workspace or the
 * signed-in chatter has used this month's message limit. A workspace and a
 * chatter that share an integer id never trip each other's limit.
 */
beforeEach(function (): void {
    Bus::fake();
});

function limitedStreamCodes(Agent $agent, ?int $chatterId = null): array
{
    $events = iterator_to_array(app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $agent->llm, message: 'Hello?'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        chatterId: $chatterId,
    )), preserve_keys: false);

    return collect($events)
        ->filter(fn (StreamEvent $event): bool => $event->type === EventType::Error)
        ->map(fn (StreamEvent $event): string => $event->data['code'])
        ->values()
        ->all();
}

/**
 * @param  array{user_id?: int, chatter_id?: int}  $owner
 */
function reachedLimit(array $owner): void
{
    MessageLimit::create([
        ...$owner,
        'monthly_limit' => 3,
        'messages_used' => 3,
        'period_starts_at' => now()->startOfMonth(),
    ]);
}

/**
 * The owner of a new workspace whose id is free in `chatters` too, so a test
 * can give a chatter the same id. Taking the next value of both sequences
 * means neither table can hold it.
 */
function mlOwnerOfWorkspaceWithChatterFreeId(): User
{
    $id = (int) DB::scalar("select greatest(nextval(pg_get_serial_sequence('workspaces', 'id')), nextval(pg_get_serial_sequence('chatters', 'id')))");
    $workspace = Workspace::factory()->create(['id' => $id]);
    $owner = User::factory()
        ->afterMaking(fn (User $user): User => $user->setRelation('factoryWorkspace', $workspace))
        ->create();
    $workspace->members()->updateExistingPivot($owner->id, ['role' => WorkspaceRole::Owner->value]);

    return $owner->fresh();
}

/**
 * The gate that runs right after both message limits refuses every turn, so
 * a turn that passed the limits ends on a rate-limit error instead of model
 * work.
 */
function refuseTurnsPastTheMessageLimits(): void
{
    test()->mock(AgentChatRateLimiter::class)->shouldReceive('visitorExceeded')->andReturnFalse()->getMock()->shouldReceive('exceeded')->andReturnTrue();
}

it('refuses the turn when the workspace reached its monthly limit', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    reachedLimit(['workspace_id' => $agent->workspace_id]);

    expect(limitedStreamCodes($agent))->toBe([ErrorCode::MessageLimitReached->value]);
});

it('refuses the turn when the signed-in chatter reached their monthly limit', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    reachedLimit(['chatter_id' => $chatter->id]);

    expect(limitedStreamCodes($agent, $chatter->id))->toBe([ErrorCode::ChatterMessageLimitReached->value]);
});

it('does not refuse the workspace over a chatter limit with the same id', function (): void {
    $owner = mlOwnerOfWorkspaceWithChatterFreeId();
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id, 'llm_id' => Llm::factory()->create()->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['id' => $agent->workspace_id]);
    reachedLimit(['chatter_id' => $chatter->id]);
    refuseTurnsPastTheMessageLimits();

    // An anonymous visitor's turn only checks the workspace's limit.
    expect(limitedStreamCodes($agent))->toBe([ErrorCode::RateLimited->value]);
});

it('does not refuse a chatter over the limit of a workspace with the same id', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $otherWorkspace = mlOwnerOfWorkspaceWithChatterFreeId()->workspace();
    $chatter = Chatter::factory()->grantedTo($agent)->create(['id' => $otherWorkspace->id]);
    reachedLimit(['workspace_id' => $otherWorkspace->id]);
    refuseTurnsPastTheMessageLimits();

    expect(limitedStreamCodes($agent, $chatter->id))->toBe([ErrorCode::RateLimited->value]);
});
