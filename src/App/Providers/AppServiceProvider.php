<?php

namespace App\Providers;

use App\Documents\Contracts\DocumentOCRInterface;
use App\Embeddings\Contracts\EmbeddingsProviderInterface;
use App\LLM\Contracts\LLMProviderInterface;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Agents\Models\ModelSetting;
use App\Marketing\Models\Post;
use Domain\Sources\Files\Models\SourceFile;
use Domain\Sources\Text\Models\SourceText;
use Domain\Sources\Web\Models\SourceWebsite;
use Domain\Sources\Youtube\Models\SourceYoutubeVideo;
use App\Auth\Models\User;
use Domain\Agents\Observers\AgentFunctionObserver;
use Domain\Agents\Observers\AgentMcpServerObserver;
use Domain\Agents\Observers\AgentObserver;
use Domain\Sources\Text\Observers\AgentTextObserver;
use Domain\Sources\Web\Observers\AgentWebsiteObserver;
use Domain\Chat\Observers\ChatConversationObserver;
use Domain\Chat\Observers\ChatMessageObserver;
use Domain\Chat\Observers\ChatSessionObserver;
use Domain\Agents\Observers\ModelSettingObserver;
use App\Marketing\Observers\PostObserver;
use Domain\Sources\Files\Observers\SourceFileObserver;
use Domain\Sources\Youtube\Observers\SourceYoutubeVideoObserver;
use App\Auth\Observers\UserObserver;
use App\Embeddings\Cohere;
use App\Embeddings\Jina;
use App\Embeddings\Qwen3;
use App\LLM\Groq;
use App\LLM\Mistral;
use App\LLM\OpenRouter;
use App\LLM\Scaleway;
use Domain\Ingestion\Services\Clients\MistralOCR;
use App\Teams\Services\TeamService;
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
        // Tool dispatcher: routes by tool name to either the webhook
        // (AgentFunctions) or MCP backend.
        $this->app->bind(
            \Domain\Chat\Services\Tooling\ToolDispatcherInterface::class,
            \Domain\Chat\Services\Tooling\RoutingToolDispatcher::class,
        );

        // Bind the LLM provider interface based on configuration
        $this->app->bind(LLMProviderInterface::class, function ($app) {
            $provider = config('ai.provider', 'openrouter');

            return match ($provider) {
                'groq' => $app->make(Groq::class),
                'mistral' => $app->make(Mistral::class),
                'scaleway' => $app->make(Scaleway::class),
                default => $app->make(OpenRouter::class),
            };
        });

        // Bind the Embeddings provider interface based on configuration
        $this->app->bind(EmbeddingsProviderInterface::class, function ($app) {
            $provider = config('ai.models.embedding', 'mistral');

            return match ($provider) {
                'jina' => $app->make(Jina::class),
                'qwen' => $app->make(Qwen3::class),
                'mistral' => $app->make(\App\Embeddings\Mistral::class),
                default => $app->make(Cohere::class),
            };
        });

        $this->app->bind(DocumentOCRInterface::class, function ($app) {
            return $app->make(MistralOCR::class);
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

        // Policies travel with their models — replace `\Models\` with
        // `\Policies\` in the FQCN to find the corresponding policy.
        // Examples:
        //   Domain\Agents\Models\Agent              → Domain\Agents\Policies\AgentPolicy
        //   Domain\Sources\Files\Models\SourceFile  → Domain\Sources\Files\Policies\SourceFilePolicy
        //   App\Teams\Models\Team                   → App\Teams\Policies\TeamPolicy
        Gate::guessPolicyNamesUsing(function (string $modelClass): ?string {
            if (! str_contains($modelClass, '\\Models\\')) {
                return null;
            }
            $policy = str_replace('\\Models\\', '\\Policies\\', $modelClass).'Policy';

            return class_exists($policy) ? $policy : null;
        });

        // Factories live flat at database/factories/ regardless of which
        // domain/module owns the model. Map any model's class basename to
        // Database\Factories\<Name>Factory.
        \Illuminate\Database\Eloquent\Factories\Factory::guessFactoryNamesUsing(
            fn (string $modelClass) => 'Database\\Factories\\'.class_basename($modelClass).'Factory'
        );

        // The inverse — given a factory class, find the matching model in
        // either the App-module or Domain-context layout. Factories declare
        // their model implicitly via their basename; we search the known
        // locations under App\* and Domain\* for a class match.
        \Illuminate\Database\Eloquent\Factories\Factory::guessModelNamesUsing(function ($factory) {
            $base = preg_replace('/Factory$/', '', class_basename($factory));
            $candidates = [
                "App\\Auth\\Models\\$base",
                "App\\Billing\\Models\\$base",
                "App\\Teams\\Models\\$base",
                "App\\Marketing\\Models\\$base",
                "App\\Helpdesk\\Models\\$base",
                "App\\Changelog\\Models\\$base",
                "App\\Country\\Models\\$base",
                "App\\Language\\Models\\$base",
                "Domain\\Agents\\Models\\$base",
                "Domain\\Chat\\Models\\$base",
                "Domain\\Connectors\\Models\\$base",
                "Domain\\Ingestion\\Models\\$base",
                "Domain\\Sources\\Files\\Models\\$base",
                "Domain\\Sources\\Text\\Models\\$base",
                "Domain\\Sources\\Web\\Models\\$base",
                "Domain\\Sources\\QA\\Models\\$base",
                "Domain\\Sources\\Youtube\\Models\\$base",
            ];
            foreach ($candidates as $fqcn) {
                if (class_exists($fqcn)) {
                    return $fqcn;
                }
            }

            return "App\\Models\\$base";
        });

        User::observe(UserObserver::class);
        Agent::observe(AgentObserver::class);
        AgentFunction::observe(AgentFunctionObserver::class);
        AgentMcpServer::observe(AgentMcpServerObserver::class);
        SourceFile::observe(SourceFileObserver::class);
        SourceText::observe(AgentTextObserver::class);
        SourceWebsite::observe(AgentWebsiteObserver::class);
        SourceYoutubeVideo::observe(SourceYoutubeVideoObserver::class);
        ModelSetting::observe(ModelSettingObserver::class);
        Post::observe(PostObserver::class);
        ChatSession::observe(ChatSessionObserver::class);
        ChatConversation::observe(ChatConversationObserver::class);
        ChatMessage::observe(ChatMessageObserver::class);

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
