<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\ReturnConversationToAiAction;
use Domain\Conversation\Actions\Handoff\TakeoverConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Event;
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
function freshHandoffConversation(string $initialStatus = 'pending', ?int $handoffUserId = null): Conversation
{
    $agent = Agent::factory()->create();
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

beforeEach(function (): void {
    Event::fake([HandedOff::class]);
});

it('TakeoverConversationAction transitions pending → human and broadcasts admin identity', function (): void {
    $conversation = freshHandoffConversation('pending');
    $admin = $conversation->session->agent->user; // workspace owner — passes access-agent Gate

    $result = app(TakeoverConversationAction::class)->execute($conversation, $admin);

    expect($result)->toBeTrue()
        ->and($conversation->fresh()->handoff_status)->toBe('human')
        ->and($conversation->fresh()->handoff_user_id)->toBe($admin->id)
        ->and($conversation->fresh()->handoff_at)->not->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'human'
        && $e->conversationId === $conversation->external_id
        && $e->adminId === (string) $admin->id
        && $e->adminName === $admin->name);
});

it('TakeoverConversationAction returns false when the conversation is already with a different admin', function (): void {
    // Seed an existing human handoff under a different real user. The
    // canBeTakenOverBy guard refuses unless the caller is that same user.
    $incumbent = Agent::factory()->create()->user;
    $conversation = freshHandoffConversation('human', handoffUserId: $incumbent->id);
    $usurper = Agent::factory()->create()->user; // a different workspace user

    $result = app(TakeoverConversationAction::class)->execute($conversation, $usurper);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_user_id)->toBe($incumbent->id);

    Event::assertNotDispatched(HandedOff::class);
});

it('ReturnConversationToAiAction returns the conversation to AI and broadcasts "returned"', function (): void {
    $conversation = freshHandoffConversation('pending');
    $admin = $conversation->session->agent->user;

    // First take it over so canBeReturnedBy passes (requires isHandedOff()).
    app(TakeoverConversationAction::class)->execute($conversation, $admin);
    Event::fake([HandedOff::class]); // reset between actions

    $result = app(ReturnConversationToAiAction::class)->execute($conversation->fresh(), $admin);

    expect($result)->toBeTrue();

    // Final DB state is 'ai' — 'returned' was an intermediate that the broadcast
    // payload carries before the immediate flip.
    expect($conversation->fresh()->handoff_status)->toBe('ai')
        ->and($conversation->fresh()->handoff_user_id)->toBeNull()
        ->and($conversation->fresh()->handoff_at)->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'returned'
        && $e->conversationId === $conversation->external_id);
});

it('ReturnConversationToAiAction returns false when the caller is not the current handoff user', function (): void {
    $owner = Agent::factory()->create()->user;
    $conversation = freshHandoffConversation('human', handoffUserId: $owner->id);
    $intruder = Agent::factory()->create()->user;

    $result = app(ReturnConversationToAiAction::class)->execute($conversation, $intruder);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->toBe('human')
        ->and($conversation->fresh()->handoff_user_id)->toBe($owner->id);

    Event::assertNotDispatched(HandedOff::class);
});
