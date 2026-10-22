<?php

namespace App\Providers;

use App\Identity\ApiKey\Models\PersonalAccessToken;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use App\Identity\Workspace\Services\StaffUserProvider;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use Laravel\Sanctum\Sanctum;

/**
 * Authorization wiring: how policies are found, the app's Gate abilities, the
 * staff user provider and the API token model and check.
 *
 *  - `access-agent`: may the user work on an agent from the dashboard
 *    ({@see AgentAccessPolicy::canManage()}).
 *  - The `staff` user provider lowers the email before a lookup
 *    ({@see StaffUserProvider}).
 *  - API tokens use {@see PersonalAccessToken}, which skips most
 *    `last_used_at` writes.
 *  - API tokens of a deactivated account stop working.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configurePolicyResolution();
        $this->defineGates();
        $this->registerStaffUserProvider();
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        $this->rejectTokensOfDeactivatedAccounts();
    }

    /**
     * The `staff` driver of the `users` provider (config/auth.php).
     */
    private function registerStaffUserProvider(): void
    {
        Auth::provider('staff', fn (Application $app, array $config): StaffUserProvider => new StaffUserProvider(
            $app->make('hash'),
            $config['model'],
        ));
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
    }

    /**
     * Deactivating an account (or the workspace it belongs to, which cascades
     * to every member) also switches off its API tokens; the session logins
     * already refuse it.
     */
    private function rejectTokensOfDeactivatedAccounts(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            fn (SanctumPersonalAccessToken $token, bool $isValid): bool => $isValid
                && (! $token->tokenable instanceof User || ! $token->tokenable->isDeactivated()),
        );
    }
}
