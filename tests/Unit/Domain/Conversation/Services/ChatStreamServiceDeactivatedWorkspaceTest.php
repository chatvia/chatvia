<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * The engine itself refuses a deactivated workspace's turns, before any
 * other step, for any caller that does not pass the hosted chat or widget
 * middleware. The turn ends with one non-retryable error and stores nothing.
 */
beforeEach(function (): void {
    Bus::fake();
});

/** @return list<array{code: string, message: string, retryable: bool}> */
function deactivatedStreamErrors(Agent $agent, ?ChatScopeData $scope = null): array
{
    $events = iterator_to_array(app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $agent->llm, message: 'Hello?'),
        externalSessionId: $scope->sessionId ?? (string) Str::uuid(),
        conversationId: $scope->conversationId ?? (string) Str::uuid(),
        saveUserMessageImmediately: true,
    )), preserve_keys: false);

    return collect($events)
        ->filter(fn (StreamEvent $event): bool => $event->type === EventType::Error)
        ->map(fn (StreamEvent $event): array => [
            'code' => $event->data['code'],
            'message' => $event->data['message'],
            'retryable' => $event->data['retryable'],
        ])
        ->values()
        ->all();
}

it('refuses the turn of a deactivated workspace', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    deactivateWorkspaceOf($agent);
    $scope = new ChatScopeData($agent, (string) Str::uuid(), (string) Str::uuid());

    expect(deactivatedStreamErrors($agent, $scope))->toBe([[
        'code' => ErrorCode::Internal->value,
        'message' => 'Sorry — this assistant is unavailable right now. Please try again later.',
        'retryable' => false,
    ]])->and(app(ChatMemory::class)->withScope($scope)->getMessagesForConversation())->toBe([]);

    Bus::assertNotDispatched(StoreMessageJob::class);
});

it('lets the turn through again once the workspace is reactivated', function (): void {
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    deactivateWorkspaceOf($agent);
    reactivateWorkspaceOf($agent);
    // The next gate refuses every turn, so a turn past this one ends there.
    $this->mock(AgentChatRateLimiter::class)->shouldReceive('visitorExceeded')->andReturnFalse()->getMock()->shouldReceive('exceeded')->andReturnTrue();

    expect(array_column(deactivatedStreamErrors($agent), 'code'))->toBe([ErrorCode::RateLimited->value]);
});
