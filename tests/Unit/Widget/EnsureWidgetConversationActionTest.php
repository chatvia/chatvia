<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Actions\EnsureWidgetConversationAction;
use Domain\Widget\Queries\ListWidgetConversationsQuery;
use Illuminate\Support\Str;

/**
 * The widget lists the visitor's conversations on boot and renders the newest
 * one, so a session without any gets one started. The list itself is
 * ListWidgetConversationsQuery (ListWidgetConversationsQueryTest).
 */
it('starts one widget conversation for a session that has none', function (): void {
    $agent = Agent::factory()->create();
    $sessionId = (string) Str::uuid();

    $started = app(EnsureWidgetConversationAction::class)->execute($agent, $sessionId);

    $conversations = app(ListWidgetConversationsQuery::class)->get($agent, $sessionId);

    expect($started)->toBeTrue()
        ->and($conversations)->toHaveCount(1)
        ->and($conversations[0]['title'])->toBe(Conversation::DEFAULT_TITLE)
        ->and(Conversation::query()->where('external_id', $conversations[0]['id'])->value('source'))
        ->toBe(ConversationSource::Widget);
});

it('leaves a session that already has a conversation alone', function (): void {
    $agent = Agent::factory()->create();
    $sessionId = (string) Str::uuid();
    $ensure = app(EnsureWidgetConversationAction::class);

    $ensure->execute($agent, $sessionId);

    expect($ensure->execute($agent, $sessionId))->toBeFalse()
        ->and(app(ListWidgetConversationsQuery::class)->get($agent, $sessionId))->toHaveCount(1);
});
