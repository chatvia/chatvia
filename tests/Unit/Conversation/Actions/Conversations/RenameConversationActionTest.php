<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\GenerateConversationTitleAction;
use Domain\Conversation\Actions\Conversations\RenameConversationAction;
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
 * A rename lands in the live list and in the database, so a rebuild of the
 * list from the database keeps the name.
 */
function renameScope(): ChatScopeData
{
    $scope = new ChatScopeData(Agent::factory()->create(), (string) Str::uuid());

    return $scope->forConversation(app(StartConversationAction::class)->execute($scope, ConversationSource::HostedChat)->external_id);
}

it('renames the conversation in the memory and the database, without quotes', function (): void {
    $scope = renameScope();

    app(RenameConversationAction::class)->execute($scope, '"Order status"');

    $listed = collect(app(ChatMemory::class)->withScope($scope)->getConversationsForCurrentSession())->firstWhere('id', $scope->conversationId);
    $stored = Conversation::query()->firstWhere('external_id', $scope->conversationId);

    expect($listed)->toMatchArray(['title' => 'Order status', 'is_visible' => true])
        ->and($stored->title)->toBe('Order status')
        ->and($stored->is_visible)->toBeTrue();
});

it('gives a hidden conversation the full retention window when a rename makes it visible', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $scope = renameScope();

    app(RenameConversationAction::class)->execute($scope, 'Order status');

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->expires_at->isSameDay(now()->addDays(90)))->toBeTrue();
});

it('keeps the horizon of a conversation that is already visible', function (): void {
    $scope = renameScope();
    $horizon = now()->addDays(5)->startOfSecond();
    Conversation::query()->where('external_id', $scope->conversationId)->update(['is_visible' => true, 'expires_at' => $horizon]);

    app(RenameConversationAction::class)->execute($scope, 'Order status');

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->expires_at->equalTo($horizon))->toBeTrue();
});

it('does not rename a conversation of another session', function (): void {
    $victim = renameScope();
    $attacker = renameScope();

    app(RenameConversationAction::class)->execute($attacker->forConversation((string) $victim->conversationId), 'Hijacked');

    expect(Conversation::query()->firstWhere('external_id', $victim->conversationId)->title)->toBe(Conversation::DEFAULT_TITLE);
});

it('titles a conversation from its first exchange', function (): void {
    $scope = renameScope();
    app(ChatMemory::class)->withScope($scope)->appendMessage(new MessageData(
        id: 'q1',
        role: MessageRole::User,
        content: 'Where is my order?',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
    ));

    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldReceive('generate')->once()->with('Where is my order?')->andReturn('Order tracking');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->title)->toBe('Order tracking');
});

it('keeps the default name when the model writes no title, so a later request can try again', function (): void {
    $scope = renameScope();
    app(ChatMemory::class)->withScope($scope)->appendMessage(new MessageData(
        id: 'q1',
        role: MessageRole::User,
        content: 'No.',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
    ));

    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldReceive('generate')->once()->andReturn('');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->title)->toBe(Conversation::DEFAULT_TITLE);
});

it('leaves a conversation without messages untitled', function (): void {
    $scope = renameScope();

    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldNotReceive('generate');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->title)->toBe(Conversation::DEFAULT_TITLE);
});

it('keeps a title the conversation has already, without another AI call', function (): void {
    $scope = renameScope();
    app(ChatMemory::class)->withScope($scope)->appendMessage(new MessageData(
        id: 'q1',
        role: MessageRole::User,
        content: 'Where is my order?',
        type: MessageData::TYPE_TEXT,
        timestamp: now()->timestamp,
    ));
    // Named by the browser right after the first reply, before the queued fallback ran.
    Conversation::query()->firstWhere('external_id', $scope->conversationId)->update(['title' => 'Order status']);

    $titles = Mockery::mock(TitleGenerator::class);
    $titles->shouldNotReceive('generate');
    app()->instance(TitleGenerator::class, $titles);

    app(GenerateConversationTitleAction::class)->execute($scope);

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->title)->toBe('Order status');
});
