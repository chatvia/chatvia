<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\AdminDeleteConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;

/**
 * An admin delete soft-deletes a conversation, wipes its message text and
 * stamps who deleted it, and hard-deletes the parent session only when it
 * is left empty and has been inactive for more than 30 days.
 */
/**
 * @return array{0: User, 1: Agent, 2: Session, 3: Conversation}
 */
function adcaSetup(array $sessionAttrs = []): array
{
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $session = Session::factory()->create(['agent_id' => $agent->id, 'last_activity_at' => now()->subDay(), ...$sessionAttrs]);
    $conversation = Conversation::factory()->inSession($session)->create();

    return [$owner, $agent, $session, $conversation];
}

it('soft-deletes the conversation, wipes message content, stamps the actor and keeps a recent session', function (): void {
    [$owner, $agent, $session, $conversation] = adcaSetup();
    $message = Message::factory()->fromUser()->create([
        'chat_conversation_id' => $conversation->id,
        'content' => 'My card number is 4242...',
    ]);

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    $trashed = Conversation::withTrashed()->find($conversation->id);
    expect($trashed->trashed())->toBeTrue()
        ->and($trashed->deleted_by)->toBe((string) $owner->id)
        ->and($message->fresh()->content)->toBeNull()
        ->and(Session::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('deletes the session when its last conversation is removed and it has been inactive for over 30 days', function (): void {
    $this->freezeTime();
    [$owner, $agent, $session, $conversation] = adcaSetup(['last_activity_at' => now()->subDays(31)]);

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    expect(Session::query()->whereKey($session->id)->exists())->toBeFalse();
});

it('keeps an empty session inactive for exactly 30 days, and an old session that still holds a conversation', function (): void {
    $this->freezeSecond();
    [$owner, $agent, $boundary, $boundaryConversation] = adcaSetup(['last_activity_at' => now()->subDays(30)]);
    [, , $busy, $busyConversation] = adcaSetup(['last_activity_at' => now()->subDays(45)]);
    $sibling = Conversation::factory()->inSession($busy)->create();

    app(AdminDeleteConversationAction::class)->execute($agent, $boundaryConversation, $owner);
    app(AdminDeleteConversationAction::class)->execute($busyConversation->agent, $busyConversation, $owner);

    expect(Session::query()->whereKey($boundary->id)->exists())->toBeTrue()
        ->and(Session::query()->whereKey($busy->id)->exists())->toBeTrue()
        ->and(Conversation::query()->whereKey($sibling->id)->exists())->toBeTrue();
});
