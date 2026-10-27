<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\ConnectionCheckData;
use Domain\Knowledge\Embedding\Enums\EmbeddingCheckStatus;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingCheck;
use Domain\Knowledge\Ingestion\Services\Clients\AzureDocumentIntelligence;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Domain\Llm\Services\FalQueue;
use Domain\Llm\Services\ModelListing;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Checks an AI provider's key, the one typed or else the saved one: a chat
 * provider by reading its model list, Cohere and Jina with a test
 * embedding, Azure with a call to its resource, ElevenLabs by reading its
 * model list, and the video and image services (Kling, BytePlus, Runway,
 * fal.ai) with a call that needs a key but makes nothing. Only a refused key
 * or a provider that cannot be reached counts as not connected.
 */
final readonly class AiProviderCheck
{
    /** The custom-model limit of Azure's standard tier; the free tier has less. */
    private const int AZURE_STANDARD_MODEL_LIMIT = 5000;

    private const string AZURE_FREE_TIER = 'This Azure resource is on the free tier (F0): it reads only the first 2 pages of each file, and files up to 4 MB. Use the standard tier (S0) for real documents.';

    public function __construct(
        private ModelListing $listing,
        private EmbeddingCheck $embeddings,
        private ProviderAdapters $adapters,
        private AzureDocumentIntelligence $azure,
    ) {}

    public function check(AiProvider $provider, ?string $apiKey = null, ?string $baseUrl = null): ConnectionCheckData
    {
        $key = filled($apiKey) ? $apiKey : config($provider->apiKeyConfig());

        if (blank($key)) {
            return new ConnectionCheckData(false, message: "No {$provider->label()} key is saved yet.");
        }

        if ($provider === AiProvider::Azure) {
            return $this->checkAzure((string) $key, filled($baseUrl) ? $baseUrl : config($provider->baseUrlConfig() ?? ''));
        }

        if ($provider === AiProvider::ElevenLabs) {
            return $this->checkElevenLabs((string) $key, (string) (filled($baseUrl) ? $baseUrl : config('services.elevenlabs.endpoint')));
        }

        if (collect([AiProvider::Kling, AiProvider::BytePlus, AiProvider::Runway, AiProvider::Fal])->containsStrict($provider)) {
            return $this->checkMediaService($provider, (string) $key, (string) (filled($baseUrl) ? $baseUrl : config($provider->baseUrlConfig() ?? '')));
        }

        $kind = $provider->chatKind();

        if ($kind instanceof LlmProviderKind) {
            // An unsaved row may hold the endpoint being checked: only saved rows of your own server keep one.
            $endpoint = match (true) {
                ! $provider->needsBaseUrl() => null,
                filled($baseUrl) => $baseUrl,
                default => config($provider->baseUrlConfig() ?? ''),
            };

            if ($provider->needsBaseUrl() && blank($endpoint)) {
                return new ConnectionCheckData(false, message: "Add the endpoint of your {$provider->label()} resource too.");
            }

            $draft = new LlmProvider(['kind' => $kind, 'name' => $provider->label(), 'api_key' => $key, 'base_url' => $endpoint]);
            $list = $this->listing->list($draft);

            return new ConnectionCheckData(
                connected: $list->keyAccepted(),
                keyRefused: $list->status === ModelListStatus::KeyRefused,
                message: $list->keyAccepted() ? null : $list->message($provider->label(), $this->adapters->for($kind)->baseUrl($draft)),
            );
        }

        $embedding = array_find(EmbeddingProvider::cases(), fn (EmbeddingProvider $service): bool => $service->account() === $provider);

        if (! $embedding instanceof EmbeddingProvider) {
            return new ConnectionCheckData(true);
        }

        $result = $this->embeddings->check($embedding, apiKey: (string) $key);

        return new ConnectionCheckData(
            connected: $result->keyAccepted(),
            keyRefused: $result->status === EmbeddingCheckStatus::KeyRefused,
            message: $result->keyAccepted() ? null : $result->message($provider->label()),
        );
    }

    /**
     * Whether the Azure resource is on the free tier (F0): its limit of
     * custom models is 500, where the standard tier (S0) has 5,000. The
     * free tier reads only the first 2 pages of each file, without an error.
     */
    private function azureFreeTier(Response $response): bool
    {
        $limit = $response->json('customDocumentModels.limit');

        return is_numeric($limit) && (int) $limit < self::AZURE_STANDARD_MODEL_LIMIT;
    }

    /** The model list takes any ElevenLabs key, whatever the key may do. */
    private function checkElevenLabs(string $key, string $endpoint): ConnectionCheckData
    {
        try {
            $response = Http::withHeaders(['xi-api-key' => $key])
                ->acceptJson()
                ->timeout(15)
                ->get(Str::rtrim($endpoint, '/').'/models');
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: "{$endpoint} could not be reached.");
        }

        return match (true) {
            $response->successful() => new ConnectionCheckData(true),
            collect([401, 403])->containsStrict($response->status()) => new ConnectionCheckData(false, true, 'ElevenLabs refused the key. Check that you copied all of it.'),
            default => new ConnectionCheckData(false, message: "ElevenLabs answered with an error ({$response->status()})."),
        };
    }

    /**
     * A call each media service answers with 401 or 403 for a wrong key:
     * Kling's balance, BytePlus' task list, Runway's organization, and the
     * status of a fal.ai request that does not exist (404 means the key was
     * taken). Any other answer below 500 means the key was accepted.
     */
    private function checkMediaService(AiProvider $provider, string $key, string $endpoint): ConnectionCheckData
    {
        $address = Str::rtrim($endpoint, '/');

        try {
            $response = match ($provider) {
                AiProvider::Kling => Http::withToken($key)->acceptJson()->timeout(15)->post("{$address}/account/billing/balance", []),
                AiProvider::BytePlus => Http::withToken($key)->acceptJson()->timeout(15)->get("{$address}/contents/generations/tasks", ['page_size' => 1]),
                AiProvider::Runway => Http::withToken($key)->withHeaders(['X-Runway-Version' => RunwayVideoGenerator::API_VERSION])->acceptJson()->timeout(15)->get(RunwayVideoGenerator::ENDPOINT.'/organization'),
                default => Http::withHeaders(['Authorization' => "Key {$key}"])->acceptJson()->timeout(15)->get(FalQueue::ENDPOINT.'/fal-ai/flux-2/requests/'.Str::uuid7()->toString().'/status'),
            };
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: "{$provider->label()} could not be reached.");
        }

        return match (true) {
            collect([401, 403])->containsStrict($response->status()) => new ConnectionCheckData(false, true, "{$provider->label()} refused the key. Check that you copied all of it."),
            $response->status() < 500 => new ConnectionCheckData(true),
            default => new ConnectionCheckData(false, message: "{$provider->label()} answered with an error ({$response->status()})."),
        };
    }

    /** Azure needs its endpoint as well as its key: a call to the resource checks both. */
    private function checkAzure(string $key, mixed $endpoint): ConnectionCheckData
    {
        if (blank($endpoint)) {
            return new ConnectionCheckData(false, message: 'Add the endpoint of your Azure resource too.');
        }

        try {
            $response = $this->azure->check($key, (string) $endpoint);
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: "{$endpoint} could not be reached. Check the endpoint.");
        }

        return match (true) {
            $response->successful() => new ConnectionCheckData(true, warning: $this->azureFreeTier($response) ? self::AZURE_FREE_TIER : null),
            collect([401, 403])->containsStrict($response->status()) => new ConnectionCheckData(false, true, 'Azure refused the key. Check that you copied all of it.'),
            default => new ConnectionCheckData(false, message: "Azure answered with an error ({$response->status()}). Check the endpoint."),
        };
    }
}
