<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Enums\SecurityFeature;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * The Enterprise Security suite is gated by a Laravel Pennant feature flag whose
 * resolver reads the workspace's LIVE plan entitlement — active for anyone whose
 * plan includes it, plus a platform-admin bypass. Resolved per request (array
 * store), so it always reflects the current plan with no cache to purge.
 */
function enterpriseUser(): User
{
    $priceId = 'price_test_ent_'.Str::random(6);
    updateBillingPlan('enterprise', ['stripe_monthly_price_id' => $priceId]);

    $user = User::factory()->create();
    $user->billing()->firstOrCreate([]);
    $user->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);
    $user->refresh();

    return $user;
}

it('activates the security feature for an enterprise workspace', function (): void {
    $user = enterpriseUser();

    expect(Feature::for($user)->active(SecurityFeature::Security->value))->toBeTrue();
});

function proUser(): User
{
    $priceId = 'price_test_pro_'.Str::random(6);
    updateBillingPlan('pro', ['stripe_monthly_price_id' => $priceId]);

    $user = User::factory()->create();
    $user->billing()->firstOrCreate([]);
    $user->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);
    $user->refresh();

    return $user;
}

it('activates the security feature for a pro workspace', function (): void {
    expect(Feature::for(proUser())->active(SecurityFeature::Security->value))->toBeTrue();
});

it('leaves the security feature inactive for a non-enterprise workspace', function (): void {
    $user = User::factory()->create();

    expect(Feature::for($user)->active(SecurityFeature::Security->value))->toBeFalse();
});

it('grants platform admins the security feature regardless of plan', function (): void {
    $admin = User::factory()->admin()->create();

    expect(Feature::for($admin)->active(SecurityFeature::Security->value))->toBeTrue();
});

it('inherits the workspace entitlement for an employee', function (): void {
    $workspace = enterpriseUser();
    $employee = User::factory()->employee($workspace)->create();

    expect(Feature::for($employee)->active(SecurityFeature::Security->value))->toBeTrue();
});

it('lets an admin force-grant the suite to a non-enterprise workspace', function (): void {
    $user = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($user->id)->update(['security_override' => true]);

    expect(Feature::for($user->fresh())->active(SecurityFeature::Security->value))->toBeTrue();
});

it('lets an admin force-revoke the suite from an enterprise workspace', function (): void {
    $user = enterpriseUser();
    WorkspaceSecurityPolicy::forWorkspace($user->id)->update(['security_override' => false]);

    expect(Feature::for($user->fresh())->active(SecurityFeature::Security->value))->toBeFalse();
});
