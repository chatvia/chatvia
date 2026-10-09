<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Messages\AddMessageToConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\AssistantTurnData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * A message lands in the live memory at once and in the chat log through the
 * queue — unless the agent's chat log is switched off.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Bus::fake();
});

function addMessageScope(Agent $agent): ChatScopeData
{
    return new ChatScopeData($agent, (string) Str::uuid(), (string) Str::uuid());
}

it('adds a visitor message to the live memory and queues the chat-log copy', function (): void {
    $agent = Agent::factory()->create();
    $scope = addMessageScope($agent);

    $id = app(AddMessageToConversationAction::class)->execute($scope, new UserTurnData(
        external_session_id: $scope->sessionId,
        external_conversation_id: (string) $scope->conversationId,
        external_message_id: 'user-1',
        model: 'gpt-test',
        message: 'Hi',
        sent_at: now(),
        message_for_llm: 'Hi [context]',
        language: 'en',
    ));

    $history = app(ChatMemory::class)->withScope($scope)->cachedMessages();

    expect($id)->toBe('user-1')
        ->and($history[0])->toMatchArray([
            'id' => 'user-1',
            'role' => 'user',
            'content' => 'Hi',
            'type' => 'text',
            'model' => 'gpt-test',
            'language' => 'en',
            'message_for_llm' => 'Hi [context]',
            'has_llm_found_answer' => null,
            'metadata' => null,
        ]);

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload->external_message_id === 'user-1');
});

it('keeps an answer’s artifacts and tool names in the live memory', function (): void {
    $agent = Agent::factory()->create();
    $scope = addMessageScope($agent);

    app(AddMessageToConversationAction::class)->execute($scope, new AssistantTurnData(
        external_session_id: $scope->sessionId,
        external_conversation_id: (string) $scope->conversationId,
        external_message_id: 'reply-1',
        message: 'Here is your chart.',
        model: 'gpt-test',
        sent_at: now(),
        has_llm_found_answer: false,
        artifacts: [['kind' => 'echarts_option', 'data' => []]],
        tool_calls: ['mcp__charts__render'],
    ));

    $reply = app(ChatMemory::class)->withScope($scope)->cachedMessages()[0];

    expect($reply['role'])->toBe('assistant')
        ->and($reply['has_llm_found_answer'])->toBeFalse()
        ->and($reply['message_for_llm'])->toBeNull()
        ->and($reply['metadata'])->toBe([
            'artifacts' => [['kind' => 'echarts_option', 'data' => []]],
            'tool_calls' => ['mcp__charts__render'],
        ]);
});

it('skips the chat-log copy when the agent’s chat log is off', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting(key: 'chatlog', value: false, group: 'analytics');
    $agent = $agent->fresh();

    expect($agent->chatlogEnabled())->toBeFalse();

    $scope = addMessageScope($agent);

    app(AddMessageToConversationAction::class)->execute($scope, new UserTurnData(
        external_session_id: $scope->sessionId,
        external_conversation_id: (string) $scope->conversationId,
        external_message_id: 'user-2',
        model: 'gpt-test',
        message: 'Hi',
        sent_at: now(),
    ));

    expect(app(ChatMemory::class)->withScope($scope)->cachedMessages())->toHaveCount(1);
    Bus::assertNotDispatched(StoreMessageJob::class);
});
