<?php

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Conversion\ConversionPrograms;
use Domain\Knowledge\Ingestion\Services\Media\TranscriptionAudio;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Exceptions\UnreadableSourceFileException;
use Domain\Knowledge\Source\File\Exceptions\UnsupportedSourceFileException;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores an uploaded knowledge file and attaches it to the agent. The file is
 * keyed to the agent's owner (the workspace), measured (pages or minutes, kept
 * as the file's length), and left in the "uploaded" state until the owner
 * starts training.
 *
 * The stored object is written before the database rows, into the source's own
 * folder ({@see KnowledgeStoragePath}), so the source's external id is chosen
 * first. When the rows fail, the object is deleted again so no orphan file
 * stays in storage.
 */
class UploadKnowledgeSourceFileAction
{
    public function __construct(
        private readonly FileAnalyzer $analyzer,
        private readonly ConversionPrograms $programs,
        private readonly TranscriptionAudio $audio,
    ) {}

    /**
     * @throws UnreadableSourceFileException when the type is on but this server cannot read this file
     * @throws UnsupportedSourceFileException when the knowledge base cannot ingest the file type
     */
    public function execute(
        Agent $agent,
        UploadedSourceFileData $upload,
        SourceStatus $status = SourceStatus::Uploaded,
    ): SourceFile {
        $this->ensureReadableHere($upload);

        if (! $upload->isSupported()) {
            throw new UnsupportedSourceFileException;
        }

        // Measuring runs outside programs on the upload; it happens before
        // anything is stored or a transaction opens, so a slow or failing
        // program never holds a database transaction or leaves a stored file.
        $analysis = $this->analyzer->analyze($upload->path, $upload->extension());

        $sourceFile = new SourceFile([
            'workspace_id' => $agent->workspace_id,
            'external_id' => (string) Str::uuid7(),
        ]);

        // Stored under a random name with the extension guessed from the content.
        $path = Storage::putFile(KnowledgeStoragePath::forSource($sourceFile), new File($upload->path));

        try {
            return DB::transaction(function () use ($sourceFile, $agent, $upload, $path, $status, $analysis): SourceFile {
                $sourceFile->fill([
                    'filename' => $upload->originalName,
                    'filetype' => $upload->category(),
                    'filepath' => $path,
                    'size_in_kb' => round($upload->sizeInBytes / 1024),
                    'mime_type' => $upload->clientMimeType,
                    'checksum' => md5_file($upload->path),
                    'status' => $status,
                    'length' => $analysis->length,
                    'metadata' => $analysis->toArray(),
                ])->save();

                $sourceFile->agents()->attach($agent->id);

                return $sourceFile;
            });
        } catch (Throwable $e) {
            Storage::delete($path);

            throw $e;
        }
    }

    /**
     * Refuses a file of a type that is on, but that this server cannot read:
     * a format whose program is missing, or a recording the speech-to-text
     * service does not take as it is while there is no ffmpeg to compress it.
     * The recording is judged by the format of its content, as it is stored.
     */
    private function ensureReadableHere(UploadedSourceFileData $upload): void
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

        $format = (string) new File($upload->path)->guessExtension();

        if (! $this->audio->canTranscribe($upload->path, $format)) {
            throw $this->audio->readsFormat($format)
                ? UnreadableSourceFileException::mediaTooLarge()
                : UnreadableSourceFileException::mediaFormat($extension !== '' ? $extension : $format);
        }
    }
}
