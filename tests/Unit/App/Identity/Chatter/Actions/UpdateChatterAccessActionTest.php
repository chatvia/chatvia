<?php

use App\Identity\Chatter\Actions\UpdateChatterAccessAction;
use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Optional;

/**
 * UpdateChatterAccessAction: the Members sheet's message cap and deactivation
 * switch for one chatter. The cap itself is SetMessageLimitAction's rule (see
 * its test); this pins what the access update adds: a field left out keeps
 * its value, and the switch moves the chatter off and on.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
    $this->action = app(UpdateChatterAccessAction::class);
});

it('deactivates a chatter with a reason and keeps a message limit the update leaves out', function (): void {
    MessageLimit::factory()->forChatter($this->chatter)->create(['monthly_limit' => 250]);

    $this->action->execute(new UpdateChatterAccessData($this->chatter, Optional::create(), true, 'Spamming the agent'));

    $chatter = $this->chatter->fresh();
    expect($chatter->isDeactivated())->toBeTrue()
        ->and($chatter->deactivation_reason)->toBe('Spamming the agent')
        ->and(MessageLimit::query()->where('chatter_id', $this->chatter->id)->value('monthly_limit'))->toBe(250)
        ->and($this->owner->fresh()->isDeactivated())->toBeFalse();
});

it('reactivates a previously deactivated chatter and clears the reason', function (): void {
    $this->chatter->update(['deactivated_at' => now(), 'deactivation_reason' => 'temporary']);

    $this->action->execute(new UpdateChatterAccessData(
        chatter: $this->chatter,
        messagesLimit: null,
        deactivated: false,
    ));

    $chatter = $this->chatter->fresh();
    expect($chatter->isDeactivated())->toBeFalse()
        ->and($chatter->deactivation_reason)->toBeNull();
});
