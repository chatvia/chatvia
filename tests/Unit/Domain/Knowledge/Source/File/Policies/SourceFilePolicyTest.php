<?php

use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;

/**
 * Every ability on a knowledge file follows one rule: the workspace that owns
 * the file may, teammates included; anyone else may not.
 */
it('lets the owning workspace, teammates included, and nobody else', function (string $ability): void {
    $owner = User::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $owner->workspace()->id]);
    $teammate = User::factory()->employee($owner)->create();
    $stranger = User::factory()->create();

    expect($owner->can($ability, $file))->toBeTrue()
        ->and($teammate->can($ability, $file))->toBeTrue()
        ->and($stranger->can($ability, $file))->toBeFalse();
})->with(['view', 'update', 'delete', 'restore', 'forceDelete']);
