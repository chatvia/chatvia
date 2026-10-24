<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * The agent's website list comes one page at a time, newest first, with each
 * website's page count and progress; a website's refresh interval is set
 * through the same controller.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('pages the website list with link counts and progress', function (): void {
    $websites = SourceWebsite::factory()->ingested()->attachedTo($this->agent)->count(51)->create();
    $websites->last()->urls()->create(['url' => $websites->last()->url.'/a']);

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.websites.index', $this->agent))
        ->assertOk()
        ->assertJsonPath('total', 51)
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.id', $websites->last()->id)
        ->assertJsonPath('data.0.urls_count', 1)
        ->assertJsonPath('data.0.progress', 100);
});

it('sets how often a website is refreshed', function (): void {
    $website = SourceWebsite::factory()->ingested()->attachedTo($this->agent)->create();

    $this->actingAs($this->owner)
        ->patchJson(route('agents.sources.websites.update', [$this->agent, $website]), ['refresh_interval' => 'yearly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('refresh_interval');

    $this->actingAs($this->owner)
        ->patchJson(route('agents.sources.websites.update', [$this->agent, $website]), ['refresh_interval' => 'weekly'])
        ->assertOk();

    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.websites.index', $this->agent))
        ->assertJsonPath('data.0.refresh_interval', 'weekly');
});
