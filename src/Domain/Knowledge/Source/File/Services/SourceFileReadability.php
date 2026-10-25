<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Services;

use Domain\Knowledge\Document\Conversion\ConversionPrograms;
use Domain\Knowledge\Ingestion\Services\Media\TranscriptionAudio;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Exceptions\UnreadableSourceFileException;
use Domain\Knowledge\Source\File\Exceptions\UnsupportedSourceFileException;
use Illuminate\Http\File;

/**
 * Whether this server can take an uploaded knowledge file: a supported type,
 * with the program its format needs, or a recording the speech-to-text
 * service can take. Shared by a new upload and a file's replacement.
 */
class SourceFileReadability
{
    public function __construct(
        private readonly ConversionPrograms $programs,
        private readonly TranscriptionAudio $audio,
    ) {}

    /**
     * Refuses a file of a type that is on, but that this server cannot read:
     * a format whose program is missing, or a recording the speech-to-text
     * service does not take as it is while there is no ffmpeg to compress it.
     * The recording is judged by the format of its content, as it is stored.
     *
     * @throws UnreadableSourceFileException when the type is on but this server cannot read this file
     * @throws UnsupportedSourceFileException when the knowledge base cannot ingest the file type
     */
    public function ensure(UploadedSourceFileData $upload): void
    {
        $this->ensureReadableType($upload);

        if (! $upload->isSupported()) {
            throw new UnsupportedSourceFileException;
        }
    }

    private function ensureReadableType(UploadedSourceFileData $upload): void
    {
        $extension = $upload->extension();

        if (SourceFileTypes::isDocument($upload->originalName)) {
            if (SourceFileTypes::category($upload->originalName)->isEnabled() && $this->programs->missingFor($extension) !== null) {
                throw UnreadableSourceFileException::missingProgram($extension);
            }

            return;
        }

        if (! SourceFileTypes::isSupportedMedia($upload->mimeType)) {
            return;
        }

        $format = new File($upload->path)->guessExtension() ?? '';

        if (! $this->audio->canTranscribe($upload->path, $format)) {
            throw $this->audio->readsFormat($format)
                ? UnreadableSourceFileException::mediaTooLarge()
                : UnreadableSourceFileException::mediaFormat($extension !== '' ? $extension : $format);
        }
    }
}
