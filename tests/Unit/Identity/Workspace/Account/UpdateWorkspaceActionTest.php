<?php

use App\Identity\Workspace\Actions\Account\UpdateWorkspaceAction;
use App\Identity\Workspace\Data\Account\UpdateWorkspaceData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;

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
        ->and($owner->email)->toBe('dana@acme.example');
});

it('only renames a workspace that has no owner', function (): void {
    $workspace = Workspace::factory()->create(['name' => 'Old name']);

    $result = app(UpdateWorkspaceAction::class)->execute(new UpdateWorkspaceData(
        workspace: $workspace,
        name: 'Acme GmbH',
        ownerName: 'Nobody',
        ownerEmail: 'nobody@acme.test',
    ));

    expect($result->fresh()->name)->toBe('Acme GmbH')
        ->and(User::query()->where('email', 'nobody@acme.test')->exists())->toBeFalse();
});
