<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Setting how often one of the agent's websites is refreshed; an unknown
 * interval is refused.
 */
it('sets how often a website is refreshed and refuses an unknown interval', function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
    $website = SourceWebsite::factory()->ingested()->attachedTo($this->agent)->create();

    $this->actingAs($this->owner)
        ->patchJson(route('agents.sources.websites.update', [$this->agent, $website]), ['refresh_interval' => 'yearly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('refresh_interval');

    $this->actingAs($this->owner)
        ->patchJson(route('agents.sources.websites.update', [$this->agent, $website]), ['refresh_interval' => 'weekly'])
        ->assertOk();

    expect($website->fresh()->refresh_interval->value)->toBe('weekly');
});
