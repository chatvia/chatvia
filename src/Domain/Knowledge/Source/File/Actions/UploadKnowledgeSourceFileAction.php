<?php

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\FileAnalyzer;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Persist a knowledge-source upload to disk, attach it to the agent, and
 * queue ingestion. Throws when the file type is not one Mistral OCR can parse
 * (see config/mimes.php `ocr`) nor audio/video — the controller maps that to a
 * 422.
 *
 * Body previously lived on Agent::addFile() — moved here so the model
 * carries data + relations and the orchestration (storage write, analyzer
 * call, pivot attach, S3 cleanup on failure) lives in an Action.
 */
class UploadKnowledgeSourceFileAction
{
    public function execute(
        Agent $agent,
        UploadedFile $file,
        SourceStatus $status = SourceStatus::Uploaded,
    ): SourceFile {
        if (! is_ocr_supported($file) && ! is_audio_file($file) && ! is_video_file($file)) {
            throw new RuntimeException('Unsupported file type.');
        }

        $path = Storage::putFile(
            path: 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/files',
            file: $file,
        );

        try {
            return DB::transaction(function () use ($agent, $file, $path, $status): SourceFile {
                $analyzer = FileAnalyzer::analyze($file);

                /** @var SourceFile $sourceFile */
                $sourceFile = SourceFile::create([
                    'user_id' => $agent->user_id,
                    'filename' => $file->getClientOriginalName(),
                    'filetype' => file_category($file),
                    'filepath' => $path,
                    'size_in_kb' => round($file->getSize() / 1024),
                    'mime_type' => $file->getClientMimeType(),
                    'checksum' => md5_file($file->getPathname()),
                    'status' => $status->value,
                    'length' => $analyzer->length,
                    'metadata' => $analyzer->toArray(),
                ]);

                $sourceFile->agents()->attach($agent->id);

                return $sourceFile;
            });
        } catch (Throwable $e) {
            // The transaction rolled back, but Storage::putFile is outside the
            // DB scope — clean up the orphaned S3 object before bubbling.
            Storage::delete($path);

            throw $e;
        }
    }
}
