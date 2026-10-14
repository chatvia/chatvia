<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * The catalog has no model the app may use: no provider and model has been
 * set up, or all are turned off.
 */
final class NoLlmAvailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No AI model is set up. Connect a provider in the admin panel (Settings → AI providers), then add its models (Settings → AI models).');
    }
}
