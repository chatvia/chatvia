<?php

namespace App\Providers;

use App\Workspace\Listeners\RecordKnownDeviceListener;
use App\Workspace\Models\User;
use App\Workspace\Observers\UserObserver;
use App\Billing\Models\UserBilling;
use App\Documents\Contracts\DocumentOCRInterface;
use App\Embedding\Cohere;
use App\Embedding\Contracts\EmbeddingsProviderInterface;
use App\Embedding\Jina;
use App\Embedding\Qwen3;
use App\LLM\Contracts\LLMProviderInterface;
use App\LLM\Groq;
use App\LLM\Mistral;
use App\LLM\OpenRouter;
use App\LLM\Scaleway;
use App\Marketing\Models\Post;
use App\Marketing\Observers\PostObserver;
use App\Team\Services\TeamService;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Domain\Agent\Models\Agent;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Agent\Models\ModelSetting;
use Domain\Mcp\Observers\AgentMcpServerObserver;
use Domain\Agent\Observers\AgentObserver;
use Domain\Agent\Observers\ModelSettingObserver;
use Domain\Function\Models\AgentFunction;
use Domain\Function\Observers\AgentFunctionObserver;
use Domain\Agent\Services\Agent as AgentService;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Listeners\TriggerAutoRecharge;
use Domain\Member\Events\AgentMemberInvited;
use Domain\Member\Listeners\SendAgentMemberInvitationEmail;
use Domain\Analytics\Listeners\RecordAnalyticsListener;
use App\Workspace\Events\MagicLinkRequested;
use App\Workspace\Listeners\SendMagicLinkEmail;
use App\Workspace\Services\PasswordAuthenticator;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Observers\ChatConversationObserver;
use Domain\Chat\Observers\ChatMessageObserver;
use Domain\Chat\Observers\ChatSessionObserver;
use Domain\Chat\Services\Tooling\Dispatcher\RoutingToolDispatcher;
use Domain\Chat\Services\Tooling\Dispatcher\ToolDispatcherInterface;
use Domain\Ingestion\Services\Clients\MistralOCR;
use Domain\Source\File\Models\SourceFile;
use Domain\Source\File\Observers\SourceFileObserver;
use Domain\Source\Text\Models\SourceText;
use Domain\Source\Text\Observers\AgentTextObserver;
use Domain\Source\Web\Models\AgentWebsiteUrl;
use Domain\Source\Web\Models\SourceWebsite;
use Domain\Source\Web\Observers\AgentWebsiteObserver;
use Domain\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Source\Youtube\Observers\SourceYoutubeVideoObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
            ToolDispatcherInterface::class,
            RoutingToolDispatcher::class,
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
                'mistral' => $app->make(\App\Embedding\Mistral::class),
                default => $app->make(Cohere::class),
            };
        });

        $this->app->bind(DocumentOCRInterface::class, function ($app) {
            return $app->make(MistralOCR::class);
        });

        // PasswordAuthenticator wraps the `web` guard so domain Actions don't
        // have to reach for the Auth facade. We bind it explicitly because
        // StatefulGuard has multiple implementations and Laravel can't auto-
        // resolve which one we want.
        $this->app->bind(PasswordAuthenticator::class, function (): PasswordAuthenticator {
            return new PasswordAuthenticator(Auth::guard('web'));
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

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //        Cashier::calculateTaxes();

        //        Cashier::keepIncompleteSubscriptionsActive();

        Stripe::setApiKey(config('cashier.secret'));

        // Short morph keys instead of FQCNs. Saves ~50 bytes per row on chunks
        // (billions of rows = real disk) and decouples persisted data from
        // namespace renames.
        Relation::enforceMorphMap([
            'agent' => Agent::class,
            'user' => User::class,
            'source_file' => SourceFile::class,
            'source_website' => SourceWebsite::class,
            'source_text' => SourceText::class,
            'source_youtube_video' => SourceYoutubeVideo::class,
            'agent_website_url' => AgentWebsiteUrl::class,
        ]);

        // Catch lazy loading in dev/test — N+1s should fail loudly in CI
        // rather than ship to production. Disabled in production so a missed
        // `with(...)` still serves traffic.
        Model::shouldBeStrict(! app()->isProduction());

        // The `Billable` trait lives on `UserBilling`, not `User`, so Cashier
        // resolves customers (e.g. `Cashier::findBillable($stripeId)`) to a
        // `UserBilling` row. The owning `User` is reachable via
        // `$billing->user` / `User::billing()` HasOne.
        Cashier::useCustomerModel(UserBilling::class);

        // Prompts are Blade views (markdown + Blade) loaded via the `prompt()`
        // helper, e.g. prompt('knowledge/system') → resources/prompts/knowledge/system.blade.php.
        View::addNamespace('prompts', resource_path('prompts'));

        // Policies travel with their models — replace `\Models\` with
        // `\Policies\` in the FQCN to find the corresponding policy.
        // Examples:
        //   Domain\Agent\Models\Agent              → Domain\Agent\Policies\AgentPolicy
        //   Domain\Source\File\Models\SourceFile  → Domain\Source\File\Policies\SourceFilePolicy
        //   App\Team\Models\Team                   → App\Team\Policies\TeamPolicy
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
        Factory::guessFactoryNamesUsing(
            fn (string $modelClass): string => 'Database\\Factories\\'.class_basename($modelClass).'Factory'
        );

        // The inverse — given a factory class, find the matching model in
        // either the App-module or Domain-context layout. Factories declare
        // their model implicitly via their basename; we search the known
        // locations under App\* and Domain\* for a class match.
        Factory::guessModelNamesUsing(function ($factory) {
            $base = preg_replace('/Factory$/', '', class_basename($factory));
            $candidates = [
                "App\\Workspace\\Models\\$base",
                "App\\Billing\\Models\\$base",
                "App\\Team\\Models\\$base",
                "App\\Marketing\\Models\\$base",
                "App\\Helpdesk\\Models\\$base",
                "App\\Changelog\\Models\\$base",
                "App\\Country\\Models\\$base",
                "App\\Language\\Models\\$base",
                "Domain\\Agent\\Models\\$base",
                "Domain\\Chat\\Models\\$base",
                "Domain\\Connector\\Models\\$base",
                "Domain\\Function\\Models\\$base",
                "Domain\\Ingestion\\Models\\$base",
                "Domain\\Lead\\Models\\$base",
                "Domain\\Llm\\Models\\$base",
                "Domain\\Mcp\\Models\\$base",
                "Domain\\Member\\Models\\$base",
                "Domain\\Prompt\\Models\\$base",
                "Domain\\Topic\\Models\\$base",
                "Domain\\Source\\File\\Models\\$base",
                "Domain\\Source\\Text\\Models\\$base",
                "Domain\\Source\\Web\\Models\\$base",
                "Domain\\Source\\QA\\Models\\$base",
                "Domain\\Source\\Youtube\\Models\\$base",
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

            if ($user->isWorkspace()) {
                return $agent->user_id === $user->id;
            }

            // Employee users: check team-based access with permissions
            // Use the user's current_team_id from the database (works in all contexts including broadcasting)
            $team = $user->currentTeam ?? $user->ensureCurrentTeam();

            if (! $team) {
                return false;
            }

            // Use TeamService to check if staff user can access this agent
            // This checks: agent belongs to team + user has correct permissions
            return app(TeamService::class)->canAccessAgent($user, $team, $agent);
        });

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('microsoft', MicrosoftProvider::class);
        });

        Event::listen(\Illuminate\Auth\Events\Login::class, RecordKnownDeviceListener::class);
        Event::listen(AgentMemberInvited::class, SendAgentMemberInvitationEmail::class);
        Event::listen(MagicLinkRequested::class, SendMagicLinkEmail::class);
        Event::listen(MessageCreditsLow::class, TriggerAutoRecharge::class);

        Event::subscribe(RecordAnalyticsListener::class);

        // Bulk gate for every /widget/* endpoint. Per-IP because widget
        // traffic is unauthenticated for public agents and we want a single
        // ceiling regardless of how many sessions an IP spins up.
        RateLimiter::for('widgets', function ($request) {
            return Limit::perMinute(90)->by($request->ip());
        });

        // Stream endpoint specifically — every call costs an LLM invocation,
        // so we throttle by session (or IP when there's no session) at a
        // much lower budget than the bulk widget gate.
        RateLimiter::for('widget-stream', function ($request) {
            $key = $request->header('X-SESSION-ID') ?: $request->ip();

            return Limit::perMinute(20)->by('widget-stream|'.$key);
        });

        // Auth exchange runs once per page load for protected widgets. A
        // tight per-IP cap removes JWT-verification brute force as a DoS
        // surface (defence in depth — verify() is already constant-time).
        RateLimiter::for('widget-exchange', function ($request) {
            return Limit::perMinute(10)->by('widget-exchange|'.$request->ip());
        });

        RateLimiter::for('api', function ($request) {
            return $request->user()
                ? Limit::perMinute(300)->by($request->user()->getAuthIdentifier().'|'.$request->user()::class)
                : Limit::perMinute(60)->by($request->ip());
        });

        // Scramble — scope OpenAPI discovery to /api/v1/* and declare the
        // Sanctum bearer token as the single security requirement.
        Scramble::routes(fn (Route $route): bool => str_starts_with($route->uri, 'api/v1/'));
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(
                SecurityScheme::http('bearer', 'Bearer'),
            );
        });

        // Parallel testing creates per-worker databases (testing_test_N) and
        // migrates them before the first test runs. Our schema relies on
        // PostgreSQL extensions (vector, vchord, vchord_bm25, pg_tokenizer,
        // pg_trgm) that aren't created by the migrations themselves, so we
        // install them on each fresh test DB before migrations execute.
        if ($this->app->environment('testing')) {
            ParallelTesting::setUpTestDatabaseBeforeMigrating(function (): void {
                $extensions = [
                    'vector',
                    'pg_trgm',
                    'pg_tokenizer',
                    'vchord',
                    'vchord_bm25',
                ];

                foreach ($extensions as $extension) {
                    rescue(fn () => DB::statement("CREATE EXTENSION IF NOT EXISTS {$extension} CASCADE"));
                }
            });
        }

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
