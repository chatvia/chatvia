<?php

namespace Domain\Agent\Actions\Sources;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Sources\KnowledgeAnalyzeResourceData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\FileAnalyzer;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;
use Illuminate\Http\UploadedFile;

class AgentSourceAnalyzeFileAction
{
    public function execute(UploadedFile $uploadedFile, User $user, ?Agent $agent = null): KnowledgeAnalyzeResourceData
    {
        if ($agent) {
            $hash = md5_file(TextCleaner::clean($uploadedFile->getPathname()));

            abort_if(
                boolean: $user->sourceFiles()->where('checksum', $hash)->exists(),
                code: 422,
                message: 'You have already uploaded this file.'
            );
        }

        $info = FileAnalyzer::analyze($uploadedFile);

        abort_if(
            boolean: $user->canUploadPages($info->length),
            code: 422,
            message: 'File upload limit exceeded. This document requires '.$info->length.' pages.'
        );

        return KnowledgeAnalyzeResourceData::from([
            'length' => $info->length,
            'mime_type' => $uploadedFile->getMimeType(),
            'filetype' => get_file_type($uploadedFile->getMimeType()),
            'is_audio_file' => is_audio_file($uploadedFile),
            'is_video_file' => is_video_file($uploadedFile),
        ]);
    }
}
