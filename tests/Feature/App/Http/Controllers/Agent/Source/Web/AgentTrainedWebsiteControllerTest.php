<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * GET agents.sources.trained-websites.index offers the workspace's trained
 * websites, to link them to another agent.
 */
it('lists the workspace\'s trained websites, to link them', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $website = SourceWebsite::factory()->ingested()->create(['workspace_id' => $owner->workspace()->id]);

    $this->actingAs($owner)
        ->getJson(route('agents.sources.trained-websites.index', $agent))
        ->assertOk()
        ->assertJsonPath('0.id', $website->id);
});
