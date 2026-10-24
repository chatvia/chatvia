<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\AdminDeleteConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;

/**
 * AdminDeleteConversationAction soft-deletes a conversation through
 * DeleteConversationAction (stamping WHO deleted it — the acting user's id, or the
 * 'admin' fallback for actor-less callers) and hard-deletes the parent
 * session only when it is both empty and inactive for over 30 days.
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

it('soft-deletes the conversation, wipes message content, and stamps the actor id', function (): void {
    [$owner, $agent, $session, $conversation] = adcaSetup();

    $message = Message::factory()->fromUser()->create([
        'chat_conversation_id' => $conversation->id,
        'content' => 'My card number is 4242...',
    ]);

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    $trashed = Conversation::withTrashed()->find($conversation->id);

    expect($trashed)->not->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        // deleted_by records WHO deleted: the acting user's id as a string.
        ->and($trashed->deleted_by)->toBe((string) $owner->id)
        ->and($message->fresh()->content)->toBeNull();

    // Session had recent activity — it must survive the conversation delete.
    expect(Session::query()->whereKey($session->id)->exists())->toBeTrue();
});

it("falls back to the 'admin' stamp when no actor is given", function (): void {
    [, $agent, , $conversation] = adcaSetup();

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation);

    expect(Conversation::withTrashed()->find($conversation->id)->deleted_by)->toBe('admin');
});

it('deletes the session when its last conversation is removed and it has been inactive for over 30 days', function (): void {
    $this->freezeTime();

    [$owner, $agent, $session, $conversation] = adcaSetup([
        'last_activity_at' => now()->subDays(31),
    ]);

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    expect(Session::query()->whereKey($session->id)->exists())->toBeFalse();
});

it('keeps a stale-empty session when the inactivity window has not passed', function (): void {
    // Whole-second freeze: the timestamp column drops microseconds, so a
    // microsecond-precision "now" would make the stored boundary value
    // strictly older and flip the lt() comparison.
    $this->freezeSecond();

    // Exactly 30 days is NOT "older than 30 days" — the branch requires lt().
    [$owner, $agent, $session, $conversation] = adcaSetup([
        'last_activity_at' => now()->subDays(30),
    ]);

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    expect(Session::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('keeps an inactive session while other conversations remain in it', function (): void {
    $this->freezeTime();

    [$owner, $agent, $session, $conversation] = adcaSetup([
        'last_activity_at' => now()->subDays(45),
    ]);

    $sibling = Conversation::factory()->inSession($session)->create();

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    expect(Session::query()->whereKey($session->id)->exists())->toBeTrue()
        ->and(Conversation::query()->whereKey($sibling->id)->exists())->toBeTrue();
});

it('deletes the conversation exactly once', function (): void {
    [$owner, $agent, , $conversation] = adcaSetup();

    // The deleting hook wipes attachments + cache; a second delete() call
    // would run that cascade (and the soft-delete UPDATE) all over again.
    $deletes = 0;
    Conversation::deleting(function () use (&$deletes): void {
        $deletes++;
    });

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    expect($deletes)->toBe(1);
});

it('does nothing when the conversation has no session any more', function (): void {
    [$owner, $agent, , $conversation] = adcaSetup();

    // The FK cascades a session delete onto its conversations; a loaded model
    // whose session is gone has nothing left to erase — and must not crash.
    $conversation->setRelation('session', null);

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    expect(Conversation::query()->whereKey($conversation->id)->exists())->toBeTrue();
});
