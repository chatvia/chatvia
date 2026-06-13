<?php

namespace App\Providers;

use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator as AgentService;
use Domain\Billing\Models\UserBilling;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Enums\SecurityFeature;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Pennant\Feature;
use Laravel\Pennant\Middleware\EnsureFeaturesAreActive;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Stripe\Stripe;

/**
 * Framework-only concerns: request macros, rate limiters, Stripe, Cashier,
 * Scramble, morph map, gates, BunnyCDN driver, and the Microsoft socialite
 * extension. All domain wiring (observers, DI against contracts, event
 * listeners) lives in {@see DomainServiceProvider}.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRequestMacros();
    }

    public function boot(): void
    {
        $this->assertProductionSecurityConfig();
        $this->configurePasswordDefaults();
        $this->configureStripe();
        $this->enforceMorphMap();
        $this->configureModelStrictness();
        $this->configureCashier();
        $this->registerPromptViewNamespace();
        $this->configurePolicyResolution();
        $this->configureFactoryResolution();
        $this->forceHttpsInProduction();
        $this->defineGates();
        $this->defineFeatures();
        $this->extendSocialiteProviders();
        $this->configureRateLimiters();
        $this->configureScramble();
        $this->registerBunnyCdnDriver();
    }

    /**
     * Fail loudly (or warn) on security-critical config that is easy to ship
     * empty from the .env template. Runs only in production so local/dev and the
     * test suite are unaffected.
     */
    private function assertProductionSecurityConfig(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        // HARD requirement: an unset Stripe webhook secret silently drops
        // Cashier's signature verification, turning /stripe/webhook into an
        // unauthenticated endpoint that grants subscriptions/credits from forged
        // events. Refuse to boot rather than run a forgeable billing webhook.
        // (TRUSTED_PROXIES is validated in bootstrap/app.php where it is read; it
        // has no config mirror, so it cannot be re-checked cache-safely here.)
        if (blank(config('cashier.webhook.secret'))) {
            throw new \RuntimeException(
                'STRIPE_WEBHOOK_SECRET is not set. Refusing to boot in production: '
                .'the Stripe webhook would accept unsigned, forgeable events.'
            );
        }
    }

    /**
     * Centralised password policy consumed by every Password::defaults() /
     * Password::default() call site (registration, reset, invitations,
     * set-password, settings). Production demands long, mixed, uncompromised
     * passwords; everywhere else stays at min(8) so local logins and the test
     * suite keep working. uncompromised() calls the haveibeenpwned.com range
     * API, which is another reason to keep it production-only.
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8));
    }

    private function registerRequestMacros(): void
    {
        Request::macro('subdomain', function () {
            $host = $this->getHost();
            $dots = substr_count($host, '.');

            if ($dots === 2 || str_contains($host, '.localhost')) {
                return explode('.', $host)[0];
            }

            return null;
        });

        Request::macro('domain', function () {
            $host = parse_url($this->getSchemeAndHttpHost(), PHP_URL_HOST);

            preg_match('/([a-z0-9\-]+\.[a-z]{2,})$/i', $host, $matches);

            return $matches[1] ?? $host.($this->getPort() === 80 ? '' : ':'.$this->getPort());
        });

        Request::macro('agentSlug', function () {
            $agent = $this->query('agent');

            if (is_string($agent) && $agent !== '') {
                return $agent;
            }

            // Custom domain: if the request host isn't ours, try the
            // `agents.domain` mapping. AgentService validates the owner
            // still has Custom Domain quota — cancelled subscriptions or
            // downgraded plans stop serving once the host cache expires.
            $host = $this->getHost();
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            $isOurHost = $host === $appHost
                || ($appHost && str_ends_with($host, '.'.$appHost))
                || str_ends_with($host, '.localhost')
                || $host === 'localhost';

            if (! $isOurHost) {
                $resolved = AgentService::resolveByHost($host);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            $sub = $this->subdomain();

            if ($sub !== null && $sub !== 'www') {
                return $sub;
            }

            return $this->hasSession() ? $this->session()->get('chat_agent') : null;
        });
    }

    private function configureStripe(): void
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    // Short morph keys instead of FQCNs. Saves ~50 bytes per row on chunks
    // (billions of rows = real disk) and decouples persisted data from
    // namespace renames.
    private function enforceMorphMap(): void
    {
        Relation::enforceMorphMap([
            'agent' => Agent::class,
            'user' => User::class,
            'source_file' => SourceFile::class,
            'source_website' => SourceWebsite::class,
            'source_text' => SourceText::class,
            'source_youtube_video' => SourceYoutubeVideo::class,
            'agent_website_url' => AgentWebsiteUrl::class,
        ]);
    }

    // Catch lazy loading in dev/test — N+1s should fail loudly in CI
    // rather than ship to production. Disabled in production so a missed
    // `with(...)` still serves traffic.
    private function configureModelStrictness(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
    }

    // The `Billable` trait lives on `UserBilling`, not `User`, so Cashier
    // resolves customers (e.g. `Cashier::findBillable($stripeId)`) to a
    // `UserBilling` row. The owning `User` is reachable via
    // `$billing->user` / `User::billing()` HasOne.
    private function configureCashier(): void
    {
        Cashier::useCustomerModel(UserBilling::class);
    }

    // Prompts are Blade views (markdown + Blade) loaded via the `prompt()`
    // helper, e.g. prompt('knowledge/system') → resources/prompts/knowledge/system.blade.php.
    private function registerPromptViewNamespace(): void
    {
        View::addNamespace('prompts', resource_path('prompts'));
    }

    // Policies travel with their models — replace `\Models\` with
    // `\Policies\` in the FQCN to find the corresponding policy.
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

    // Models live across many App\* and Domain\* namespaces, but every
    // factory lives flat under Database\Factories\. Map a model to its
    // factory by basename.
    private function configureFactoryResolution(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelClass): string => 'Database\\Factories\\'.class_basename($modelClass).'Factory'
        );
    }

    private function forceHttpsInProduction(): void
    {
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function defineGates(): void
    {
        Gate::define('viewPulse', fn (User $user): bool => $user->isAdmin());

        Gate::define('access-agent', function (User $user, Agent $agent) {
            if ($user->isAdmin()) {
                return true;
            }

            if ($user->isWorkspace()) {
                return $agent->user_id === $user->id;
            }

            // Employee users: check team-based access with permissions.
            // Use the user's current_team_id from the database (works in all
            // contexts including broadcasting). Read-only — Gate definitions
            // must not persist during access checks.
            $team = $user->currentTeam ?? $user->currentOrPrimaryTeam();

            if (! $team) {
                return false;
            }

            return app(TeamService::class)->canAccessAgent($user, $team, $agent);
        });
    }

    /**
     * Laravel Pennant feature flags for entitlement-gated suites. The `security`
     * flag resolves to: platform-admin bypass, then the per-workspace admin
     * override (`WorkspaceSecurityPolicy.security_override` — true force-on,
     * false force-off, so staff can grant/revoke the suite for ANY workspace
     * à-la-carte), then the workspace's LIVE plan capability. The `array` store
     * (see config/pennant.php) recomputes per request, so the flag always
     * reflects the current plan AND the current override with no stale cache to
     * purge — the override persists in our own table, not Pennant's store.
     *
     * The override is read without `firstOrCreate` so merely checking the flag
     * (e.g. the Inertia share) never writes a policy row. Pennant memoizes the
     * resolved value per scope per request, so this is one query per request.
     *
     * Gate routes with `features:security`; the failed-check response is a 403
     * (Pennant defaults to 400) to match the rest of the access layer.
     */
    private function defineFeatures(): void
    {
        Feature::define(SecurityFeature::Security->value, function (?User $user): bool {
            if (! $user instanceof User) {
                return false;
            }

            if ($user->isAdmin()) {
                return true;
            }

            $workspace = $user->workspace();

            // Read through the model so the boolean cast applies (raw value()
            // can yield Postgres 't'/'f' strings, and (bool) 'f' === true).
            $override = WorkspaceSecurityPolicy::query()
                ->where('workspace_id', $workspace->id)
                ->first(['security_override'])?->security_override;

            return $override ?? $workspace->capabilities->security;
        });

        EnsureFeaturesAreActive::whenInactive(
            fn ($request, array $features) => abort(403, "Your plan doesn't include ".SecurityFeature::Security->label().'.'),
        );
    }

    private function extendSocialiteProviders(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });
    }

    private function configureRateLimiters(): void
    {
        // Bulk gate for every /widget/* endpoint. Per-IP because widget
        // traffic is unauthenticated for public agents and we want a single
        // ceiling regardless of how many sessions an IP spins up.
        RateLimiter::for('widgets', fn ($request) => Limit::perMinute(90)->by($request->ip()));

        // Stream endpoint specifically — every call costs an LLM invocation
        // billed to the agent's OWNING workspace, so throttling matters for both
        // abuse and the victim tenant's spend. The per-session/per-IP key alone
        // is forgeable (a client mints unlimited X-SESSION-IDs and can proxy
        // across IPs), so we stack a GLOBAL per-agent ceiling keyed on the
        // server-resolved agent — independent of any client-supplied value — so
        // an attacker cannot multiply their LLM budget against a public agent
        // (financial DoS: draining the owner's message quota + triggering
        // auto-recharge). Applies to /widget/stream and /widget/.../attachments.
        RateLimiter::for('widget-stream', function ($request) {
            $sessionKey = $request->header('X-SESSION-ID') ?: $request->ip();

            $limits = [
                Limit::perMinute(20)->by('widget-stream|sess|'.$sessionKey),
            ];

            $agent = current_agent_from_widget();

            if ($agent) {
                $limits[] = Limit::perMinute((int) config('chatvia.widget.stream_per_agent_per_minute', 120))
                    ->by('widget-stream|agent|'.$agent->id);
                $limits[] = Limit::perHour((int) config('chatvia.widget.stream_per_agent_per_hour', 1500))
                    ->by('widget-stream|agent-hour|'.$agent->id);
            }

            return $limits;
        });

        // Auth exchange runs once per page load for protected widgets. A
        // tight per-IP cap removes JWT-verification brute force as a DoS
        // surface (defence in depth — verify() is already constant-time).
        RateLimiter::for(
            'widget-exchange',
            fn ($request) => Limit::perMinute(10)->by('widget-exchange|'.$request->ip()),
        );

        RateLimiter::for('api', fn ($request) => $request->user()
            ? Limit::perMinute(300)->by($request->user()->getAuthIdentifier().'|'.$request->user()::class)
            : Limit::perMinute(60)->by($request->ip()));

        // Built-in MCP endpoints are only reachable by our own signed tool
        // loop; key the backstop by the calling agent so one runaway agent
        // loop can't exhaust the budget for every other agent.
        RateLimiter::for('builtin-mcp', fn ($request) => Limit::perMinute(120)
            ->by('builtin-mcp|'.((string) $request->header('X-Chatvia-Agent-Id') ?: $request->ip())));

        // Hosted-chat LLM-cost endpoints (stream, transcription, regenerate,
        // attachment upload). Keyed by the authenticated chatter, IP fallback.
        RateLimiter::for('chat-stream', function ($request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(30)->by('chat-stream|'.$key);
        });
    }

    // Scramble — scope OpenAPI discovery to /api/v1/* and declare the
    // bearer token as the single security requirement.
    private function configureScramble(): void
    {
        Scramble::routes(fn (Route $route): bool => str_starts_with($route->uri, 'api/v1/'));
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(
                SecurityScheme::http('bearer', 'Bearer'),
            );
        });
    }

    private function registerBunnyCdnDriver(): void
    {
        Storage::extend('bunnycdn', function ($app, $config) {
            $bunnyConfig = config('filesystems.disks.bunnycdn');

            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    $bunnyConfig['storage_zone'],
                    $bunnyConfig['api_key'],
                    $bunnyConfig['region'],
                ),
                $bunnyConfig['pull_zone'],
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }
}
