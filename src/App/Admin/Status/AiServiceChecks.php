<?php

declare(strict_types=1);

namespace App\Admin\Status;

use App\Admin\Settings\AiProviderCheck;
use App\Admin\Settings\AiProviderUsage;
use App\Admin\Settings\Data\ConnectionCheckData;
use App\Admin\Settings\FirecrawlCheck;
use App\Admin\Status\Data\ServiceStatusData;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingCheck;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks each outside service of the Status page ({@see AiService}) with the
 * saved keys, through the checks Settings uses: a provider's key with a call
 * that costs nothing (its model list) or next to nothing, the embedding
 * service with a real embedding, and Firecrawl with its credit usage.
 *
 * A provider that serves several services is called once.
 */
final class AiServiceChecks
{
    /** @var array<string, ConnectionCheckData> The provider checks made so far, by provider. */
    private array $checked = [];

    public function __construct(
        private readonly AiProviderUsage $usage,
        private readonly AiProviderCheck $providers,
        private readonly EmbeddingCheck $embeddings,
        private readonly FirecrawlCheck $firecrawl,
    ) {}

    public function check(AiService $service): ServiceStatusData
    {
        try {
            return match ($service) {
                AiService::Chat => $this->chat(),
                AiService::Embeddings => $this->embeddings(),
                AiService::Reranking => $this->onProvider($service, AiCapability::Reranking),
                AiService::Documents => $this->onProvider($service, AiCapability::Documents),
                AiService::Speech => $this->onProvider($service, AiCapability::Speech),
                AiService::Voice => $this->onProvider($service, AiCapability::Voice),
                AiService::WebPages => $this->webPages(),
            };
        } catch (Throwable $e) {
            return new ServiceStatusData(ServiceState::Down, Str::limit($e->getMessage(), 200));
        }
    }

    /** Every provider with a chat model agents can use; the worst of them decides. */
    private function chat(): ServiceStatusData
    {
        $providers = $this->usage->chatProviders();

        if ($providers === []) {
            return new ServiceStatusData(ServiceState::Down, __('No chat model is ready, so agents cannot reply. Add one in Settings → AI models.'));
        }

        $failed = array_values(array_filter($providers, fn (AiProvider $provider): bool => ! $this->provider($provider)->connected));
        $names = array_map(fn (AiProvider $provider): string => $provider->label(), $providers);
        $values = array_map(fn (AiProvider $provider): string => $provider->value, $providers);

        if ($failed !== []) {
            return new ServiceStatusData(
                count($failed) === count($providers) ? ServiceState::Down : ServiceState::Warning,
                $this->provider($failed[0])->message ?? '',
                $names,
                $values,
            );
        }

        return new ServiceStatusData(ServiceState::Running, __('Connected.'), $names, $values);
    }

    /** A real embedding with the service in use, as training a source makes. */
    private function embeddings(): ServiceStatusData
    {
        $service = EmbeddingProvider::configured();

        if (! $service instanceof EmbeddingProvider) {
            return new ServiceStatusData(ServiceState::Down, __('No embedding service is set up, so knowledge cannot be searched. Pick one in Settings → AI models.'));
        }

        $result = $this->embeddings->check($service);
        $facts = [$service->label()];
        $providers = [$service->account()->value];

        if (! $result->ok()) {
            return new ServiceStatusData(ServiceState::Down, $result->message($service->label()) ?? '', $facts, $providers);
        }

        return new ServiceStatusData(ServiceState::Running, __('Connected.'), [
            ...$facts,
            __(':count dimensions', ['count' => $result->returned]),
        ], $providers);
    }

    private function webPages(): ServiceStatusData
    {
        $key = config('services.firecrawl.key');

        if (blank($key)) {
            return new ServiceStatusData(ServiceState::Off, __('Not set up. Websites cannot be added as knowledge.'));
        }

        return $this->fromCheck($this->firecrawl->check((string) $key), ['Firecrawl']);
    }

    /** A service that runs on one provider, picked in Settings (or the first connected one). */
    private function onProvider(AiService $service, AiCapability $capability): ServiceStatusData
    {
        $provider = $this->usage->providerFor($capability);

        if (! $provider instanceof AiProvider) {
            return new ServiceStatusData($service->isRequired() ? ServiceState::Down : ServiceState::Off, __('Not set up.'));
        }

        return $this->fromCheck($this->provider($provider), [$provider->label()], [$provider->value]);
    }

    private function provider(AiProvider $provider): ConnectionCheckData
    {
        return $this->checked[$provider->value] ??= $this->providers->check($provider);
    }

    /**
     * @param  list<string>  $facts
     * @param  list<string>  $providers
     */
    private function fromCheck(ConnectionCheckData $check, array $facts, array $providers = []): ServiceStatusData
    {
        return match (true) {
            ! $check->connected => new ServiceStatusData(ServiceState::Down, $check->message ?? '', $facts, $providers),
            $check->warning !== null => new ServiceStatusData(ServiceState::Warning, $check->warning, $facts, $providers),
            default => new ServiceStatusData(ServiceState::Running, __('Connected.'), $facts, $providers),
        };
    }
}
