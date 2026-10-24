<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;

/**
 * The header badge counts the chats waiting for a human across the agents
 * the person may view; which chats count is the Inbox query's rule.
 */
it('counts the chats waiting for a human for the header badge', function (): void {
    $owner = User::factory()->create();
    $support = makeTeamAgent($owner, 'Support');
    $sales = makeTeamAgent($owner, 'Sales');
    Conversation::factory()->forAgent($support)->live()->handedOff(HandoffStatus::Pending)->create();
    Conversation::factory()->forAgent($sales)->live()->handedOff(HandoffStatus::Pending)->create();
    Conversation::factory()->forAgent($sales)->live()->create();

    $this->actingAs($owner)
        ->getJson(route('inbox.waiting-count'))
        ->assertOk()
        ->assertExactJson(['count' => 2]);
});
