<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\TurnState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

beforeEach(function (): void {
    // Tagged-cache API works under the array driver, keeping these unit
    // tests Redis-free.
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

it('reattaches an authenticated chatter to their existing session for the agent', function (): void {
    // Regression: ChatLogoutController invalidates the Laravel session, so
    // cs:session is gone on the next login. Without this lookup the chatter
    // gets a fresh chat_sessions row every time and their prior
    // conversations stay orphaned under the old session id.
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();

    $existing = ChatSession::create([
        'agent_id' => $agent->id,
        'user_id' => $chatter->id,
    ]);

    Auth::guard('web')->login($chatter);
    Session::flush(); // post-logout Laravel session state

    $resolved = app(TurnState::class)->firstOrCreateSession($agent);

    expect($resolved)->toBe($existing->external_id)
        ->and(ChatSession::where('user_id', $chatter->id)->where('agent_id', $agent->id)->count())
        ->toBe(1);
});

it('honours an in-memory cs:session marker on the same request', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    Session::put('cs:session', $session->external_id);

    Cache::driver('chat')->tags($session->external_id)
        ->forever($session->external_id, ['conversations' => []]);

    expect(app(TurnState::class)->firstOrCreateSession($agent))->toBe($session->external_id);
});

it('stamps user_id on a freshly minted session for an authenticated chatter', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();

    Auth::guard('web')->login($chatter);

    $resolved = app(TurnState::class)->firstOrCreateSession($agent);

    $row = ChatSession::where('external_id', $resolved)->firstOrFail();
    expect($row->user_id)->toBe($chatter->id)
        ->and($row->agent_id)->toBe($agent->id);
});

it('creates an anonymous session with null user_id when no chatter is signed in', function (): void {
    $agent = Agent::factory()->create();

    $resolved = app(TurnState::class)->firstOrCreateSession($agent);

    expect(ChatSession::where('external_id', $resolved)->value('user_id'))->toBeNull();
});
