<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * The widget looks a conversation up by ids the browser sends. It must only
 * match when the conversation belongs to that session, the session to that
 * agent and, on a protected agent, to the signed-in chatter — and never once
 * the visitor cleared the thread. Anything else is null, never another
 * visitor's or another tenant's conversation.
 */
it('finds the conversation of the given session and agent', function (): void {
    $conversation = Conversation::factory()->create();
    $session = $conversation->session;

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $session->external_id,
        $session->agent,
    );

    expect($found?->id)->toBe($conversation->id);
});

it('returns null when the session belongs to another agent', function (): void {
    $conversation = Conversation::factory()->create();
    $otherAgent = Agent::factory()->create();

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $conversation->session->external_id,
        $otherAgent,
    );

    expect($found)->toBeNull();
});

it('returns null when the conversation belongs to another session', function (): void {
    $conversation = Conversation::factory()->create();
    $otherSession = Session::factory()->create(['agent_id' => $conversation->session->agent_id]);

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $otherSession->external_id,
        $conversation->session->agent,
    );

    expect($found)->toBeNull();
});

it('returns null for a thread the visitor cleared, like the widget\'s other lookups', function (): void {
    $conversation = Conversation::factory()->create();
    $conversation->update(['cleared_at' => now()]);
    $agent = $conversation->session->agent;

    $found = app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $conversation->session->external_id,
        $agent,
    );

    expect($found)->toBeNull();
});

it('on a protected agent, only finds the signed-in chatter\'s own conversation', function (): void {
    $agent = Agent::factory()->create();
    $owner = Chatter::factory()->ofWorkspace($agent->workspace)->create();
    $other = Chatter::factory()->ofWorkspace($agent->workspace)->create();
    $conversation = Conversation::factory()->forAgent($agent)->ofChatter($owner)->create();
    $session = $conversation->session;

    $find = fn (ChatViewerData $viewer): ?Conversation => app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $session->external_id,
        $session->agent,
        $viewer,
    );

    // Another chatter replaying the owner's session and conversation ids, and
    // a staff account whose id happens to equal the owner's.
    expect($find(ChatViewerData::chatter($other->id)))->toBeNull()
        ->and($find(ChatViewerData::staff($owner->id)))->toBeNull()
        ->and($find(ChatViewerData::chatter($owner->id))?->id)->toBe($conversation->id);
});

it('on a protected agent, finds a staff member\'s conversation for that staff member only', function (): void {
    $staff = User::factory()->create();
    $conversation = Conversation::factory()->inSession(Session::factory()->forUser($staff)->create())->create();
    $session = $conversation->session;

    $find = fn (ChatViewerData $viewer): ?Conversation => app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $session->external_id,
        $session->agent,
        $viewer,
    );

    expect($find(ChatViewerData::staff($staff->id))?->id)->toBe($conversation->id)
        ->and($find(ChatViewerData::chatter($staff->id)))->toBeNull();
});

it('returns null for ids that are not UUIDs instead of querying', function (string $conversationId, string $sessionId): void {
    expect(app(FindWidgetConversationAction::class)->execute($conversationId, $sessionId, Agent::factory()->create()))->toBeNull();
})->with([
    'bad conversation id' => ['not-a-uuid', (string) Str::uuid7()],
    'bad session id' => [(string) Str::uuid7(), "1' OR '1'='1"],
]);
