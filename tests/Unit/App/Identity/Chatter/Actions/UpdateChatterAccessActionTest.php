<?php

/**
 * UpdateChatterAccessAction: the Members sheet's message cap and deactivation
 * switch for one chatter. The cap itself is SetMessageLimitAction's rule (see
 * its test); this pins what the access update adds: a field left out keeps
 * its value, and the switch moves the chatter off and on.
 */

use App\Identity\Chatter\Actions\UpdateChatterAccessAction;
use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Optional;

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
    $this->action = app(UpdateChatterAccessAction::class);
});

it('keeps the stored message limit when the update leaves it out', function (): void {
    MessageLimit::factory()->forChatter($this->chatter)->create(['monthly_limit' => 250]);

    $this->action->execute(new UpdateChatterAccessData($this->chatter, Optional::create(), true, 'Spam'));

    expect(MessageLimit::query()->where('chatter_id', $this->chatter->id)->value('monthly_limit'))->toBe(250)
        ->and($this->chatter->fresh()->isDeactivated())->toBeTrue();
});

it('changes only the reason when the update leaves the switch out', function (bool $deactivated, ?string $reason): void {
    if ($deactivated) {
        $this->chatter->update(['deactivated_at' => now()->subDay(), 'deactivation_reason' => 'first']);
    }
    $before = $this->chatter->fresh()->deactivated_at;

    $this->action->execute(new UpdateChatterAccessData($this->chatter, Optional::create(), Optional::create(), 'second'));

    $chatter = $this->chatter->fresh();

    expect($chatter->deactivation_reason)->toBe($reason)
        ->and($chatter->deactivated_at?->toDateTimeString())->toBe($before?->toDateTimeString());
})->with([
    'a deactivated chatter gets the new reason' => [true, 'second'],
    'an active chatter stays active, without a reason' => [false, null],
]);

it('deactivates a chatter with a reason', function (): void {
    $this->action->execute(new UpdateChatterAccessData(
        chatter: $this->chatter,
        messagesLimit: null,
        deactivated: true,
        deactivationReason: 'Spamming the agent',
    ));

    $this->chatter->refresh();
    expect($this->chatter->isDeactivated())->toBeTrue()
        ->and($this->chatter->deactivation_reason)->toBe('Spamming the agent')
        ->and($this->owner->fresh()->isDeactivated())->toBeFalse();
});

it('updates the reason of an already deactivated chatter without moving the timestamp', function (): void {
    $this->chatter->update(['deactivated_at' => now()->subDay(), 'deactivation_reason' => 'first']);
    $deactivatedAt = $this->chatter->fresh()->deactivated_at;

    $this->action->execute(new UpdateChatterAccessData($this->chatter, null, true, 'second'));

    $this->chatter->refresh();
    expect($this->chatter->deactivation_reason)->toBe('second')
        ->and($this->chatter->deactivated_at->equalTo($deactivatedAt))->toBeTrue();
});

it('reactivates a previously deactivated chatter and clears the reason', function (): void {
    $this->chatter->update(['deactivated_at' => now(), 'deactivation_reason' => 'temporary']);

    $this->action->execute(new UpdateChatterAccessData(
        chatter: $this->chatter,
        messagesLimit: null,
        deactivated: false,
    ));

    $this->chatter->refresh();
    expect($this->chatter->isDeactivated())->toBeFalse()
        ->and($this->chatter->deactivation_reason)->toBeNull();
});
