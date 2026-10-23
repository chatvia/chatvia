<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\EndHandoffChatAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Queries\PendingHandoffsQuery;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Coverage for the rest of the handoff lifecycle Actions:
 *
 *  - EndHandoffChatAction   — human ends the session, conversation returns to AI
 *    (distinct from ReturnConversationToAiAction: that path is admin-driven and
 *    broadcasts 'returned'; this one is user-driven and broadcasts 'user_ended').
 *  - PendingHandoffsQuery — admin dashboard query: only conversations on
 *    THIS agent with handoff_status='pending' AND is_visible=true, ordered
 *    by updated_at desc, capped at 50.
 */
function newHandoffSession(Agent $agent, string $initialStatus, ?int $handoffUserId = null): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    /** @var Conversation $conversation */
    $conversation = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::Widget,
        'handoff_status' => $initialStatus,
        'handoff_user_id' => $handoffUserId,
        'is_visible' => true,
    ]);

    return $conversation;
}

beforeEach(function (): void {
    Event::fake([HandedOff::class]);
});

/*
|--------------------------------------------------------------------------
| EndHandoffChatAction
|--------------------------------------------------------------------------
*/

it('EndHandoffChatAction transitions human → ai and broadcasts user_ended', function (): void {
    $agent = Agent::factory()->create();
    $handoffAdmin = Agent::factory()->create()->workspace->owner; // a real user for the FK
    $conversation = newHandoffSession($agent, 'human', handoffUserId: $handoffAdmin->id);

    $result = app(EndHandoffChatAction::class)->execute($conversation);

    expect($result)->toBeTrue()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai)
        ->and($conversation->fresh()->handoff_user_id)->toBeNull()
        ->and($conversation->fresh()->handoff_at)->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'user_ended'
        && $e->conversationId === $conversation->external_id
        && $e->adminId === (string) $handoffAdmin->id
        && $e->adminName === $handoffAdmin->name);
});

it('EndHandoffChatAction is a no-op (returns false, no event) when not in human mode', function (): void {
    $agent = Agent::factory()->create();
    $conversation = newHandoffSession($agent, 'pending');

    $result = app(EndHandoffChatAction::class)->execute($conversation);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending);

    Event::assertNotDispatched(HandedOff::class);
});

/*
|--------------------------------------------------------------------------
| PendingHandoffsQuery
|--------------------------------------------------------------------------
*/

it('PendingHandoffsQuery returns only pending+visible conversations for the given agent', function (): void {
    $agent = Agent::factory()->create();
    $otherAgent = Agent::factory()->create();

    // Should be returned:
    $pendingA = newHandoffSession($agent, 'pending');
    $pendingB = newHandoffSession($agent, 'pending');

    // Should be filtered out:
    newHandoffSession($agent, 'human');     // wrong status
    newHandoffSession($agent, 'ai');        // wrong status
    newHandoffSession($otherAgent, 'pending'); // wrong agent
    // Wrong visibility:
    Conversation::factory()->create([
        'chat_session_id' => Session::factory()->create(['agent_id' => $agent->id])->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::Widget,
        'handoff_status' => 'pending',
        'is_visible' => false,
    ]);

    $rows = app(PendingHandoffsQuery::class)->get($agent);

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn (Conversation $r): bool => in_array(
            $r->external_id,
            [$pendingA->external_id, $pendingB->external_id],
            true,
        )))->toBeTrue();
});

it('PendingHandoffsQuery lists the most recently updated conversation first', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $pending = fn (int $minutesAgo): Conversation => Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        'handoff_status' => 'pending',
        'is_visible' => true,
        'updated_at' => now()->subMinutes($minutesAgo),
    ]);

    $older = $pending(30);
    $newest = $pending(1);
    $middle = $pending(10);

    expect(app(PendingHandoffsQuery::class)->get($agent)->pluck('id')->all())
        ->toBe([$newest->id, $middle->id, $older->id]);
});

it('PendingHandoffsQuery lists at most its limit and leaves out the oldest', function (): void {
    $this->freezeSecond();
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $conversations = Conversation::factory()
        ->count(PendingHandoffsQuery::LIMIT + 1)
        ->sequence(fn (Sequence $sequence): array => ['updated_at' => now()->subMinutes($sequence->index)])
        ->create([
            'chat_session_id' => $session->id,
            'source' => ConversationSource::Widget,
            'handoff_status' => 'pending',
            'is_visible' => true,
        ]);

    $rows = app(PendingHandoffsQuery::class)->get($agent);

    expect($rows)->toHaveCount(PendingHandoffsQuery::LIMIT)
        ->and($rows->pluck('id'))->not->toContain($conversations->last()->id);
});

it('PendingHandoffsQuery carries the latest message of each conversation', function (): void {
    $agent = Agent::factory()->create();
    $waiting = newHandoffSession($agent, 'pending');
    $quiet = newHandoffSession($agent, 'pending');
    Message::factory()->fromUser()->create(['chat_conversation_id' => $waiting->id, 'content' => 'Hello?', 'created_at' => now()->subMinutes(2)]);
    Message::factory()->fromUser()->create(['chat_conversation_id' => $waiting->id, 'content' => 'Anyone there?', 'created_at' => now()->subMinute()]);

    $rows = app(PendingHandoffsQuery::class)->get($agent)->keyBy('id');

    expect($rows[$waiting->id]->lastMessage?->content)->toBe('Anyone there?')
        ->and($rows[$quiet->id]->lastMessage)->toBeNull();
});
