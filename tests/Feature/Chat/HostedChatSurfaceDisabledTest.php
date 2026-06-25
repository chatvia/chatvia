<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The Hosted Chat surface can be turned off per agent from the Deploy page. When
 * it's disabled the chat 404s with the same AgentNotFound screen a non-existent
 * agent gets — "as if it doesn't exist".
 */
beforeEach(function (): void {
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    $this->agent = Agent::create([
        'user_id' => $owner->id,
        'team_id' => $owner->primaryTeam()->id,
        'name' => 'Public Agent',
        'external_id' => Str::random(14),
        'sub_domain' => Str::lower(Str::random(12)),
        'is_public' => true,
    ]);
    $this->host = $this->agent->sub_domain.'.chatvia.test';
});

it('serves the hosted chat when the surface is enabled (default)', function (): void {
    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page->component('agents/chat/AgentNoMessages'));
});

it('shows AgentNotFound when the hosted chat surface is disabled', function (): void {
    $this->agent->setSetting(key: 'hosted_chat', value: false, group: 'surfaces');

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page->component('agents/chat/AgentNotFound'));
});

it('serves the hosted chat again once the surface is re-enabled', function (): void {
    $this->agent->setSetting(key: 'hosted_chat', value: false, group: 'surfaces');
    $this->agent->setSetting(key: 'hosted_chat', value: true, group: 'surfaces');

    $this->get('https://'.$this->host.'/chat')
        ->assertInertia(fn (Assert $page): Assert => $page->component('agents/chat/AgentNoMessages'));
});
