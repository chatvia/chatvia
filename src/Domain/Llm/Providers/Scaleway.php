<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Models\Llm;

/**
 * Chat completions through the Scaleway Generative APIs (open-weight models
 * such as Qwen3, hosted in the EU). The request goes as it is: which models
 * take a reasoning effort, and which values, comes from the model's facts
 * ({@see Llm::effortValue()}).
 */
class Scaleway extends AbstractLlmProvider
{
    /**
     * The Scaleway address set in Settings → AI providers
     * (`services.scalewayai.endpoint`), for the Qwen3 embedding and rerank
     * clients and audio transcription. Chat calls use the address of their
     * provider row instead.
     */
    public static function baseUrl(): string
    {
        return rtrim((string) config('services.scalewayai.endpoint'), '/');
    }
}
