<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Handoff\ReturnConversationToAiAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * The teammate who holds a conversation hands it back to the AI. The
 * broadcast carries 'returned' and the stored state is 'ai' right after, so
 * the widget can tell a return from a chat that never left the AI. Only the
 * holder can hand it back.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class]);

    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support');
});

it('returns the conversation to the AI and broadcasts "returned"', function (): void {
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Human, $this->owner)->create(['handoff_at' => now()]);

    $result = app(ReturnConversationToAiAction::class)->execute($conversation, $this->owner);

    expect($result)->toBeTrue()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Ai)
        ->and($conversation->fresh()->handoff_user_id)->toBeNull()
        ->and($conversation->fresh()->handoff_at)->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'returned'
        && $e->conversationId === $conversation->external_id);
});

it('refuses anyone but the teammate who holds the conversation', function (): void {
    $holder = teammateWithPermissions($this->owner, []);
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Human, $holder)->create();

    $result = app(ReturnConversationToAiAction::class)->execute($conversation, $this->owner);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human)
        ->and($conversation->fresh()->handoff_user_id)->toBe($holder->id);

    Event::assertNotDispatched(HandedOff::class);
});
