<?php

use App\Identity\Workspace\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Knowledge Sources page (GET agents.sources) says whether the agent
 * searches its knowledge and may turn that off; the switch saves through the
 * generic `settings/knowledge` endpoint.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('shares the knowledge search switch, and saves the agent turning it off', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.sources', $this->agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('knowledgeSearch', ['installEnabled' => true, 'canChange' => true, 'enabled' => true]));

    $this->actingAs($this->owner)
        ->postJson(route('agents.settings.update', ['agent' => $this->agent->external_id, 'group' => 'knowledge']), ['search' => false])
        ->assertOk();

    $this->actingAs($this->owner)
        ->get(route('agents.sources', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page->where('knowledgeSearch.enabled', false));
});

it('offers no switch while the admin does not let agents choose', function (): void {
    config(['chatvia.vector.search.agent_choice' => false]);
    $this->agent->setSetting('search', false, 'knowledge');

    $this->actingAs($this->owner)
        ->get(route('agents.sources', $this->agent))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('knowledgeSearch', ['installEnabled' => true, 'canChange' => false, 'enabled' => true]));
});
