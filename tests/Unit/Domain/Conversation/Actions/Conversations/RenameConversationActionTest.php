<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\RenameConversationAction;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Str;

/**
 * A hosted-chat rename only reaches a conversation of the visitor's own
 * session, never one whose id another visitor sent.
 */
function rcaScope(): ChatScopeData
{
    $scope = new ChatScopeData(Agent::factory()->create(), (string) Str::uuid7());

    return $scope->forConversation(app(StartConversationAction::class)->execute($scope, ConversationSource::HostedChat)->external_id);
}

it('does not rename a conversation of another session', function (): void {
    $victim = rcaScope();
    $attacker = rcaScope();

    app(RenameConversationAction::class)->execute($attacker->forConversation((string) $victim->conversationId), 'Hijacked');

    expect(Conversation::query()->firstWhere('external_id', $victim->conversationId)->title)->toBe(Conversation::DEFAULT_TITLE);
});
