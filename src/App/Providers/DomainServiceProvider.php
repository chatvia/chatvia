<?php

namespace App\Providers;

use App\Identity\Chatter\Events\ChatterPasswordResetRequested;
use App\Identity\Chatter\Listeners\SendChatterPasswordResetLink;
use App\Identity\Workspace\Events\AccountDeletionRequested;
use App\Identity\Workspace\Events\DataExportReady;
use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Listeners\CountReplyAgainstMessageLimit;
use App\Identity\Workspace\Listeners\RecordFailedLoginSecurityEvent;
use App\Identity\Workspace\Listeners\RecordKnownDevice;
use App\Identity\Workspace\Listeners\RecordLastLogin;
use App\Identity\Workspace\Listeners\RecordLoginSecurityEvent;
use App\Identity\Workspace\Listeners\RecordLogoutSecurityEvent;
use App\Identity\Workspace\Listeners\SendAccountDeletionConfirmationEmail;
use App\Identity\Workspace\Listeners\SendDataExportReadyNotification;
use App\Identity\Workspace\Listeners\SendMagicLinkEmail;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use App\Mcp\CodeDefinedToolDefinitions;
use Domain\Agent\Events\AgentToolsChanged;
use Domain\Agent\Listeners\ForgetAgentToolPool;
use Domain\Agent\Models\Agent;
use Domain\Agent\Observers\AgentObserver;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Listeners\QueueMessageAnalysis;
use Domain\Analytics\Listeners\RecordAnalytics;
use Domain\Conversation\Events\AgentRateLimitExceeded;
use Domain\Conversation\Events\AssistantReplyCompleted;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Events\ToolCallCompleted;
use Domain\Conversation\Listeners\LocateSession;
use Domain\Conversation\Listeners\RecordToolCallAudit;
use Domain\Conversation\Listeners\SendAgentRateLimitNotification;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use Domain\Conversation\Services\Streaming\Transformers\StreamTransformer;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Listeners\PushSubmissionToCrm;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Observers\FormDefinitionObserver;
use Domain\Guardrail\Events\GuardrailTriggered;
use Domain\Guardrail\Listeners\RecordGuardrailEvent;
use Domain\Integration\Connector\Services\OAuth\OAuthTokenRefresher;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Observers\AgentFunctionObserver;
use Domain\Integration\Mcp\Contracts\AccessTokenRefresher;
use Domain\Integration\Mcp\Contracts\BuiltinToolDefinitions;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Domain\Integration\Mcp\Listeners\UpdateMcpInvocationCounters;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Observers\AgentMcpServerObserver;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Dispatch\RoutingToolDispatcher;
use Domain\Knowledge\Embedding\Cohere;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Exceptions\EmbeddingDimensionsMismatchException;
use Domain\Knowledge\Embedding\Rerank\CohereReranker;
use Domain\Knowledge\Embedding\Rerank\NullReranker;
use Domain\Knowledge\Embedding\Rerank\Qwen3Reranker;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Contracts\DocumentParser;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFitTextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\LocalTextSplitter;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;
use Domain\Knowledge\Ingestion\Services\Parsing\MistralDocumentParser;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\File\Observers\SourceFileObserver;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Text\Observers\SourceTextObserver;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Web\Observers\SourceWebsiteObserver;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Knowledge\Source\Youtube\Observers\SourceYoutubeVideoObserver;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Operations\Media\NullTranscriber;
use Domain\Llm\Operations\Media\ScalewayTranscriber;
use Domain\Llm\Providers\Contracts\ProviderAdapter;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Services\ProviderAdapters;
use Domain\Llm\Usage\TokenUsageContext;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Listeners\SendAgentChatterInvitationEmail;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Listeners\SendTeamInvitationEmail;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Listeners\RecordSecurityEvent;
use Domain\Security\Listeners\SendSecurityEventToSiem;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Domain wiring: observers, DI bindings against contracts (AI providers,
 * tool dispatch, …) and event listeners — every
 * listener is registered here by hand; there is no auto-discovery.
 *
 * Framework wiring lives in sibling providers: AppServiceProvider (morph map,
 * model strictness, prompt views), AuthServiceProvider (gates, policies,
 * Sanctum), HttpMacroServiceProvider, RateLimitServiceProvider,
 * FilesystemServiceProvider and ApiDocsServiceProvider.
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

        // The MCP client asks for a fresh OAuth token through this contract, so
        // it does not depend on the connector OAuth flow (which syncs tool
        // catalogs through the client). Builtin tool definitions are read from
        // the code-defined MCP servers in App\Mcp.
        $this->app->bind(AccessTokenRefresher::class, OAuthTokenRefresher::class);
        $this->app->bind(BuiltinToolDefinitions::class, CodeDefinedToolDefinitions::class);

        // SSE stream transformer: parses the upstream OpenAI-compatible byte
        // stream into envelope events. Kept behind an interface as the seam the
        // tool loop streams through — ToolLoopRunnerTest injects fake transformers
        // to drive canned tool-call streams without a live upstream.
        $this->app->bind(StreamTransformer::class, OpenAiCompatibleTransformer::class);

        // Per-request / per-job scope that attributes AI requests to a
        // workspace. MUST be a singleton so every call within one chat turn or
        // queued job sees the same scope.
        $this->app->singleton(TokenUsageContext::class);

        $this->registerAiServices();

        // PasswordAuthenticator wraps the request's default guard (`web`, or
        // `chat` inside the hosted chat) so Domain Actions don't have to reach
        // for the Auth facade. We bind it explicitly because StatefulGuard has
        // multiple implementations and Laravel can't auto-resolve which one we
        // want. A plain bind, so each resolve picks up the current guard.
        $this->app->bind(
            PasswordAuthenticator::class,
            fn (): PasswordAuthenticator => new PasswordAuthenticator(Auth::guard()),
        );
    }

    /**
     * The embedding provider plus the services of each ingestion and
     * retrieval step, picked from config('chatvia.ai.*'). Chat models are not
     * bound here: each call goes to its model's provider (LlmProviders). `auto` uses a hosted
     * service when its API key is set and a local or "off" implementation when
     * it is not, so a self-hosted install works without those accounts. All
     * are bound (not singletons) so they always match the current config.
     */
    private function registerAiServices(): void
    {
        $this->app->bind(function ($app): EmbeddingsProviderInterface {
            $provider = $app->make(
                EmbeddingProvider::tryFrom((string) config('chatvia.ai.models.embedding'))?->providerClass() ?? Cohere::class,
            );

            // The vector columns have a fixed size: fail here with a clear
            // message instead of on the first insert or search.
            $stored = $app->make(EmbeddingStorage::class)->dimensions();

            if ((int) $provider->getDimensions() !== $stored) {
                throw EmbeddingDimensionsMismatchException::for($provider, $stored);
            }

            return $provider;
        });

        $this->app->bind(fn ($app): Reranker => match ($this->aiDriver('reranker', auto: match (config('chatvia.ai.models.embedding', 'mistral')) {
            'qwen' => 'qwen',
            'jina', 'mistral' => 'none',
            default => 'cohere',
        })) {
            'qwen' => $app->make(Qwen3Reranker::class),
            'cohere' => $app->make(CohereReranker::class),
            'none' => $app->make(NullReranker::class),
            default => throw $this->unknownAiDriver('reranker', ['qwen', 'cohere', 'none']),
        });

        $this->app->bind(fn ($app): DocumentParser => match ($this->aiDriver('document_parser', auto: filled(config('services.mistral.api_key')) ? 'mistral' : 'local')) {
            'mistral' => $app->make(MistralDocumentParser::class),
            'local' => $app->make(LocalDocumentParser::class),
            default => throw $this->unknownAiDriver('document_parser', ['mistral', 'local']),
        });

        $this->app->bind(fn ($app): TextSplitter => match ($this->aiDriver('text_splitter', auto: $app->make(ChunkFitClient::class)->isAvailable() ? 'chunkfit' : 'local')) {
            'chunkfit' => $app->make(ChunkFitTextSplitter::class),
            'local' => $app->make(LocalTextSplitter::class),
            default => throw $this->unknownAiDriver('text_splitter', ['chunkfit', 'local']),
        });

        $this->app->bind(fn ($app): Transcriber => match ($this->aiDriver('transcriber', auto: filled(config('services.scalewayai.api_key')) ? 'scaleway' : 'none')) {
            'scaleway' => $app->make(ScalewayTranscriber::class),
            'none' => $app->make(NullTranscriber::class),
            default => throw $this->unknownAiDriver('transcriber', ['scaleway', 'none']),
        });

        $this->app->bind(function ($app): DocumentLlm {
            $driver = $this->aiDriver('document_llm', auto: filled(config('services.mistral.api_key')) ? 'mistral' : 'none');

            if ($driver === 'none') {
                return new DocumentLlm(null);
            }

            $adapters = $app->make(ProviderAdapters::class);
            $readers = array_values(array_filter($adapters->all(), static fn (ProviderAdapter $adapter): bool => $adapter->readsDocuments()));
            $provider = LlmProviderEnum::tryFrom($driver);

            if (! in_array($provider, array_map(static fn (ProviderAdapter $adapter): LlmProviderEnum => $adapter->kind(), $readers), true)) {
                throw $this->unknownAiDriver('document_llm', [...array_map(static fn (ProviderAdapter $adapter): string => $adapter->kind()->value, $readers), 'none']);
            }

            return new DocumentLlm($provider);
        });
    }

    /** The configured driver for a chatvia.ai step, with `auto` (or empty) replaced by $auto. */
    private function aiDriver(string $key, string $auto): string
    {
        $driver = strtolower(trim((string) config("chatvia.ai.{$key}", 'auto')));

        return $driver === '' || $driver === 'auto' ? $auto : $driver;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function unknownAiDriver(string $key, array $allowed): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf(
            'Unsupported value "%s" for chatvia.ai.%s. Use auto, %s.',
            (string) config("chatvia.ai.{$key}"),
            $key,
            implode(', ', $allowed),
        ));
    }

    public function boot(): void
    {
        $this->registerObservers();
        $this->registerEventListeners();
    }

    private function registerObservers(): void
    {
        Agent::observe(AgentObserver::class);
        AgentFunction::observe(AgentFunctionObserver::class);
        FormDefinition::observe(FormDefinitionObserver::class);
        AgentMcpServer::observe(AgentMcpServerObserver::class);
        SourceFile::observe(SourceFileObserver::class);
        SourceText::observe(SourceTextObserver::class);
        SourceWebsite::observe(SourceWebsiteObserver::class);
        SourceYoutubeVideo::observe(SourceYoutubeVideoObserver::class);
    }

    private function registerEventListeners(): void
    {
        Event::listen(Login::class, RecordKnownDevice::class);
        Event::listen(Login::class, RecordLastLogin::class);

        // Enterprise security audit log — one parametric event, persisted off
        // the request by a queued listener. The auth listeners run on the web
        // thread to capture live IP/UA (NOT queued); they denormalize that onto
        // the event so the downstream recorder can run on a worker.
        Event::listen(SecurityEventOccurred::class, RecordSecurityEvent::class);
        // Enterprise SIEM fan-out — same event, delivered (HMAC-signed) to the
        // workspace's webhook by a second queued listener; no-ops unless enabled.
        Event::listen(SecurityEventOccurred::class, SendSecurityEventToSiem::class);
        Event::listen(Login::class, RecordLoginSecurityEvent::class);
        Event::listen(Failed::class, RecordFailedLoginSecurityEvent::class);
        Event::listen(Logout::class, RecordLogoutSecurityEvent::class);

        Event::listen(AgentChatterInvited::class, SendAgentChatterInvitationEmail::class);
        Event::listen(TeamInvitationCreated::class, SendTeamInvitationEmail::class);
        Event::listen(MagicLinkRequested::class, SendMagicLinkEmail::class);
        Event::listen(DataExportReady::class, SendDataExportReadyNotification::class);
        Event::listen(ChatterPasswordResetRequested::class, SendChatterPasswordResetLink::class);
        Event::listen(SessionLocationUnknown::class, LocateSession::class);
        Event::listen(AssistantReplyCompleted::class, CountReplyAgainstMessageLimit::class);
        Event::listen(MessageSent::class, QueueMessageAnalysis::class);
        Event::listen(AccountDeletionRequested::class, SendAccountDeletionConfirmationEmail::class);
        Event::listen(ToolCallCompleted::class, RecordToolCallAudit::class);
        Event::listen(GuardrailTriggered::class, RecordGuardrailEvent::class);
        Event::listen(McpToolInvoked::class, UpdateMcpInvocationCounters::class);
        Event::listen(AgentToolsChanged::class, ForgetAgentToolPool::class);
        Event::listen(AgentRateLimitExceeded::class, SendAgentRateLimitNotification::class);
        Event::listen(FormSubmitted::class, PushSubmissionToCrm::class);

        Event::subscribe(RecordAnalytics::class);
    }
}
