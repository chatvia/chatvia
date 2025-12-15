<?php

namespace App\Providers;

use App\Contracts\EmbeddingsProviderInterface;
use App\Contracts\LLMProviderInterface;
use App\Models\Agent;
use App\Models\ModelSetting;
use App\Models\Post;
use App\Models\SourceFile;
use App\Models\SourceText;
use App\Models\SourceWebsite;
use App\Models\SourceYoutubeVideo;
use App\Models\User;
use App\Observers\AgentObserver;
use App\Observers\AgentTextObserver;
use App\Observers\AgentWebsiteObserver;
use App\Observers\ModelSettingObserver;
use App\Observers\PostObserver;
use App\Observers\SourceFileObserver;
use App\Observers\SourceYoutubeVideoObserver;
use App\Observers\UserObserver;
use App\Services\Infrastructure\Embeddings\Cohere;
use App\Services\Infrastructure\Embeddings\Jina;
use App\Services\Infrastructure\Embeddings\Qwen3;
use App\Services\Infrastructure\LLM\Groq;
use App\Services\Infrastructure\LLM\OpenRouter;
use App\Services\Infrastructure\LLM\Scaleway;
use App\Services\Teams\TeamService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use League\Flysystem\Filesystem;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNAdapter;
use PlatformCommunity\Flysystem\BunnyCDN\BunnyCDNClient;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\Provider as MicrosoftProvider;
use Stripe\Stripe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the LLM provider interface based on configuration
        $this->app->bind(LLMProviderInterface::class, function ($app) {
            $provider = config('ai.provider', 'openrouter');

            return match ($provider) {
                'groq' => $app->make(Groq::class),
                'scaleway' => $app->make(Scaleway::class),
                default => $app->make(OpenRouter::class),
            };
        });

        // Bind the Embeddings provider interface based on configuration
        $this->app->bind(EmbeddingsProviderInterface::class, function ($app) {
            $provider = config('ai.models.embedding', 'cohere');

            return match ($provider) {
                'jina' => $app->make(Jina::class),
                'qwen' => $app->make(Qwen3::class),
                default => $app->make(Cohere::class),
            };
        });

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //        Cashier::calculateTaxes();

        //        Cashier::keepIncompleteSubscriptionsActive();

        Stripe::setApiKey(config('cashier.secret'));

        User::observe(UserObserver::class);
        Agent::observe(AgentObserver::class);
        SourceFile::observe(SourceFileObserver::class);
        SourceText::observe(AgentTextObserver::class);
        SourceWebsite::observe(AgentWebsiteObserver::class);
        SourceYoutubeVideo::observe(SourceYoutubeVideoObserver::class);
        ModelSetting::observe(ModelSettingObserver::class);
        Post::observe(PostObserver::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::define('viewPulse', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('access-agent', function (User $user, Agent $agent) {
            if ($user->isAdmin()) {
                return true;
            }

            if ($user->isCompany()) {
                return $agent->user_id === $user->id;
            }

            // Staff users: check team-based access with permissions
            // Use the user's current_team_id from the database (works in all contexts including broadcasting)
            $team = $user->currentTeam ?? $user->ensureCurrentTeam();

            if (! $team) {
                return false;
            }

            // Use TeamService to check if staff user can access this agent
            // This checks: agent belongs to team + user has correct permissions
            return app(TeamService::class)->canAccessAgent($user, $team, $agent);
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

        RateLimiter::for('widgets', function ($request) {
            return Limit::perMinute(90)->by($request->ip());
        });

        Storage::extend('bunnycdn', function ($app, $config) {
            $bunnyConfig = config('filesystems.disks.bunnycdn');

            $adapter = new BunnyCDNAdapter(
                new BunnyCDNClient(
                    $bunnyConfig['storage_zone'],
                    $bunnyConfig['api_key'],
                    $bunnyConfig['region']
                ),
                $bunnyConfig['pull_zone']
            );

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
