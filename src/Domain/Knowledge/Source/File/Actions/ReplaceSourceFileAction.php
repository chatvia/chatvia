<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Files\StartFileIngestionAction;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Exceptions\UnreadableSourceFileException;
use Domain\Knowledge\Source\File\Exceptions\UnsupportedSourceFileException;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Domain\Knowledge\Source\File\Services\SourceFileReadability;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puts a new version in place of a knowledge file and trains it again. The
 * file keeps its id, so every agent that uses it, its retrieval counts and
 * the eval coverage stay; only its content, name and measurements change.
 *
 * The new object is stored before the row points at it, and the old one (and
 * the parser's cached output) is removed only afterwards, so a failure never
 * leaves the file without content. The old chunks, the summary included, are
 * deleted in the same transaction, so nothing of the old version is found
 * any more once the new one is trained.
 */
class ReplaceSourceFileAction
{
    public function __construct(
        private readonly FileAnalyzer $analyzer,
        private readonly SourceFileReadability $readability,
        private readonly StartFileIngestionAction $startIngestion,
    ) {}

    /**
     * @throws UnreadableSourceFileException when the type is on but this server cannot read this file
     * @throws UnsupportedSourceFileException when the knowledge base cannot ingest the file type
     */
    public function execute(Agent $agent, SourceFile $sourceFile, UploadedSourceFileData $upload): SourceFile
    {
        $this->readability->ensure($upload);

        $analysis = $this->analyzer->analyze($upload->path, $upload->extension());
        $oldPaths = array_filter([$sourceFile->filepath, $sourceFile->parser_filepath]);

        $path = Storage::putFileAs(
            KnowledgeStoragePath::forSource($sourceFile),
            new File($upload->path),
            Str::random(40).'.'.$upload->storedExtension(),
        );

        try {
            DB::transaction(function () use ($sourceFile, $upload, $path, $analysis): void {
                $sourceFile->update([
                    'filename' => $upload->originalName,
                    'filetype' => $upload->category(),
                    'filepath' => $path,
                    'parser_filepath' => null,
                    'size_in_kb' => round($upload->sizeInBytes / 1024),
                    'mime_type' => $upload->clientMimeType,
                    'checksum' => md5_file($upload->path),
                    'status' => SourceStatus::Uploaded,
                    'length' => $analysis->length,
                    'metadata' => $analysis->toArray(),
                ]);

                $sourceFile->deleteChunks();
            });
        } catch (Throwable $e) {
            Storage::delete($path);

            throw $e;
        }

        Storage::delete(array_values(array_diff($oldPaths, [$path])));

        $this->startIngestion->execute($sourceFile, $agent);

        return $sourceFile;
    }
}
