<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * Source summaries are off, or no model that may be used reads PDF files.
 */
final class NoSummaryModelException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No AI model that reads PDF files is set up for source summaries (Settings → AI models → Documents).');
    }
}
