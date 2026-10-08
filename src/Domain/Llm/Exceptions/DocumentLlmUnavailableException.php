<?php

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * No LLM provider that can read PDF documents is set up on this install.
 */
class DocumentLlmUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Reading PDF documents is not configured on this server.');
    }
}
