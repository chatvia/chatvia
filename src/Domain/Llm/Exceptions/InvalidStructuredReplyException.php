<?php

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * The model was asked for JSON but its reply held none that could be decoded.
 * The reply itself is not part of the message: it is derived from customer
 * content and must not reach the logs.
 */
class InvalidStructuredReplyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The model reply did not contain valid JSON.');
    }
}
