<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * A voice call could not be opened or joined: voice is not set up, or the
 * provider refused or did not answer. The caller gets a plain "try again"
 * message; the details go to the log.
 */
class RealtimeCallFailedException extends RuntimeException {}
