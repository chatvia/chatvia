<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;

/**
 * Answers every model with one fake client, so a test scripts the provider's
 * replies without an HTTP fake. Bind it with bindFakeLlmProvider().
 */
class FakeLlmProviders extends LlmProviders
{
    public function __construct(
        private readonly ChatCompletionProvider $provider,
    ) {}

    public function forProvider(LlmProvider $provider): ChatCompletionProvider
    {
        return $this->provider;
    }
}
