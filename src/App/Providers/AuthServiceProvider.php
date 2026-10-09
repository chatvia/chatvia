<?php

namespace App\Providers;

use App\Admin\StaffToolsAccess;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Authorization wiring: how policies are found, the app's Gate abilities, and
 * the extra check on API tokens.
 *
 *  - `access-agent`: may the user work on an agent from the dashboard
 *    ({@see AgentAccessPolicy::canManage()}).
 *  - `viewPulse` / `viewSpectra` (and Horizon and Telescope in their own
 *    providers): the staff tools, open only to an admin signed in to the
 *    admin panel ({@see StaffToolsAccess}).
 *  - API tokens of a deactivated account stop working.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configurePolicyResolution();
        $this->defineGates();
        $this->rejectTokensOfDeactivatedAccounts();
    }

    /**
     * Policies travel with their models: replace `\Models\` with `\Policies\`
     * in the model's class name to find its policy.
     */
    private function configurePolicyResolution(): void
    {
        Gate::guessPolicyNamesUsing(function (string $modelClass): ?string {
            if (! str_contains($modelClass, '\\Models\\')) {
                return null;
            }

            $policy = str_replace('\\Models\\', '\\Policies\\', $modelClass).'Policy';

            return class_exists($policy) ? $policy : null;
        });
    }

    private function defineGates(): void
    {
        Gate::define(
            'access-agent',
            fn (User $user, Agent $agent): bool => $this->app->make(AgentAccessPolicy::class)->canManage($user, $agent),
        );

        // The web-guard user is ignored on purpose: staff tools need the admin
        // panel's own session.
        foreach (['viewPulse', 'viewSpectra'] as $ability) {
            Gate::define($ability, fn (?User $user = null): bool => $this->app->make(StaffToolsAccess::class)->allows());
        }
    }

    /**
     * Deactivating an account (or the workspace it belongs to, which cascades
     * to every member) also switches off its API tokens; the session logins
     * already refuse it.
     */
    private function rejectTokensOfDeactivatedAccounts(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
                && (! $token->tokenable instanceof User || ! $token->tokenable->isDeactivated()),
        );
    }
}
