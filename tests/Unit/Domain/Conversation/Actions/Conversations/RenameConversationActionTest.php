<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\RenameConversationAction;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Str;

/**
 * A rename lands in the live list and in the database, so a rebuild of the
 * list from the database keeps the name.
 */
function rcaScope(): ChatScopeData
{
    $scope = new ChatScopeData(Agent::factory()->create(), (string) Str::uuid());

    return $scope->forConversation(app(StartConversationAction::class)->execute($scope, ConversationSource::HostedChat)->external_id);
}

it('renames the conversation in the memory and the database, without quotes', function (): void {
    $scope = rcaScope();

    app(RenameConversationAction::class)->execute($scope, '"Order status"');

    $listed = collect(app(ChatMemory::class)->withScope($scope)->getConversationsForCurrentSession())->firstWhere('id', $scope->conversationId);
    $stored = Conversation::query()->firstWhere('external_id', $scope->conversationId);

    expect($listed)->toMatchArray(['title' => 'Order status', 'is_visible' => true])
        ->and($stored->title)->toBe('Order status')
        ->and($stored->is_visible)->toBeTrue();
});

it('gives a hidden conversation the full retention window when a rename makes it visible', function (): void {
    $this->freezeSecond();
    config(['chatvia.retention.conversations_days' => 90]);
    $scope = rcaScope();

    app(RenameConversationAction::class)->execute($scope, 'Order status');

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->expires_at->isSameDay(now()->addDays(90)))->toBeTrue();
});

it('keeps the horizon of a conversation that is already visible', function (): void {
    $scope = rcaScope();
    $horizon = now()->addDays(5)->startOfSecond();
    Conversation::query()->where('external_id', $scope->conversationId)->update(['is_visible' => true, 'expires_at' => $horizon]);

    app(RenameConversationAction::class)->execute($scope, 'Order status');

    expect(Conversation::query()->firstWhere('external_id', $scope->conversationId)->expires_at->equalTo($horizon))->toBeTrue();
});

it('does not rename a conversation of another session', function (): void {
    $victim = rcaScope();
    $attacker = rcaScope();

    app(RenameConversationAction::class)->execute($attacker->forConversation((string) $victim->conversationId), 'Hijacked');

    expect(Conversation::query()->firstWhere('external_id', $victim->conversationId)->title)->toBe(Conversation::DEFAULT_TITLE);
});
