<?php

namespace Domain\Team\Exceptions;

use RuntimeException;

class InviteRejectedException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
