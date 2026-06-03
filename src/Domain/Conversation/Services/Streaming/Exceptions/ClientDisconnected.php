<?php

namespace Domain\Conversation\Services\Streaming\Exceptions;

use RuntimeException;

class ClientDisconnected extends RuntimeException
{
    public function __construct(string $message = 'Client disconnected mid-stream')
    {
        parent::__construct($message);
    }
}
