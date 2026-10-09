<?php

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Exceptions\DocumentLlmUnavailableException;

/**
 * The LLM provider that can read a whole PDF passed by URL in a chat message
 * (a `document_url` message part). Document summaries, document analysis and
 * chat with PDF files use it.
 *
 * Built from config (`chatvia.ai.document_llm`). When no such provider is set
 * up, isAvailable() is false and those features switch off instead of failing.
 */
final readonly class DocumentLlm
{
    public function __construct(
        private ?LlmProviderEnum $provider,
    ) {}

    public function isAvailable(): bool
    {
        return $this->provider instanceof LlmProviderEnum;
    }

    /**
     * @throws DocumentLlmUnavailableException
     */
    public function provider(): LlmProviderInterface
    {
        $class = $this->provider?->providerClass() ?? throw new DocumentLlmUnavailableException;

        return app($class);
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
}
