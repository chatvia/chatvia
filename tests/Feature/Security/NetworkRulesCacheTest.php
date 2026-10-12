<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Cache;

/**
 * EnsureWorkspaceNetworkAllowed runs on every web request, so it caches the
 * workspace's network rules. Saving the policy clears that cache, so a new
 * rule applies on the very next request.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

it('caches the network rules between requests', function (): void {
    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertOk();

    expect(Cache::has(WorkspaceSecurityPolicy::networkRulesCacheKey($this->owner->workspace()->id)))->toBeTrue();
});

it('applies a new allowlist on the next request after the policy is saved', function (): void {
    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertOk();

    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => ['203.0.113.0/24'],
        'geo_mode' => 'off',
    ]);

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertForbidden();
});
