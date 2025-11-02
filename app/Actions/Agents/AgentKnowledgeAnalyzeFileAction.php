<?php

namespace App\Actions\Agents;

use App\Data\Agent\KnowledgeAnalyzeData;
use App\Models\Agent;
use App\Models\User;
use App\Services\Agents\FileAnalyzer;
use App\Services\Tidy\TextCleaner;
use Illuminate\Http\UploadedFile;

class AgentKnowledgeAnalyzeFileAction
{
    public function execute(UploadedFile $uploadedFile, User $user, ?Agent $agent = null): KnowledgeAnalyzeData
    {
        if ($agent) {
            $hash = md5_file(TextCleaner::clean($uploadedFile->getPathname()));

            abort_if(
                boolean: $agent->files()->where('checksum', $hash)->exists(),
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

        return KnowledgeAnalyzeData::from([
            'length' => $info->length,
            'mime_type' => $uploadedFile->getMimeType(),
            'filetype' => get_file_type($uploadedFile->getMimeType()),
            'is_audio_file' => is_audio_file($uploadedFile),
            'is_video_file' => is_video_file($uploadedFile),
        ]);
    }
}
