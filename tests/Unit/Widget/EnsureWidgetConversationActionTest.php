<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Actions\EnsureWidgetConversationAction;
use Domain\Widget\Queries\ListWidgetConversationsQuery;
use Illuminate\Support\Str;

/**
 * The widget lists the visitor's conversations on boot and renders the newest
 * one, so a session without any gets one started (write side), while the
 * list itself is a plain read (ListWidgetConversationsQuery).
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

it('lists nothing and writes nothing for a session it has never seen', function (): void {
    $agent = Agent::factory()->create();
    $before = Conversation::query()->count();

    expect(app(ListWidgetConversationsQuery::class)->get($agent, (string) Str::uuid()))->toBe([])
        ->and(Conversation::query()->count())->toBe($before);
});
