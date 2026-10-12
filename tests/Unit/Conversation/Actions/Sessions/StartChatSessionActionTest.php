<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatSessions;
use Illuminate\Support\Carbon;

/**
 * Starting a visitor session: a guest's lapses after a few days, a signed-in
 * viewer's stays. A chatter's session is linked by `chatter_id`, a staff
 * member's by `user_id`, so equal ids from the two tables never share one.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

it('starts a guest session that lapses after a few days', function (): void {
    $agent = Agent::factory()->create();

    $sessionId = app(StartChatSessionAction::class)->execute($agent, null);

    $row = ChatSession::query()->where('external_id', $sessionId)->firstOrFail();

    expect($row->user_id)->toBeNull()
        ->and($row->chatter_id)->toBeNull()
        ->and(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeTrue();

    Carbon::setTestNow(now()->addDays(StartChatSessionAction::GUEST_SESSION_TTL_DAYS)->addMinute());

    expect(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeFalse();
});

it('keeps a signed-in chatter session live', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $viewer = ChatViewerData::chatter($chatter->id);

    $sessionId = app(StartChatSessionAction::class)->execute($agent, $viewer);

    Carbon::setTestNow(now()->addYear());

    expect(ChatSession::query()->where('external_id', $sessionId)->value('chatter_id'))->toBe($chatter->id)
        ->and(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeTrue()
        ->and(app(ChatSessions::class)->latestFor($agent, $viewer))->toBe($sessionId);
});

it('keeps a signed-in staff session live', function (): void {
    $agent = Agent::factory()->create();
    $staffId = $agent->workspace->owner->id;
    $viewer = ChatViewerData::staff($staffId);

    $sessionId = app(StartChatSessionAction::class)->execute($agent, $viewer);

    Carbon::setTestNow(now()->addYear());

    expect(ChatSession::query()->where('external_id', $sessionId)->value('user_id'))->toBe($staffId)
        ->and(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeTrue()
        ->and(app(ChatSessions::class)->latestFor($agent, $viewer))->toBe($sessionId);
});

it('keeps the sessions of a chatter and a staff account with the same id apart', function (): void {
    $agent = Agent::factory()->create();

    // One id taken from both id sequences, so neither table already holds it.
    $id = sharedStaffAndChatterId();
    User::factory()->create(['id' => $id]);
    Chatter::factory()->grantedTo($agent)->create(['id' => $id]);

    $chatterSession = app(StartChatSessionAction::class)->execute($agent, ChatViewerData::chatter($id));
    $staffSession = app(StartChatSessionAction::class)->execute($agent, ChatViewerData::staff($id));

    expect(app(ChatSessions::class)->latestFor($agent, ChatViewerData::chatter($id)))->toBe($chatterSession)
        ->and(app(ChatSessions::class)->latestFor($agent, ChatViewerData::staff($id)))->toBe($staffSession);
});
