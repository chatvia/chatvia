<?php

use App\Http\Chat\HostedChatSession;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/**
 * The hosted chat remembers the visitor's chat session in the browser session
 * and reattaches a signed-in chatter to their latest session.
 */
beforeEach(function (): void {
    // Tagged-cache API works under the array driver, keeping these unit
    // tests Redis-free.
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

it('reattaches an authenticated chatter to their existing session for the agent', function (): void {
    // ChatLogoutController invalidates the chat session, so cs:session:* is
    // gone on the next login. Without this lookup the chatter
    // gets a fresh chat_sessions row every time and their prior
    // conversations stay orphaned under the old session id.
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();

    $existing = ChatSession::create([
        'agent_id' => $agent->id,
        'user_id' => $chatter->id,
    ]);

    Auth::shouldUse('chat');
    Auth::guard('chat')->login($chatter);
    Session::flush(); // post-logout Laravel session state

    $resolved = app(HostedChatSession::class)->findOrStart($agent);

    expect($resolved)->toBe($existing->external_id)
        ->and(ChatSession::where('user_id', $chatter->id)->where('agent_id', $agent->id)->count())
        ->toBe(1);
});

it('honours an in-memory cs:session marker for the agent on the same request', function (): void {
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);
    Session::put('cs:session:'.$agent->id, $session->external_id);

    // Plant under the same [workspace, agent, session] tag set the reader uses
    // (see ChatCache::sessionTags).
    Cache::driver('chat')->tags(ChatCache::sessionTags($agent->workspaceExternalId(), $agent->external_id, $session->external_id))
        ->forever($session->external_id, ['conversations' => []]);

    expect(app(HostedChatSession::class)->findOrStart($agent))->toBe($session->external_id);
});

it('stamps user_id on a freshly minted session for an authenticated chatter', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();

    Auth::shouldUse('chat');
    Auth::guard('chat')->login($chatter);

    $resolved = app(HostedChatSession::class)->findOrStart($agent);

    $row = ChatSession::where('external_id', $resolved)->firstOrFail();
    expect($row->user_id)->toBe($chatter->id)
        ->and($row->agent_id)->toBe($agent->id);
});

it('creates an anonymous session with null user_id when no chatter is signed in', function (): void {
    $agent = Agent::factory()->create();

    $resolved = app(HostedChatSession::class)->findOrStart($agent);

    expect(ChatSession::where('external_id', $resolved)->value('user_id'))->toBeNull();
});

it('finds no session for a guest without starting one', function (): void {
    $agent = Agent::factory()->create();

    expect(app(HostedChatSession::class)->find($agent))->toBeNull()
        ->and(ChatSession::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

it('builds the memory scope with the signed-in viewer', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();
    Auth::shouldUse('chat');
    Auth::guard('chat')->login($chatter);

    $scope = app(HostedChatSession::class)->scope($agent, 'conversation-1');

    expect($scope->viewerId)->toBe($chatter->id)
        ->and($scope->conversationId)->toBe('conversation-1')
        ->and(Session::get('cs:session:'.$agent->id))->toBe($scope->sessionId);
});

it('keeps a separate chat session per agent in one browser session', function (): void {
    [$first, $second] = Agent::factory()->count(2)->create();

    $chat = app(HostedChatSession::class);
    $firstSession = $chat->findOrStart($first);
    $secondSession = $chat->findOrStart($second);

    expect($firstSession)->not->toBe($secondSession)
        ->and(Session::get('cs:session:'.$first->id))->toBe($firstSession)
        ->and(Session::get('cs:session:'.$second->id))->toBe($secondSession);
});
