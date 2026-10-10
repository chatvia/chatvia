<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

/**
 * The plans page and the billing overview send each plan's feature bullets as
 * generated items (a `billing.features.*` template key with its numbers, or a
 * free-text line), and the yearly saving as a whole-number percentage the
 * cards compute their "Save X%" badge from.
 */
beforeEach(function (): void {
    Cache::flush();

    config(['billing.enabled' => true, 'billing.currency_symbol' => '€']);
});

function plansPageOwner(): User
{
    $owner = User::factory()->workspace()->create();
    app(TeamService::class)->createDefaultTeam($owner);
    $owner->bootstrapCurrentTeam();

    return $owner->fresh();
}

it('sends every plan with its generated feature items and yearly saving', function (): void {
    $this->actingAs(plansPageOwner())
        ->get(route('billing.packages'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('billing/Plans')
            ->where('packages.0.id', 'starter')
            ->where('packages.0.features.monthly.0', ['key' => 'messages_per_month', 'params' => ['count' => 3000], 'text' => null])
            ->where('packages.0.features.yearly.0', ['key' => 'messages_per_year', 'params' => ['count' => 39000], 'text' => null])
            ->where('packages.0.features.monthly.7', ['key' => 'mcp', 'params' => [], 'text' => null])
            ->where('packages.0.monthly_savings.percentage', 20)
            ->where('packages.2.features.monthly.23', ['key' => 'custom_integrations', 'params' => [], 'text' => null])
            ->where('packages.3.features.monthly.0.key', 'messages_custom')
            ->where('packages.3.monthly_savings.percentage', 0)
            ->missing('packages.0.features_monthly')
            ->missing('packages.0.features_yearly')
            ->missing('billingConfig.ui')
        );
});

it('sends the current plan feature items on the billing overview', function (): void {
    $this->actingAs(plansPageOwner())
        ->getJson(route('billing.data'))
        ->assertOk()
        ->assertJsonPath('current_package.id', 'starter')
        ->assertJsonPath('current_package.features.monthly.0', ['key' => 'messages_per_month', 'params' => ['count' => 3000], 'text' => null])
        ->assertJsonPath('current_package.features.monthly.9.key', 'retention')
        ->assertJsonCount(10, 'current_package.features.yearly');
});
