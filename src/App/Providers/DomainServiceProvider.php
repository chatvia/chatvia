<?php

namespace App\Providers;

use App\Identity\Workspace\Events\AccountDeletionRequested;
use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Listeners\RecordFailedLoginSecurityEvent;
use App\Identity\Workspace\Listeners\RecordKnownDevice;
use App\Identity\Workspace\Listeners\RecordLoginSecurityEvent;
use App\Identity\Workspace\Listeners\RecordLogoutSecurityEvent;
use App\Identity\Workspace\Listeners\SendAccountDeletionConfirmationEmail;
use App\Identity\Workspace\Listeners\SendDataExportReadyNotification;
use App\Identity\Workspace\Listeners\SendMagicLinkEmail;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Observers\UserObserver;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\ModelSetting;
use Domain\Agent\Observers\AgentObserver;
use Domain\Agent\Observers\ModelSettingObserver;
use Domain\Analytics\Events\McpToolInvoked;
use Domain\Analytics\Listeners\RecordAnalytics;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Listeners\HandleStripeWebhook;
use Domain\Billing\Listeners\RecordLlmTokenUsage;
use Domain\Billing\Listeners\TriggerAutoRecharge;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Listeners\LocateSession;
use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Listeners\SendAgentRateLimitNotification;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformer;
use Domain\Conversation\Services\Tooling\Dispatcher\RoutingToolDispatcher;
use Domain\Conversation\Services\Tooling\Dispatcher\ToolDispatcherInterface;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Listeners\RecordGuardrailEvent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Observers\AgentFunctionObserver;
use Domain\Integration\Mcp\Listeners\UpdateMcpInvocationCounters;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Observers\AgentMcpServerObserver;
use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Jina;
use Domain\Knowledge\Embedding\Mistral;
use Domain\Knowledge\Embedding\Qwen3;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\File\Observers\SourceFileObserver;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Text\Observers\AgentTextObserver;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Observers\AgentWebsiteObserver;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Knowledge\Source\Youtube\Observers\SourceYoutubeVideoObserver;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Events\LlmTokensConsumed;
use Domain\Llm\Providers\Groq;
use Domain\Llm\Providers\Mistral as LlmMistral;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Usage\TokenUsageContext;
use Domain\Membership\Agent\Events\AgentMemberInvited;
use Domain\Membership\Agent\Listeners\SendAgentMemberInvitationEmail;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Listeners\SendTeamInvitationEmail;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\RecordSecurityEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;
use Support\Cms\Marketing\Models\Post;
use Support\Cms\Marketing\Observers\PostObserver;

/**
 * Domain wiring: observers, DI bindings against contracts, and event
 * listeners. Sibling to AppServiceProvider, which keeps framework-only
 * concerns (request macros, rate limiters, Stripe, Cashier, Scramble,
 * morphMap, gates, etc.).
 *
 * Loaded from bootstrap/providers.php.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Tool dispatcher: routes by tool name to either the webhook
        // (AgentFunctions) or MCP backend.
        $this->app->bind(ToolDispatcherInterface::class, RoutingToolDispatcher::class);

        // SSE stream transformer: parses the upstream OpenAI-compatible byte
        // stream into envelope events. Single implementation today.
        $this->app->bind(StreamTransformer::class, OpenAiCompatibleTransformer::class);

        // Per-request / per-job accumulator that attributes LLM token usage to a
        // workspace for analytics. MUST be a singleton so every add() within one
        // chat turn or queued job lands in the same scope.
        $this->app->singleton(TokenUsageContext::class);

        $this->app->bind(LlmProviderInterface::class, function ($app) {
            $provider = config('chatvia.ai.provider', 'openrouter');

            return match ($provider) {
                'groq' => $app->make(Groq::class),
                'mistral' => $app->make(LlmMistral::class),
                'scaleway' => $app->make(Scaleway::class),
                default => $app->make(OpenRouter::class),
            };
        });

        $this->app->bind(EmbeddingsProviderInterface::class, function ($app) {
            $provider = config('chatvia.ai.models.embedding', 'mistral');

            return match ($provider) {
                'jina' => $app->make(Jina::class),
                'qwen' => $app->make(Qwen3::class),
                'mistral' => $app->make(Mistral::class),
                default => $app->make(Cohere::class),
            };
        });

        // PasswordAuthenticator wraps the `web` guard so Domain Actions don't
        // have to reach for the Auth facade. We bind it explicitly because
        // StatefulGuard has multiple implementations and Laravel can't
        // auto-resolve which one we want.
        $this->app->bind(
            PasswordAuthenticator::class,
            fn (): PasswordAuthenticator => new PasswordAuthenticator(Auth::guard('web')),
        );
    }

    public function boot(): void
    {
        $this->registerObservers();
        $this->registerEventListeners();
    }

    private function registerObservers(): void
    {
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
    }

    private function registerEventListeners(): void
    {
        Event::listen(Login::class, RecordKnownDevice::class);

        // Enterprise security audit log — one parametric event, persisted off
        // the request by a queued listener. The auth listeners run on the web
        // thread to capture live IP/UA (NOT queued); they denormalize that onto
        // the event so the downstream recorder can run on a worker.
        Event::listen(SecurityEventOccurred::class, RecordSecurityEvent::class);
        Event::listen(Login::class, RecordLoginSecurityEvent::class);
        Event::listen(Failed::class, RecordFailedLoginSecurityEvent::class);
        Event::listen(Logout::class, RecordLogoutSecurityEvent::class);

        Event::listen(AgentMemberInvited::class, SendAgentMemberInvitationEmail::class);
        Event::listen(TeamInvitationCreated::class, SendTeamInvitationEmail::class);
        Event::listen(MagicLinkRequested::class, SendMagicLinkEmail::class);
        Event::listen(MessageCreditsLow::class, TriggerAutoRecharge::class);
        Event::listen(DataExportReady::class, SendDataExportReadyNotification::class);
        Event::listen(WebhookReceived::class, HandleStripeWebhook::class);
        Event::listen(SessionLocationUnknown::class, LocateSession::class);
        Event::listen(AccountDeletionRequested::class, SendAccountDeletionConfirmationEmail::class);
        Event::listen(LlmTokensConsumed::class, RecordLlmTokenUsage::class);
        Event::listen(ToolCallCompleted::class, RecordToolCallAudit::class);
        Event::listen(GuardrailTriggered::class, RecordGuardrailEvent::class);
        Event::listen(McpToolInvoked::class, UpdateMcpInvocationCounters::class);
        Event::listen(AgentRateLimitExceeded::class, SendAgentRateLimitNotification::class);

        Event::subscribe(RecordAnalytics::class);
    }
}
