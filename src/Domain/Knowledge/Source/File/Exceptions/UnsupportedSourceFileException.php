<?php

namespace Domain\Knowledge\Source\File\Exceptions;

use RuntimeException;

/**
 * The uploaded file is neither a document format the knowledge base can read
 * nor audio or video. The message is shown to the uploader.
 */
class UnsupportedSourceFileException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('Unsupported file type.'));
    }
}
