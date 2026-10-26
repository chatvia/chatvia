<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * No image service is set up on this install, so no picture can be made.
 * The message names the fix in plain words and no vendor.
 */
class ImageGenerationNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Making images is not set up on this server.');
    }
}
