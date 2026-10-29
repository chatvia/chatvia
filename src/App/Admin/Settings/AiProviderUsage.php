<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\MediaModelCatalog;
use Illuminate\Support\Str;

/**
 * What runs on which AI provider right now: the embedding service in use,
 * the driver picked (or "Automatic") for speech, voice, reading aloud,
 * documents, reranking and audio generation, the services of the image and
 * video models listed, and the providers whose chat models agents can use.
 * It marks the chips on Settings → AI providers.
 */
final class AiProviderUsage
{
    public function __construct(
        private readonly MediaModelCatalog $media,
    ) {}

    /** The provider a capability runs on now; null when none does (or, for chat and image and video generation, when several may). */
    public function providerFor(AiCapability $capability): ?AiProvider
    {
        return match ($capability) {
            AiCapability::Embeddings => EmbeddingProvider::configured()?->account(),
            AiCapability::Speech => $this->driver('transcriber', $capability),
            AiCapability::Voice => $this->driver('voice', $capability),
            AiCapability::TextToSpeech => $this->driver('speaker', $capability),
            AiCapability::Documents => $this->driver('document_parser', $capability),
            AiCapability::Reranking => $this->driver('reranker', $capability),
            AiCapability::AudioGeneration => $this->driver('audio_generator', $capability),
            AiCapability::Chat, AiCapability::ImageGeneration, AiCapability::VideoGeneration => null,
        };
    }

    /**
     * Every provider a capability runs on now.
     *
     * @return list<AiProvider>
     */
    public function providersFor(AiCapability $capability): array
    {
        $listed = fn (array $models): array => collect($models)
            ->map(fn (MediaModelData $model): AiProvider => $model->service)
            ->unique()
            ->values()
            ->all();

        return match ($capability) {
            AiCapability::Chat => $this->chatProviders(),
            AiCapability::ImageGeneration => $listed($this->media->imageModels()),
            AiCapability::VideoGeneration => $listed($this->media->videoModels()),
            default => collect([$this->providerFor($capability)])->filter()->values()->all(),
        };
    }

    /**
     * What a provider is used for now.
     *
     * @return list<AiCapability>
     */
    public function usedFor(AiProvider $provider): array
    {
        return array_values(array_filter(
            $provider->capabilities(),
            fn (AiCapability $capability): bool => collect($this->providersFor($capability))->containsStrict($provider),
        ));
    }

    /**
     * The providers whose chat models agents can use now.
     *
     * @return list<AiProvider>
     */
    public function chatProviders(): array
    {
        return Llm::available()
            ->map(fn (Llm $llm): ?AiProvider => AiProvider::forChatKind($llm->provider->kind))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** The provider a "chatvia.ai.*" driver resolves to: the one picked, or the first connected one. */
    private function driver(string $key, AiCapability $capability): ?AiProvider
    {
        $driver = Str::lower(Str::trim((string) config("chatvia.ai.{$key}", 'auto')));

        if ($driver === '' || $driver === 'auto') {
            return AiProvider::firstConnectedFor($capability);
        }

        $provider = AiProvider::tryFrom($driver);

        return $provider instanceof AiProvider && $provider->can($capability) ? $provider : null;
    }
}
