<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Exceptions;

use Domain\Conversation\Services\Turn\TurnStops;
use RuntimeException;

/**
 * Thrown from inside a turn once its Stop was asked for
 * ({@see TurnStops}), so the turn ends
 * where it is and keeps its partial answer, as after a lost connection.
 */
class TurnStopped extends RuntimeException
{
    public function __construct(string $message = 'Turn stopped by the visitor')
    {
        parent::__construct($message);
    }
}
