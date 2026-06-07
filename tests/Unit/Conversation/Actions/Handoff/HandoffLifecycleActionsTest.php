<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\EndHandoffChatAction;
use Domain\Conversation\Actions\Handoff\ListPendingHandoffsAction;
use Domain\Conversation\Data\Handoff\HandoffSummaryData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Coverage for the rest of the handoff lifecycle Actions:
 *
 *  - EndHandoffChatAction   — human ends the session, conversation returns to AI
 *    (distinct from ReturnConversationToAiAction: that path is admin-driven and
 *    broadcasts 'returned'; this one is user-driven and broadcasts 'user_ended').
 *  - ListPendingHandoffsAction — admin dashboard query: only conversations on
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
    $handoffAdmin = Agent::factory()->create()->user; // a real user for the FK
    $conversation = newHandoffSession($agent, 'human', handoffUserId: $handoffAdmin->id);

    $result = app(EndHandoffChatAction::class)->execute($conversation);

    expect($result)->toBeTrue()
        ->and($conversation->fresh()->handoff_status)->toBe('ai')
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
        ->and($conversation->fresh()->handoff_status)->toBe('pending');

    Event::assertNotDispatched(HandedOff::class);
});

/*
|--------------------------------------------------------------------------
| ListPendingHandoffsAction
|--------------------------------------------------------------------------
*/

it('ListPendingHandoffsAction returns only pending+visible conversations for the given agent', function (): void {
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

    $rows = app(ListPendingHandoffsAction::class)->execute($agent);

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn (HandoffSummaryData $r): bool => in_array(
            $r->conversation_id,
            [$pendingA->external_id, $pendingB->external_id],
            true,
        )))->toBeTrue();
});

it('ListPendingHandoffsAction returns an empty Collection when the agent has no pending handoffs', function (): void {
    $agent = Agent::factory()->create();
    newHandoffSession($agent, 'ai'); // exists but not pending

    $rows = app(ListPendingHandoffsAction::class)->execute($agent);

    expect($rows)->toHaveCount(0);
});
