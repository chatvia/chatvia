<?php

use App\Identity\Chatter\Actions\UpdateChatterAccessAction;
use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;

beforeEach(function (): void {
    $this->workspace = User::factory()->workspace()->create();
    $this->chatter = User::factory()->chatter($this->workspace)->create();
    $this->action = app(UpdateChatterAccessAction::class);
});

it('sets a monthly message limit', function (): void {
    $this->action->execute(new UpdateChatterAccessData(
        chatter: $this->chatter,
        messagesLimit: 250,
        deactivated: false,
    ));

    expect(UserCredit::query()->where('user_id', $this->chatter->id)->value('messages'))->toBe(250);
});

it('clears the limit when null is given', function (): void {
    $this->action->execute(new UpdateChatterAccessData($this->chatter, 100, false));
    $this->action->execute(new UpdateChatterAccessData($this->chatter, null, false));

    expect(UserCredit::query()->where('user_id', $this->chatter->id)->exists())->toBeFalse();
});

it('deactivates a chatter with a reason', function (): void {
    $this->action->execute(new UpdateChatterAccessData(
        chatter: $this->chatter,
        messagesLimit: null,
        deactivated: true,
        deactivationReason: 'Spamming the agent',
    ));

    $this->chatter->refresh();
    expect($this->chatter->isDeactivated())->toBeTrue()
        ->and($this->chatter->deactivation_reason)->toBe('Spamming the agent');
});

it('reactivates a previously deactivated chatter and clears the reason', function (): void {
    $this->chatter->deactivate('temporary');

    $this->action->execute(new UpdateChatterAccessData(
        chatter: $this->chatter,
        messagesLimit: null,
        deactivated: false,
    ));

    $this->chatter->refresh();
    expect($this->chatter->isDeactivated())->toBeFalse()
        ->and($this->chatter->deactivation_reason)->toBeNull();
});
