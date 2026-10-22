<?php

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Exceptions\DocumentLlmUnavailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * The LLM provider that can read a whole PDF passed by URL in a chat message
 * (a `document_url` message part). Document summaries, document analysis and
 * chat with PDF files use it.
 *
 * Built from config (`chatvia.ai.document_llm`), with the key and address of
 * that account (Settings → AI providers) rather than a catalog model. When no such
 * provider is set up, isAvailable() is false and those features switch off
 * instead of failing.
 */
final readonly class DocumentLlm
{
    public function __construct(
        private ?LlmProviderKind $provider,
    ) {}

    public function isAvailable(): bool
    {
        return $this->provider instanceof LlmProviderKind;
    }

    /**
     * @throws DocumentLlmUnavailableException
     */
    public function provider(): LlmProviderInterface
    {
        return app(LlmProviders::class)->forProvider($this->connection());
    }

    /**
     * A model of this provider by its name (`chatvia.ai.models.summary`, ...),
     * for a request. It is not a catalog row.
     *
     * @throws DocumentLlmUnavailableException
     */
    public function model(string $name): Llm
    {
        return new Llm(['name' => $name])->setRelation('provider', $this->connection());
    }

    /**
     * The provider's slug, for cost tracking.
     *
     * @throws DocumentLlmUnavailableException
     */
    public function slug(): string
    {
        return $this->provider?->value ?? throw new DocumentLlmUnavailableException;
    }

    /**
     * @throws DocumentLlmUnavailableException
     */
    private function connection(): LlmProvider
    {
        return app(ProviderAdapters::class)->for($this->provider ?? throw new DocumentLlmUnavailableException)->standalone();
    }
}
