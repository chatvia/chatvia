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
        parent::__construct('No AI model is set up. Add a provider and its models in the admin panel (Settings → AI).');
    }
}
