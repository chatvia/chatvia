<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Document\Contracts\PdfInspector;
use Domain\Knowledge\Source\File\Data\ArchiveUploadResultData;
use Domain\Knowledge\Source\File\Data\ExtractedArchiveFileData;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\File\Services\ArchiveExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Uploads every knowledge file inside a zip archive as its own source file,
 * folders and archives inside it included. Each file goes through the same
 * checks as a file uploaded on its own ({@see UploadKnowledgeSourceFileAction}):
 * a type that is off or that this server cannot read, a locked PDF, or a
 * file the workspace already holds is skipped by name, and the rest are
 * stored. One bad file never fails the archive: the result says what was
 * stored and what was left out.
 */
class UploadKnowledgeArchiveAction
{
    public function __construct(
        private readonly ArchiveExtractor $extractor,
        private readonly UploadKnowledgeSourceFileAction $upload,
        private readonly PdfInspector $pdfInspector,
    ) {}

    public function execute(Agent $agent, UploadedSourceFileData $archive): ArchiveUploadResultData
    {
        $folder = create_temp_dir('/archives/'.Str::uuid7());

        try {
            $contents = $this->extractor->extract($archive->path, $folder);

            $uploaded = 0;
            $length = 0;
            $skipped = $contents->skipped;
            $checksums = [];

            foreach ($contents->files as $file) {
                $checksum = md5_file($file->path);

                // The same bytes twice in the archive, or already in the workspace.
                if (collect($checksums)->containsStrict($checksum) || $this->alreadyUploaded($agent, $checksum)) {
                    $skipped[] = $file->name;

                    continue;
                }

                try {
                    $this->ensureUnlocked($file);
                    $sourceFile = $this->upload->execute($agent, $this->describe($file));
                } catch (DomainValidationException) {
                    $skipped[] = $file->name;

                    continue;
                }

                $checksums[] = $checksum;
                $uploaded++;
                $length += $sourceFile->length;
            }

            return new ArchiveUploadResultData(uploaded: $uploaded, length: $length, skipped: $skipped);
        } finally {
            File::deleteDirectory($folder);
        }
    }

    /**
     * The extracted file described as an upload. No browser named its type,
     * so the client type is the one its extension usually has.
     */
    private function describe(ExtractedArchiveFileData $file): UploadedSourceFileData
    {
        $mimeType = (string) File::mimeType($file->path);

        return new UploadedSourceFileData(
            path: $file->path,
            originalName: Str::length($file->name) <= 255 ? $file->name : File::basename($file->name),
            mimeType: $mimeType,
            clientMimeType: MimeTypes::getDefault()->getMimeTypes(file_extension($file->name))[0] ?? $mimeType,
            sizeInBytes: File::size($file->path),
        );
    }

    private function alreadyUploaded(Agent $agent, string $checksum): bool
    {
        return SourceFile::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('checksum', $checksum)
            ->exists();
    }

    /**
     * A locked PDF is refused the way the upload form refuses one: no reader
     * can open it, so training it would only fail later.
     *
     * @throws DomainValidationException
     */
    private function ensureUnlocked(ExtractedArchiveFileData $file): void
    {
        if (is_pdf_file($file->path) && $this->pdfInspector->isLocked($file->path)) {
            throw new DomainValidationException('file', __('This PDF is locked with a password. Remove the password and upload it again.'));
        }
    }
}
