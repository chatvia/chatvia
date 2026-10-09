<?php

namespace Domain\Conversation\Services\Streaming\Exceptions;

use RuntimeException;

/**
 * Thrown into a turn's event generator when the browser went away, so the
 * turn can keep the partial answer instead of streaming to nobody.
 */
class ClientDisconnected extends RuntimeException
{
    public function __construct(string $message = 'Client disconnected mid-stream')
    {
        parent::__construct($message);
    }
}
