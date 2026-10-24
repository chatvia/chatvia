<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\EndHandoffChatAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * The visitor ends a live chat: a conversation a teammate holds goes back to
 * the AI and the widget hears `user_ended` (the teammate's own return is
 * ReturnConversationToAiAction, which broadcasts `returned`). A conversation
 * nobody holds is left as it is.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class]);
});

it('returns a held conversation to the AI and broadcasts user_ended with the teammate who held it', function (): void {
    $agent = Agent::factory()->create();
    $operator = $agent->workspace->owner;
    $conversation = Conversation::factory()->forAgent($agent)->visible()->handedOff(HandoffStatus::Human, $operator)->create();

    $result = app(EndHandoffChatAction::class)->execute($conversation);

    expect($result)->toBeTrue()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai)
        ->and($conversation->fresh()->handoff_user_id)->toBeNull()
        ->and($conversation->fresh()->handoff_at)->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'user_ended'
        && $e->conversationId === $conversation->external_id
        && $e->adminId === (string) $operator->id
        && $e->adminName === $operator->name);
});

it('does nothing, and broadcasts nothing, when no teammate holds the conversation', function (): void {
    $conversation = Conversation::factory()->visible()->handedOff(HandoffStatus::Pending)->create();

    $result = app(EndHandoffChatAction::class)->execute($conversation);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending);

    Event::assertNotDispatched(HandedOff::class);
});
