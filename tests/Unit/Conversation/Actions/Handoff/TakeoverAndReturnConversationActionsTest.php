<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\ReturnConversationToAiAction;
use Domain\Conversation\Actions\Handoff\TakeoverConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Handoff state-machine transitions: 'ai' → 'pending' → 'human' → ('returned' → 'ai').
 *
 *  - RequestHandoffAction (covered in RequestHandoffActionTest) drives 'ai' → 'pending'
 *  - TakeoverConversationAction (here) drives 'pending'/'ai' → 'human'
 *  - ReturnConversationToAiAction (here) drives 'human' → 'returned' → 'ai'
 *
 * Notable: ReturnConversationToAiAction updates to 'returned' first (so the
 * broadcast carries that status) and then immediately flips to 'ai'. The
 * final persisted state is 'ai'; downstream consumers see 'returned' on the
 * Reverb channel.
 */
function freshHandoffConversation(string $initialStatus = 'pending', ?int $handoffUserId = null, ?Agent $agent = null): Conversation
{
    $agent ??= Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    /** @var Conversation $conversation */
    $conversation = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::Widget,
        'handoff_status' => $initialStatus,
        'handoff_user_id' => $handoffUserId,
    ]);

    return $conversation;
}

/**
 * An agent on its owner's team, so teammates of that team can reach it.
 *
 * @return array{0: User, 1: Agent}
 */
function teamHandoffAgent(): array
{
    $owner = User::factory()->workspace()->create();
    $team = app(TeamService::class)->createDefaultTeam($owner);

    return [$owner, makeTeamAgent($owner, 'Support', $team)];
}

beforeEach(function (): void {
    Event::fake([HandedOff::class]);
});

it('TakeoverConversationAction transitions pending → human and broadcasts admin identity', function (): void {
    $conversation = freshHandoffConversation('pending');
    $admin = $conversation->session->agent->workspace->owner; // workspace owner — passes access-agent Gate

    $result = app(TakeoverConversationAction::class)->execute($conversation, $admin);

    expect($result)->toBeTrue()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human)
        ->and($conversation->fresh()->handoff_user_id)->toBe($admin->id)
        ->and($conversation->fresh()->handoff_at)->not->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'human'
        && $e->conversationId === $conversation->external_id
        && $e->adminId === (string) $admin->id
        && $e->adminName === $admin->name);
});

it('TakeoverConversationAction returns false when the conversation is already with a different operator', function (): void {
    // Both teammates can reach the agent, so only the "held by someone
    // else" rule can refuse the second one.
    [$owner, $agent] = teamHandoffAgent();
    $incumbent = teammateWithPermissions($owner, []);
    $other = teammateWithPermissions($owner, []);
    $conversation = freshHandoffConversation('human', handoffUserId: $incumbent->id, agent: $agent);

    expect(Gate::forUser($other)->allows('access-agent', $agent))->toBeTrue();

    $result = app(TakeoverConversationAction::class)->execute($conversation, $other);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_user_id)->toBe($incumbent->id);

    Event::assertNotDispatched(HandedOff::class);
});

it('TakeoverConversationAction refuses the second of two operators who loaded the same waiting conversation', function (): void {
    [$owner, $agent] = teamHandoffAgent();
    $first = teammateWithPermissions($owner, []);
    $second = teammateWithPermissions($owner, []);
    $conversation = freshHandoffConversation('pending', agent: $agent);
    // Both operators opened the conversation while it was still waiting.
    $seenByFirst = Conversation::query()->findOrFail($conversation->id);
    $seenBySecond = Conversation::query()->findOrFail($conversation->id);

    $firstWon = app(TakeoverConversationAction::class)->execute($seenByFirst, $first);
    $secondWon = app(TakeoverConversationAction::class)->execute($seenBySecond, $second);

    expect($firstWon)->toBeTrue()
        ->and($secondWon)->toBeFalse()
        ->and($conversation->fresh()->handoff_user_id)->toBe($first->id)
        ->and($seenByFirst->handoff_user_id)->toBe($first->id)
        ->and($seenByFirst->isDirty())->toBeFalse();

    Event::assertDispatchedTimes(HandedOff::class, 1);
});

it('TakeoverConversationAction lets the holder take the conversation over again', function (): void {
    $conversation = freshHandoffConversation('pending');
    $admin = $conversation->session->agent->workspace->owner;
    $takeover = app(TakeoverConversationAction::class);

    expect($takeover->execute($conversation, $admin))->toBeTrue()
        ->and($takeover->execute(Conversation::query()->findOrFail($conversation->id), $admin))->toBeTrue()
        ->and($conversation->fresh()->handoff_user_id)->toBe($admin->id);
});

it('TakeoverConversationAction returns false for an operator who cannot reach the agent', function (): void {
    [$owner, $agent] = teamHandoffAgent();
    $outsider = teammateWithPermissions($owner, [], allAgents: false);
    $conversation = freshHandoffConversation('pending', agent: $agent);

    $result = app(TakeoverConversationAction::class)->execute($conversation, $outsider);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending)
        ->and($conversation->fresh()->handoff_user_id)->toBeNull();

    Event::assertNotDispatched(HandedOff::class);
});

it('ReturnConversationToAiAction returns the conversation to AI and broadcasts "returned"', function (): void {
    $conversation = freshHandoffConversation('pending');
    $admin = $conversation->session->agent->workspace->owner;

    // First take it over so isHeldBy passes (requires isHandedOff()).
    app(TakeoverConversationAction::class)->execute($conversation, $admin);
    Event::fake([HandedOff::class]); // reset between actions

    $result = app(ReturnConversationToAiAction::class)->execute($conversation->fresh(), $admin);

    expect($result)->toBeTrue();

    // Final DB state is 'ai' — 'returned' was an intermediate that the broadcast
    // payload carries before the immediate flip.
    expect($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai)
        ->and($conversation->fresh()->handoff_user_id)->toBeNull()
        ->and($conversation->fresh()->handoff_at)->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'returned'
        && $e->conversationId === $conversation->external_id);
});

it('ReturnConversationToAiAction returns false when the caller is not the current handoff user', function (): void {
    $owner = Agent::factory()->create()->workspace->owner;
    $conversation = freshHandoffConversation('human', handoffUserId: $owner->id);
    $intruder = Agent::factory()->create()->workspace->owner;

    $result = app(ReturnConversationToAiAction::class)->execute($conversation, $intruder);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human)
        ->and($conversation->fresh()->handoff_user_id)->toBe($owner->id);

    Event::assertNotDispatched(HandedOff::class);
});
