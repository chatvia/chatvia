<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Handoff\TakeoverConversationAction;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * A teammate takes over a waiting conversation ('pending' → 'human') and the
 * widget hears who joined. Only one teammate can hold it: a conversation
 * someone else holds, the slower of two teammates on the same waiting chat,
 * and a teammate who cannot reach the agent are refused.
 */
beforeEach(function (): void {
    Event::fake([HandedOff::class]);

    // An agent on its owner's team, so teammates of that team can reach it.
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support');
});

it('hands a waiting conversation to the teammate and broadcasts who joined', function (): void {
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Pending)->create();

    $result = app(TakeoverConversationAction::class)->execute($conversation, $this->owner);

    expect($result)->toBeTrue()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Human)
        ->and($conversation->fresh()->handoff_user_id)->toBe($this->owner->id)
        ->and($conversation->fresh()->handoff_at)->not->toBeNull();

    Event::assertDispatched(HandedOff::class, fn (HandedOff $e): bool => $e->status === 'human'
        && $e->conversationId === $conversation->external_id
        && $e->adminId === (string) $this->owner->id
        && $e->adminName === $this->owner->name);
});

it('refuses a conversation another teammate holds', function (): void {
    // Both teammates can reach the agent, so only the "held by someone
    // else" rule can refuse the second one.
    $incumbent = teammateWithPermissions($this->owner, []);
    $other = teammateWithPermissions($this->owner, []);
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Human, $incumbent)->create();

    expect(Gate::forUser($other)->allows('access-agent', $this->agent))->toBeTrue();

    $result = app(TakeoverConversationAction::class)->execute($conversation, $other);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_user_id)->toBe($incumbent->id);

    Event::assertNotDispatched(HandedOff::class);
});

it('refuses the second of two teammates who loaded the same waiting conversation', function (): void {
    $first = teammateWithPermissions($this->owner, []);
    $second = teammateWithPermissions($this->owner, []);
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Pending)->create();
    // Both teammates opened the conversation while it was still waiting.
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

it('lets the holder take the conversation over again', function (): void {
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Pending)->create();
    $takeover = app(TakeoverConversationAction::class);

    expect($takeover->execute($conversation, $this->owner))->toBeTrue()
        ->and($takeover->execute(Conversation::query()->findOrFail($conversation->id), $this->owner))->toBeTrue()
        ->and($conversation->fresh()->handoff_user_id)->toBe($this->owner->id);
});

it('refuses a teammate who cannot reach the agent', function (): void {
    $outsider = teammateWithPermissions($this->owner, [], allAgents: false);
    $conversation = Conversation::factory()->forAgent($this->agent)->handedOff(HandoffStatus::Pending)->create();

    $result = app(TakeoverConversationAction::class)->execute($conversation, $outsider);

    expect($result)->toBeFalse()
        ->and($conversation->fresh()->handoff_status)->toBe(HandoffStatus::Pending)
        ->and($conversation->fresh()->handoff_user_id)->toBeNull();

    Event::assertNotDispatched(HandedOff::class);
});
