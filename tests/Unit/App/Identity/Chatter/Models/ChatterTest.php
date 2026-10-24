<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;

/**
 * A chatter reaches only the agents granted to it. The database refuses a
 * grant to another workspace's agent (CrossWorkspaceDataTest), so a grant is
 * always inside the chatter's own workspace.
 */
it('reaches the agents it was granted and no other agent of its workspace', function (): void {
    $owner = User::factory()->create();
    $granted = createAgent($owner);
    $other = createAgent($owner);

    $chatter = Chatter::factory()->grantedTo($granted)->create();

    expect($chatter->canAccessAgent($granted))->toBeTrue()
        ->and($chatter->canAccessAgent($other))->toBeFalse();
});
