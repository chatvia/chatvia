<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;
use RuntimeException;

/**
 * A model's provider cannot be called: it is turned off, or the app has no
 * client for its kind yet.
 */
final class LlmProviderUnavailableException extends RuntimeException
{
    public static function disabled(LlmProvider $provider): self
    {
        return new self("The LLM provider \"{$provider->name}\" is turned off. Turn it on in the admin panel (Settings → AI → Providers).");
    }

    public static function unsupported(LlmProviderEnum $kind): self
    {
        return new self("Chatvia cannot call {$kind->value} providers yet.");
    }
}
