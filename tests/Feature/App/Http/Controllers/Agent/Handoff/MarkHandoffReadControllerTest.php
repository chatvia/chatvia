<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;

/**
 * Marking a waiting conversation read from the live-chat list; one of another
 * agent of the workspace is a 404.
 */
it('404s a conversation of another agent of the workspace', function (): void {
    $owner = User::factory()->create();
    $agent = makeTeamAgent($owner, 'Support Bot');
    $foreign = Conversation::factory()->withoutTitle()->create(['agent_id' => makeTeamAgent($owner, 'Other Bot')->id, 'handoff_status' => HandoffStatus::Pending]);

    $this->actingAs($owner)
        ->postJson(route('agents.handoff.mark-read', ['agent' => $agent->external_id, 'conversation' => $foreign->external_id]))
        ->assertNotFound();
});
