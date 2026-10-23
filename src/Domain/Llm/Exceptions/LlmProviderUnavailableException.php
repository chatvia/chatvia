<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use Domain\Llm\Models\LlmProvider;
use RuntimeException;

/**
 * A model's provider cannot be called: it is turned off, or it has no key.
 */
final class LlmProviderUnavailableException extends RuntimeException
{
    public static function disabled(LlmProvider $provider): self
    {
        return new self("The LLM provider \"{$provider->name}\" is turned off. Turn it on in the admin panel (Settings → AI providers).");
    }

    public static function notConnected(LlmProvider $provider): self
    {
        return new self("The LLM provider \"{$provider->name}\" has no API key. Add it in the admin panel (Settings → AI providers).");
    }
}
