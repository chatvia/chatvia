<?php

use App\Identity\Workspace\Actions\Account\UpdateWorkspaceAction;
use App\Identity\Workspace\Data\Account\UpdateWorkspaceData;
use App\Identity\Workspace\Models\User;

it('renames the workspace and saves its owner\'s name and email', function (): void {
    $owner = User::factory()->create(['name' => 'Dana Owner', 'email' => 'dana@acme.test']);
    $workspace = $owner->workspace();

    app(UpdateWorkspaceAction::class)->execute(new UpdateWorkspaceData(
        workspace: $workspace,
        name: 'Acme GmbH',
        ownerName: 'Dana Smith',
        ownerEmail: 'dana@acme.example',
    ));

    $owner->refresh();
    expect($workspace->fresh()->name)->toBe('Acme GmbH')
        ->and($owner->name)->toBe('Dana Smith')
        ->and($owner->email)->toBe('dana@acme.example')
        ->and($owner->email_verified_at)->toBeNull();
});

it('keeps the owner\'s email confirmed when only the names change', function (): void {
    $owner = User::factory()->create(['email' => 'dana@acme.test']);

    app(UpdateWorkspaceAction::class)->execute(new UpdateWorkspaceData(
        workspace: $owner->workspace(),
        name: 'Acme GmbH',
        ownerName: 'Dana Smith',
        ownerEmail: 'dana@acme.test',
    ));

    expect($owner->fresh()->email_verified_at)->not->toBeNull();
});
