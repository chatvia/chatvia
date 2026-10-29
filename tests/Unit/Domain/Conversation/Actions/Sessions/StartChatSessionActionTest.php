<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatSessions;

/**
 * Starting a visitor session: a guest's lapses after a few days, a signed-in
 * viewer's stays. A chatter's session is linked by `chatter_id`, a staff
 * member's by `user_id`, so equal ids from the two tables never share one.
 */
it('starts a guest session that lapses after a few days', function (): void {
    $agent = Agent::factory()->create();

    $sessionId = app(StartChatSessionAction::class)->execute($agent, null);
    $row = ChatSession::query()->where('external_id', $sessionId)->firstOrFail();

    expect($row->user_id)->toBeNull()
        ->and($row->chatter_id)->toBeNull()
        ->and(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeTrue();

    $this->travelTo(now()->addDays(StartChatSessionAction::GUEST_SESSION_TTL_DAYS)->addMinute());

    expect(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeFalse();
});

it('keeps the long-lived sessions of a chatter and a staff account with the same id apart', function (): void {
    $agent = Agent::factory()->create();
    $id = sharedStaffAndChatterId();
    User::factory()->create(['id' => $id]);
    Chatter::factory()->grantedTo($agent)->create(['id' => $id]);

    $chatterSession = app(StartChatSessionAction::class)->execute($agent, ChatViewerData::chatter($id));
    $staffSession = app(StartChatSessionAction::class)->execute($agent, ChatViewerData::staff($id));
    $this->travelTo(now()->addYear());

    expect(app(ChatSessions::class)->isLive($agent, $chatterSession))->toBeTrue()
        ->and(app(ChatSessions::class)->isLive($agent, $staffSession))->toBeTrue()
        ->and(app(ChatSessions::class)->latestFor($agent, ChatViewerData::chatter($id)))->toBe($chatterSession)
        ->and(app(ChatSessions::class)->latestFor($agent, ChatViewerData::staff($id)))->toBe($staffSession);
});
