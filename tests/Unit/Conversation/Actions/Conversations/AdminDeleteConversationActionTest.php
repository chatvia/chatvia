<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\AdminDeleteConversationAction;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Str;

/**
 * AdminDeleteConversationAction soft-deletes a conversation through
 * ChatMemory (stamping WHO deleted it — the acting user's id, or the
 * 'admin' fallback for actor-less callers) and hard-deletes the parent
 * session only when it is both empty and inactive for over 30 days.
 */
beforeEach(function (): void {
    // ChatCache pins Cache::driver('chat') (redis in config); point it at the
    // array store for tests — same override as the other Memory unit tests.
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

/**
 * @return array{0: User, 1: Agent, 2: Session, 3: Conversation}
 */
function adcaSetup(array $sessionAttrs = []): array
{
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $session = Session::factory()->create(array_merge([
        'agent_id' => $agent->id,
        'last_activity_at' => now()->subDay(),
    ], $sessionAttrs));

    // Pin the source (the test DB's CHECK constraint predates the newer
    // ConversationSource cases the factory may randomly pick) and the title
    // (a random faker sentence can overflow varchar(255) once encrypted).
    $conversation = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
    ]);

    return [$owner, $agent, $session, $conversation];
}

it('soft-deletes the conversation, wipes message content, and stamps the actor id', function (): void {
    [$owner, $agent, $session, $conversation] = adcaSetup();

    $message = Message::create([
        'external_id' => (string) Str::uuid(),
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
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

    $sibling = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
    ]);

    app(AdminDeleteConversationAction::class)->execute($agent, $conversation, $owner);

    expect(Session::query()->whereKey($session->id)->exists())->toBeTrue()
        ->and(Conversation::query()->whereKey($sibling->id)->exists())->toBeTrue();
});
