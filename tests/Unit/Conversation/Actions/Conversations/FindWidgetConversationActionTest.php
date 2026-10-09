<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * The widget looks a conversation up by ids the browser sends. It must only
 * match when the conversation belongs to that session AND (when an agent is
 * given) the session belongs to that agent; anything else is null, never
 * another visitor's or another tenant's conversation.
 */
function sessionScopedConversation(): Conversation
{
    $session = Session::factory()->create();

    return Conversation::factory()->create(['chat_session_id' => $session->id]);
}

it('finds the conversation of the given session and agent', function (): void {
    $conversation = sessionScopedConversation();
    $session = $conversation->session;

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $session->external_id,
        $session->agent,
    );

    expect($found?->id)->toBe($conversation->id);
});

it('finds the conversation by session alone when no agent is given', function (): void {
    $conversation = sessionScopedConversation();

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $conversation->session->external_id,
    );

    expect($found?->id)->toBe($conversation->id);
});

it('returns null when the session belongs to another agent', function (): void {
    $conversation = sessionScopedConversation();
    $otherAgent = Agent::factory()->create();

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $conversation->session->external_id,
        $otherAgent,
    );

    expect($found)->toBeNull();
});

it('returns null when the conversation belongs to another session', function (): void {
    $conversation = sessionScopedConversation();
    $otherSession = Session::factory()->create(['agent_id' => $conversation->session->agent_id]);

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $otherSession->external_id,
        $conversation->session->agent,
    );

    expect($found)->toBeNull();
});

it('returns null for ids that are not UUIDs instead of querying', function (string $conversationId, string $sessionId): void {
    expect(app(FindWidgetConversationAction::class)->execute($conversationId, $sessionId))->toBeNull();
})->with([
    'bad conversation id' => ['not-a-uuid', (string) Str::uuid()],
    'bad session id' => [(string) Str::uuid(), "1' OR '1'='1"],
]);
