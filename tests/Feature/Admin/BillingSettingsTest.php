<?php

use App\Admin\Settings\AdminSetting;
use App\Admin\Settings\SettingGroup;
use App\Admin\Settings\SettingsStore;
use App\Providers\AdminSettingsServiceProvider;
use App\Providers\AppServiceProvider;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Services\Entitlements\StripeEntitlements;
use Domain\Billing\Services\Entitlements\UnlimitedEntitlements;
use Laravel\Cashier\Cashier;
use Tests\Support\ReregisterProviders;

/**
 * Billing is set in the admin panel (Settings → Billing), not in .env. The
 * saved values reach the same config keys as before, as their PHP types, and
 * early enough for the decisions made before the providers boot (Cashier's
 * routes). With nothing saved, a new install runs free: paid plans off and
 * sign-up open.
 */
function applyBillingSettings(?array $payload): SettingsStore
{
    AdminSetting::forGroup(SettingGroup::Billing)->update(['payload' => $payload]);

    app()->forgetInstance(SettingsStore::class);
    $store = app(SettingsStore::class);
    $store->apply();

    return $store;
}

describe('with nothing saved', function (): void {
    it('runs free with open sign-up and the old defaults', function (): void {
        applyBillingSettings(null);

        expect(config('billing.enabled'))->toBeFalse()
            ->and(config('billing.registration_enabled'))->toBeTrue()
            ->and(config('billing.trial.days'))->toBe(7)
            ->and(config('billing.currency'))->toBe('eur')
            ->and(config('cashier.currency'))->toBe('eur')
            ->and(config('billing.currency_symbol'))->toBe('€')
            ->and(config('billing.unlimited_retention_days'))->toBe(366)
            ->and(config('billing.discount'))->toBe(['percentage' => 0, 'ends_at' => null])
            ->and(config('billing.addons.auto_recharge_credits.max_per_day'))->toBe(3)
            ->and(app(Entitlements::class))->toBeInstanceOf(UnlimitedEntitlements::class);
    });
});

describe('saved values', function (): void {
    it('reach config as their PHP types', function (): void {
        applyBillingSettings([
            'enabled' => '1',
            'registration_enabled' => '0',
            'trial_days' => '14',
            'currency' => 'usd',
            'unlimited_retention_days' => '90',
            'discount_percentage' => '20',
            'discount_ends_at' => '2026-12-31',
            'auto_recharge_max_per_day' => '5',
        ]);

        expect(config('billing.enabled'))->toBeTrue()
            ->and(config('billing.registration_enabled'))->toBeFalse()
            ->and(config('billing.trial.days'))->toBe(14)
            ->and(config('billing.currency'))->toBe('usd')
            ->and(config('cashier.currency'))->toBe('usd')
            ->and(config('billing.currency_symbol'))->toBe('$')
            ->and(config('billing.unlimited_retention_days'))->toBe(90)
            ->and(config('billing.discount'))->toBe(['percentage' => 20, 'ends_at' => '2026-12-31'])
            ->and(config('billing.addons.auto_recharge_credits.max_per_day'))->toBe(5)
            ->and(app(Entitlements::class))->toBeInstanceOf(StripeEntitlements::class);
    });

    it('turns billing off with a saved "0", not the string', function (): void {
        applyBillingSettings(['enabled' => '0']);

        expect(config('billing.enabled'))->toBeFalse();
    });

    it('opens sign-up by default only while paid plans are off', function (?string $enabled, ?string $registration, bool $open): void {
        applyBillingSettings(array_filter(
            ['enabled' => $enabled, 'registration_enabled' => $registration],
            fn (?string $value): bool => $value !== null,
        ));

        expect(config('billing.registration_enabled'))->toBe($open);
    })->with([
        'paid plans on' => ['1', null, false],
        'paid plans off' => ['0', null, true],
        'on, sign-up opened' => ['1', '1', true],
        'off, sign-up closed' => ['0', '0', false],
    ]);

    it('uses the default for a value that is not valid', function (): void {
        applyBillingSettings(['trial_days' => 'soon', 'currency' => 'xyz', 'discount_percentage' => '150', 'discount_ends_at' => '2026-02-30']);

        expect(config('billing.trial.days'))->toBe(7)
            ->and(config('billing.currency'))->toBe('eur')
            ->and(config('billing.discount'))->toBe(['percentage' => 0, 'ends_at' => null]);
    });

    it('derives the currency symbol from the currency', function (string $currency, string $symbol): void {
        applyBillingSettings(['currency' => $currency]);

        expect(config('billing.currency_symbol'))->toBe($symbol);
    })->with([
        ['eur', '€'],
        ['gbp', '£'],
        ['sek', 'SEK'],
    ]);

    it('reaches a queued job after a change in the panel', function (): void {
        applyBillingSettings(['enabled' => '1']);

        AdminSetting::forGroup(SettingGroup::Billing)->update(['payload' => ['enabled' => '0']]);

        dispatch(function (): void {
            cache()->put('billing-enabled-seen-by-job', config('billing.enabled'));
        });

        expect(cache('billing-enabled-seen-by-job'))->toBeFalse();
    });
});

describe('applying before the providers boot', function (): void {
    it('decides the Cashier routes and the Entitlements driver from the saved setting', function (bool $enabled): void {
        AdminSetting::forGroup(SettingGroup::Billing)->update(['payload' => ['enabled' => $enabled ? '1' : '0']]);
        config(['billing.enabled' => ! $enabled]);

        try {
            Cashier::$registersRoutes = true;

            ReregisterProviders::andRunBootingCallbacks(AdminSettingsServiceProvider::class, AppServiceProvider::class);

            expect(config('billing.enabled'))->toBe($enabled)
                ->and(Cashier::$registersRoutes)->toBe($enabled)
                ->and(app(Entitlements::class))->toBeInstanceOf($enabled ? StripeEntitlements::class : UnlimitedEntitlements::class);
        } finally {
            Cashier::$registersRoutes = true;
        }
    })->with([
        'saved on' => true,
        'saved off' => false,
    ]);

    it('registers the admin settings provider before the other app providers', function (): void {
        $providers = require base_path('bootstrap/providers.php');

        expect($providers[0])->toBe(AdminSettingsServiceProvider::class)
            ->and(array_search(AppServiceProvider::class, $providers, true))->toBeGreaterThan(0);
    });
});
