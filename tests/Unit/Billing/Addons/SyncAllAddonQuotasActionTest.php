<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Addons\SyncAllAddonQuotasAction;

it('rebuilds the add-on quotas of every workspace with an add-on subscription', function (): void {
    $withAddon = User::factory()->workspace()->create();
    $withAddon->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'extra_agents',
        'stripe_id' => 'sub_extra_agents',
        'stripe_status' => 'active',
        'stripe_price' => 'price_extra_agent',
        'quantity' => 4,
    ]);

    $withoutAddon = User::factory()->workspace()->create();
    $withoutAddon->billing()->firstOrCreate([]);

    expect(app(SyncAllAddonQuotasAction::class)->execute())->toBeGreaterThanOrEqual(1)
        ->and($withAddon->fresh()->quota->extra_agents_limit)->toBe(4);
});
