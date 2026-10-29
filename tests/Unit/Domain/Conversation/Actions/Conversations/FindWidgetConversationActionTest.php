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
 * agent and, on a protected agent, to the signed-in chatter or staff member,
 * and never once the visitor cleared the thread. Anything else is null,
 * never another visitor's or another tenant's conversation.
 */
it('finds only a conversation of the given session and agent that the visitor has not cleared', function (): void {
    $conversation = Conversation::factory()->create();
    $session = $conversation->session;
    $otherSession = Session::factory()->create(['agent_id' => $session->agent_id]);
    $cleared = Conversation::factory()->inSession($session)->create(['cleared_at' => now()]);
    $find = fn (string $conversationId, string $sessionId, Agent $agent): ?Conversation => app(FindWidgetConversationAction::class)
        ->execute($conversationId, $sessionId, $agent);

    expect($find($conversation->external_id, $session->external_id, $session->agent)?->id)->toBe($conversation->id)
        ->and($find($conversation->external_id, $session->external_id, Agent::factory()->create()))->toBeNull()
        ->and($find($conversation->external_id, $otherSession->external_id, $session->agent))->toBeNull()
        ->and($find($cleared->external_id, $session->external_id, $session->agent))->toBeNull();
});

it('on a protected agent, finds a conversation only for the chatter or staff member who owns it', function (): void {
    $agent = Agent::factory()->create();
    $owner = Chatter::factory()->ofWorkspace($agent->workspace)->create();
    $other = Chatter::factory()->ofWorkspace($agent->workspace)->create();
    $chatterConversation = Conversation::factory()->forAgent($agent)->ofChatter($owner)->create();
    $staff = User::factory()->create();
    $staffConversation = Conversation::factory()->inSession(Session::factory()->forUser($staff)->create())->create();
    $find = fn (Conversation $conversation, ChatViewerData $viewer): ?Conversation => app(FindWidgetConversationAction::class)->execute(
        $conversation->external_id,
        $conversation->session->external_id,
        $conversation->session->agent,
        $viewer,
    );

    expect($find($chatterConversation, ChatViewerData::chatter($other->id)))->toBeNull()
        ->and($find($chatterConversation, ChatViewerData::staff($owner->id)))->toBeNull()
        ->and($find($chatterConversation, ChatViewerData::chatter($owner->id))?->id)->toBe($chatterConversation->id)
        ->and($find($staffConversation, ChatViewerData::staff($staff->id))?->id)->toBe($staffConversation->id)
        ->and($find($staffConversation, ChatViewerData::chatter($staff->id)))->toBeNull();
});

it('returns null for ids that are not UUIDs instead of querying', function (string $conversationId, string $sessionId): void {
    expect(app(FindWidgetConversationAction::class)->execute($conversationId, $sessionId, Agent::factory()->create()))->toBeNull();
})->with([
    'bad conversation id' => ['not-a-uuid', (string) Str::uuid7()],
    'bad session id' => [(string) Str::uuid7(), "1' OR '1'='1"],
]);
