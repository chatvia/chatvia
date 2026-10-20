<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Exceptions;

use Support\Exceptions\DomainValidationException;

/**
 * The file's type is on, but this server cannot read this file: the program
 * for its format is not installed (pandoc, catdoc, catppt), or a recording
 * needs ffmpeg to fit the speech-to-text service and the server has none.
 * Refused at upload, so it does not fail later while it is trained. The
 * message is shown to the uploader; over HTTP it is a 422 error on `file`.
 */
class UnreadableSourceFileException extends DomainValidationException
{
    public static function missingProgram(string $extension): self
    {
        return new self('file', __('This server can\'t read .:extension files. Save the file as a PDF or .docx and upload it again.', ['extension' => $extension]));
    }

    public static function mediaTooLarge(): self
    {
        return new self('file', __('This recording is too large to turn into text on this server. Upload a shorter recording.'));
    }

    public static function mediaFormat(string $extension): self
    {
        return new self('file', __('This server can\'t turn .:extension files into text. Upload an MP3 file instead.', ['extension' => $extension]));
    }
}
