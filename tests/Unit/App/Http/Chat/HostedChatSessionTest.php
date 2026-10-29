<?php

use App\Http\Chat\HostedChatSession;
use App\Http\Chat\HostedChatViewer;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Models\Session as ChatSession;
use Illuminate\Support\Facades\Session;

/**
 * The hosted chat remembers the visitor's chat session in the browser session
 * and reattaches a signed-in chatter or staff member to their latest session.
 * A chatter's session is linked by `chatter_id`, a staff member's by
 * `user_id`, so equal ids from the two tables never share a session.
 */
it('reattaches a signed-in chatter to their session and stamps chatter_id on a new one', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = createAgent($agent->workspace->owner);
    $chatter = Chatter::factory()->grantedTo($agent)->grantedTo($otherAgent)->create();
    $existing = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    HostedChatViewer::signIn($chatter);
    Session::flush();

    $chat = app(HostedChatSession::class);
    $resolved = $chat->findOrStart($agent);
    $fresh = ChatSession::query()->where('external_id', $chat->findOrStart($otherAgent))->firstOrFail();
    $scope = $chat->scope($agent, 'conversation-1');

    expect($resolved)->toBe($existing->external_id)
        ->and(ChatSession::query()->where('chatter_id', $chatter->id)->where('agent_id', $agent->id)->count())->toBe(1)
        ->and($fresh)->chatter_id->toBe($chatter->id)->user_id->toBeNull()->agent_id->toBe($otherAgent->id)
        ->and($scope->viewer)->toEqual(ChatViewerData::chatter($chatter->id))
        ->and($scope->sessionId)->toBe($resolved);
});

it('reattaches signed-in staff to their own session, never a chatter\'s with the same id', function (): void {
    $agent = Agent::factory()->create();
    $id = sharedStaffAndChatterId();
    $staff = User::factory()->create(['id' => $id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['id' => $id]);
    $chatterSession = ChatSession::factory()->forChatter($chatter)->create(['agent_id' => $agent->id]);
    HostedChatViewer::signIn($staff);
    Session::flush();

    $resolved = app(HostedChatSession::class)->findOrStart($agent);

    expect($resolved)->not->toBe($chatterSession->external_id)
        ->and(ChatSession::query()->where('external_id', $resolved)->first())
        ->user_id->toBe($staff->id)
        ->chatter_id->toBeNull();
});

it('gives a guest no session until asked, then a separate anonymous one per agent', function (): void {
    [$first, $second] = Agent::factory()->count(2)->create();
    $chat = app(HostedChatSession::class);

    $found = $chat->find($first);
    $firstSession = $chat->findOrStart($first);
    $secondSession = $chat->findOrStart($second);

    expect($found)->toBeNull()
        ->and($firstSession)->not->toBe($secondSession)
        ->and(Session::get('cs:session:'.$first->id))->toBe($firstSession)
        ->and(Session::get('cs:session:'.$second->id))->toBe($secondSession)
        ->and(ChatSession::query()->where('external_id', $firstSession)->first())
        ->user_id->toBeNull()
        ->chatter_id->toBeNull();
});
