<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\GenerateConversationTitleAction;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Llm\Operations\Query\TitleGenerator;
use Illuminate\Support\Str;

/**
 * A conversation is titled from its first exchange by the title model, once:
 * a conversation that has a title already, or no message yet, makes no AI
 * call, and an empty answer keeps the default name so a later request can
 * try again.
 */
function gctaScope(?string $firstMessage = null): ChatScopeData
{
    $scope = new ChatScopeData(Agent::factory()->create(), (string) Str::uuid());
    $scope = $scope->forConversation(app(StartConversationAction::class)->execute($scope, ConversationSource::HostedChat)->external_id);

    if ($firstMessage !== null) {
        app(ChatMemory::class)->withScope($scope)->appendMessage(new MessageData(
            id: 'q1',
            role: MessageRole::User,
            content: $firstMessage,
            type: MessageData::TYPE_TEXT,
            timestamp: now()->timestamp,
        ));
    }

    return $scope;
}

function gctaTitle(ChatScopeData $scope): ?string
{
    return Conversation::query()->firstWhere('external_id', $scope->conversationId)->title;
}

it('titles a conversation from its first exchange', function (): void {
    $scope = gctaScope('Where is my order?');
    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldReceive('generate')->once()->with('Where is my order?')->andReturn('Order tracking');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(gctaTitle($scope))->toBe('Order tracking');
});

it('keeps the default name when the model writes no title, so a later request can try again', function (): void {
    $scope = gctaScope('No.');
    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldReceive('generate')->once()->andReturn('');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(gctaTitle($scope))->toBe(Conversation::DEFAULT_TITLE);
});

it('leaves a conversation without messages untitled', function (): void {
    $scope = gctaScope();
    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldNotReceive('generate');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(gctaTitle($scope))->toBe(Conversation::DEFAULT_TITLE);
});

it('keeps a title the conversation has already, without another AI call', function (): void {
    $scope = gctaScope('Where is my order?');
    // Named by the browser right after the first reply, before the queued fallback ran.
    Conversation::query()->firstWhere('external_id', $scope->conversationId)->update(['title' => 'Order status']);
    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldNotReceive('generate');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(gctaTitle($scope))->toBe('Order status');
});
