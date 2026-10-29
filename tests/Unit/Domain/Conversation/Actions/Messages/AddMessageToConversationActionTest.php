<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Jobs\StoreMessageJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * A chatter's message is counted against their monthly limit on the turn
 * itself, so the count never depends on the agent's chat log being on. An
 * anonymous visitor's message and an assistant reply never count against a
 * chatter's limit.
 */
beforeEach(function (): void {
    Bus::fake();
});

function addMessageScope(Agent $agent): ChatScopeData
{
    return new ChatScopeData($agent, (string) Str::uuid7(), (string) Str::uuid7());
}

it('counts a chatter message against their monthly limit even with the chat log off', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chatlog', value: false, group: 'analytics');
    $agent = $agent->fresh();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    app(SetMessageLimitAction::class)->execute($chatter, 5);
    app(SetMessageLimitAction::class)->execute($agent->workspace, 5);

    $scope = addMessageScope($agent);

    app(AddMessageToConversationAction::class)->execute($scope, new UserTurnData(
        external_session_id: $scope->sessionId,
        external_conversation_id: (string) $scope->conversationId,
        external_message_id: 'user-3',
        llm_id: 7,
        message: 'Hi',
        sent_at: now(),
        chatter_id: $chatter->id,
    ));

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->value('messages_used'))->toBe(1)
        ->and(MessageLimit::query()->where('workspace_id', $agent->workspace_id)->value('messages_used'))->toBe(0);
    Bus::assertNotDispatched(StoreMessageJob::class);
});

it('counts no anonymous message and no assistant reply against a chatter limit', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    app(SetMessageLimitAction::class)->execute($chatter, 5);

    $scope = addMessageScope($agent);

    app(AddMessageToConversationAction::class)->execute($scope, new UserTurnData(
        external_session_id: $scope->sessionId,
        external_conversation_id: (string) $scope->conversationId,
        external_message_id: 'user-4',
        llm_id: 7,
        message: 'Hi',
        sent_at: now(),
    ));
    app(AddMessageToConversationAction::class)->execute($scope, new AssistantTurnData(
        external_session_id: $scope->sessionId,
        external_conversation_id: (string) $scope->conversationId,
        external_message_id: 'reply-4',
        message: 'Hello!',
        llm_id: 7,
        sent_at: now(),
        has_llm_found_answer: true,
    ));

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->value('messages_used'))->toBe(0);
});
