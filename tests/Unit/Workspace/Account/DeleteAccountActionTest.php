<?php

use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Jobs\DeleteWorkspaceJob;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Bus;

it('switches the account and its members off at once and queues the teardown', function (): void {
    Bus::fake([DeleteWorkspaceJob::class]);
    $owner = User::factory()->workspace()->create();
    $teammate = User::factory()->employee($owner)->create();

    app(DeleteAccountAction::class)->execute($owner);

    expect($owner->fresh()->isDeactivated())->toBeTrue()
        ->and($owner->fresh()->deactivation_reason)->toBe('Account deletion in progress')
        ->and($teammate->fresh()->isDeactivated())->toBeTrue()
        ->and(User::whereKey($owner->id)->exists())->toBeTrue();

    Bus::assertDispatched(DeleteWorkspaceJob::class, fn (DeleteWorkspaceJob $job): bool => $job->userId === $owner->id);
});
