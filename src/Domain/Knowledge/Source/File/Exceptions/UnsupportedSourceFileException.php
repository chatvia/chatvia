<?php

namespace Domain\Knowledge\Source\File\Exceptions;

use Support\Exceptions\DomainValidationException;

/**
 * The uploaded file is neither a document format the knowledge base can read
 * nor audio or video. The message is shown to the uploader; over HTTP it is a
 * 422 error on the `file` field.
 */
class UnsupportedSourceFileException extends DomainValidationException
{
    public function __construct()
    {
        parent::__construct('file', __('Unsupported file type.'));
    }
}
