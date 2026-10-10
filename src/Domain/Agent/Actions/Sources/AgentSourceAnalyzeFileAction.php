<?php

namespace Domain\Agent\Actions\Sources;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Sources\KnowledgeAnalyzeResourceData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Illuminate\Http\UploadedFile;
use Support\Exceptions\DomainValidationException;

/**
 * Inspects an uploaded knowledge file before training — its length, MIME type
 * and kind — so the dashboard can show what will be ingested.
 *
 * Refuses (as a `file` validation error) a file the workspace already uploaded
 * when an agent is given.
 */
class AgentSourceAnalyzeFileAction
{
    public function __construct(
        private readonly FileAnalyzer $fileAnalyzer,
    ) {}

    public function execute(UploadedFile $uploadedFile, User $user, ?Agent $agent = null): KnowledgeAnalyzeResourceData
    {
        if ($agent instanceof Agent && $this->alreadyUploaded($uploadedFile, $user)) {
            throw new DomainValidationException('file', __('You have already uploaded this file.'));
        }

        $info = $this->fileAnalyzer->analyze($uploadedFile->getPathname(), file_extension($uploadedFile));

        return KnowledgeAnalyzeResourceData::from([
            'length' => $info->length,
            'mime_type' => $uploadedFile->getMimeType(),
            'filetype' => SourceFileTypes::category($uploadedFile),
            'is_audio_file' => is_audio_file($uploadedFile),
            'is_video_file' => is_video_file($uploadedFile),
        ]);
    }

    private function alreadyUploaded(UploadedFile $uploadedFile, User $user): bool
    {
        $checksum = md5_file($uploadedFile->getPathname());

        return $user->sourceFiles()->where('checksum', $checksum)->exists();
    }
}
