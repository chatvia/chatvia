<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Support\Str;

/**
 * The widget looks a conversation up by ids the browser sends. It must only
 * match when the conversation belongs to that session, the session to that
 * agent and, on a protected agent, to the signed-in chatter — and never once
 * the visitor cleared the thread. Anything else is null, never another
 * visitor's or another tenant's conversation.
 */
function sessionScopedConversation(array $sessionAttributes = []): Conversation
{
    $session = Session::factory()->create($sessionAttributes);

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

it('returns null for a thread the visitor cleared, like the widget\'s other lookups', function (): void {
    $conversation = sessionScopedConversation();
    $conversation->update(['cleared_at' => now()]);
    $agent = $conversation->session->agent;

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $conversation->session->external_id,
        $agent,
    );

    expect($found)->toBeNull()
        ->and(app(WidgetVerification::class)->verifyConversationId($conversation->external_id, $agent))->toBeNull();
});

it('on a protected agent, only finds the signed-in chatter\'s own conversation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = sessionScopedConversation(['user_id' => $owner->id]);
    $session = $conversation->session;

    $find = fn (?User $chatter): ?Conversation => app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $session->external_id,
        $session->agent,
        $chatter,
    );

    // Another chatter replaying the owner's session and conversation ids.
    expect($find($other))->toBeNull()
        ->and($find($owner)?->id)->toBe($conversation->id)
        ->and(app(WidgetVerification::class)->verifyConversationId($conversation->external_id, $session->agent, $other))->toBeNull()
        ->and(app(WidgetVerification::class)->verifyConversationId($conversation->external_id, $session->agent, $owner)?->id)->toBe($conversation->id);
});

it('returns null for ids that are not UUIDs instead of querying', function (string $conversationId, string $sessionId): void {
    expect(app(FindWidgetConversationAction::class)->execute($conversationId, $sessionId, Agent::factory()->create()))->toBeNull();
})->with([
    'bad conversation id' => ['not-a-uuid', (string) Str::uuid()],
    'bad session id' => [(string) Str::uuid(), "1' OR '1'='1"],
]);
