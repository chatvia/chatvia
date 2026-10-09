<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatSessions;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

it('starts a guest session that lapses after a few days', function (): void {
    $agent = Agent::factory()->create();

    $sessionId = app(StartChatSessionAction::class)->execute($agent, null);

    expect(ChatSession::query()->where('external_id', $sessionId)->value('user_id'))->toBeNull()
        ->and(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeTrue();

    Carbon::setTestNow(now()->addDays(StartChatSessionAction::GUEST_SESSION_TTL_DAYS)->addMinute());

    expect(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeFalse();
});

it('keeps a signed-in chatter session live', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();

    $sessionId = app(StartChatSessionAction::class)->execute($agent, $chatter->id);

    Carbon::setTestNow(now()->addYear());

    expect(app(ChatSessions::class)->isLive($agent, $sessionId))->toBeTrue()
        ->and(app(ChatSessions::class)->latestFor($agent, $chatter->id))->toBe($sessionId);
});
