<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;

/**
 * Chat completions through the Scaleway Generative APIs (open-weight models
 * such as Qwen3, hosted in the EU).
 */
class Scaleway extends AbstractLlmProvider
{
    /**
     * Scaleway's Qwen3 reasoning models (qwen3.*) think by default: that adds
     * several seconds before the first answer token plus a chunk of hidden
     * chain-of-thought. This pipeline is RAG — we want the grounded answer
     * straight away — so default reasoning OFF for those models via
     * `reasoning_effort=none`.
     *
     * Scoped to qwen ONLY: other Scaleway models (the analytics / guardrail
     * instruct models) strictly validate the request body and return 400 on an
     * unknown `reasoning_effort` field, so they must never receive it. A caller
     * that set `reasoning_effort` itself is always left untouched.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        if (array_key_exists('reasoning_effort', $payload)) {
            return $payload;
        }

        if (! str_contains(strtolower((string) ($payload['model'] ?? '')), 'qwen')) {
            return $payload;
        }

        $payload['reasoning_effort'] = 'none';

        return $payload;
    }

    /**
     * The Scaleway address set in Settings → AI
     * (`services.scalewayai.endpoint`), for the Qwen3 embedding and rerank
     * clients and audio transcription. Chat calls use the address of their
     * provider row instead.
     */
    public static function baseUrl(): string
    {
        return rtrim((string) config('services.scalewayai.endpoint'), '/');
    }
}
