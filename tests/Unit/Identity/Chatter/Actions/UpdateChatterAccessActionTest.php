<?php

/**
 * UpdateChatterAccessAction: the Members sheet's message cap (stored on
 * `message_limits.chatter_id`, never on the workspace's `workspace_id` row) and
 * the deactivation switch for one chatter.
 */

use App\Identity\Chatter\Actions\UpdateChatterAccessAction;
use App\Identity\Chatter\Data\UpdateChatterAccessData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;

beforeEach(function (): void {
    $this->owner = User::factory()->workspace()->create();
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
    $this->action = app(UpdateChatterAccessAction::class);
});

it('sets a monthly message limit', function (): void {
    $this->action->execute(new UpdateChatterAccessData(
        chatter: $this->chatter,
        messagesLimit: 250,
        deactivated: false,
    ));

    $limit = MessageLimit::query()->where('chatter_id', $this->chatter->id)->first();

    expect($limit?->monthly_limit)->toBe(250)
        ->and($limit?->workspace_id)->toBeNull();
});

it('does not write a workspace limit for a workspace whose id equals the chatter id', function (): void {
    // Chatter and workspace ids come from different tables and can be equal.
    $workspace = User::factory()->create()->workspace();
    $chatter = Chatter::factory()->ofWorkspace($this->owner)->create(['id' => $workspace->id]);

    $this->action->execute(new UpdateChatterAccessData($chatter, 50, false));

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->value('monthly_limit'))->toBe(50)
        ->and(MessageLimit::query()->where('workspace_id', $workspace->id)->exists())->toBeFalse();
});

it('clears the limit when null is given', function (): void {
    $this->action->execute(new UpdateChatterAccessData($this->chatter, 100, false));
    $this->action->execute(new UpdateChatterAccessData($this->chatter, null, false));

    expect(MessageLimit::query()->where('chatter_id', $this->chatter->id)->exists())->toBeFalse();
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
