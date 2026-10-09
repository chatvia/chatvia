<?php

namespace App\Providers;

use App\Identity\Workspace\Models\User;
use Domain\Security\Enums\SecurityFeature;
use Domain\Security\Services\SecurityFeatureResolver;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;

/**
 * Laravel Pennant feature flags for plan-gated suites. The `array` store (see
 * config/pennant.php) resolves each flag once per request, so it always
 * follows the current plan and staff override with no stored value to purge.
 *
 * Routes gate with `features:security`; a failed check answers 403 (Pennant's
 * default is 400) to match the rest of the access layer.
 */
class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Feature::define(
            SecurityFeature::Security->value,
            fn (?User $user): bool => $this->app->make(SecurityFeatureResolver::class)->isActiveFor($user),
        );

        EnsureFeaturesAreActive::whenInactive(
            fn ($request, array $features) => abort(403, __("Your plan doesn't include :feature.", ['feature' => SecurityFeature::Security->label()])),
        );
    }
}
