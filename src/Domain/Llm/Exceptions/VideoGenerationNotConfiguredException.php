<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * No video service is set up on this install, so no video can be made.
 * The message names the fix in plain words and no vendor.
 */
class VideoGenerationNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Making videos is not set up on this server.');
    }
}
