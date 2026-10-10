<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentDomainAction;
use Domain\Agent\Models\Agent;

beforeEach(function (): void {
    $this->action = app(SetAgentDomainAction::class);
    $this->workspace = User::factory()->create(['type' => UserType::Workspace]);
});

it('sets a custom domain', function (): void {
    $agent = Agent::factory()->for($this->workspace, 'user')->create();

    $this->action->execute($agent, 'help.acme.com');

    expect($agent->fresh()->domain)->toBe('help.acme.com');
});

it('lets every agent of a workspace have its own custom domain', function (): void {
    Agent::factory()->for($this->workspace, 'user')->create(['domain' => 'first.acme.com']);
    $agent = Agent::factory()->for($this->workspace, 'user')->create();

    $this->action->execute($agent, 'second.acme.com');

    expect($agent->fresh()->domain)->toBe('second.acme.com');
});

it('clears the domain', function (): void {
    $agent = Agent::factory()->for($this->workspace, 'user')->create(['domain' => 'old.acme.com']);

    $this->action->execute($agent, null);

    expect($agent->fresh()->domain)->toBeNull();
});
