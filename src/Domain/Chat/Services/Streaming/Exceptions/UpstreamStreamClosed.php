<?php

namespace Domain\Chat\Services\Streaming\Exceptions;

use RuntimeException;
use Throwable;

class UpstreamStreamClosed extends RuntimeException
{
    public function __construct(string $message = 'Upstream stream closed before completion', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
