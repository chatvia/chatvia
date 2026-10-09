<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Actions\Settings\SetAgentDomainAction;
use Domain\Agent\Models\Agent;
use Domain\Billing\Models\UserQuota;
use Support\Exceptions\DomainValidationException;

/**
 * Build a workspace whose custom-domain quota is exactly $limit. Registration
 * no longer auto-provisions a `user_quotas` row (the trial-checkout webhook
 * does), so seed one explicitly — mirroring QuotaGatesTest.
 */
function setAgentDomainWorkspace(int $limit): User
{
    $workspace = User::factory()->create(['type' => UserType::Workspace]);

    UserQuota::firstOrCreate(
        ['user_id' => $workspace->id],
        config('billing.packages.starter.quota_limits.fixed', []),
    );

    $workspace->quota()->update([
        'custom_domains_limit' => $limit,
        'extra_custom_domains_limit' => 0,
    ]);

    return $workspace->fresh();
}

beforeEach(function (): void {
    $this->action = app(SetAgentDomainAction::class);
});

it('sets a custom domain when the workspace has quota', function (): void {
    $workspace = setAgentDomainWorkspace(1);
    $agent = Agent::factory()->for($workspace, 'user')->create();

    $this->action->execute($agent, $workspace, 'help.acme.com');

    expect($agent->fresh()->domain)->toBe('help.acme.com');
});

it('rejects a custom domain when the plan grants none', function (): void {
    $workspace = setAgentDomainWorkspace(0);
    $agent = Agent::factory()->for($workspace, 'user')->create();

    expect(fn () => $this->action->execute($agent, $workspace, 'help.acme.com'))
        ->toThrow(DomainValidationException::class);

    expect($agent->fresh()->domain)->toBeNull();
});

it('rejects when the custom-domain quota is already used up', function (): void {
    $workspace = setAgentDomainWorkspace(1);

    // First agent claims the only available slot.
    Agent::factory()->for($workspace, 'user')->create(['domain' => 'taken.acme.com']);
    $agent = Agent::factory()->for($workspace, 'user')->create();

    expect(fn () => $this->action->execute($agent, $workspace, 'second.acme.com'))
        ->toThrow(DomainValidationException::class);
});

it('always allows clearing the domain, even with no quota', function (): void {
    $workspace = setAgentDomainWorkspace(0);
    $agent = Agent::factory()->for($workspace, 'user')->create(['domain' => 'old.acme.com']);

    $this->action->execute($agent, $workspace, null);

    expect($agent->fresh()->domain)->toBeNull();
});
