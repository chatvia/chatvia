<?php

namespace App\Actions\Agents;

use App\Data\Agent\KnowledgeAnalyzeData;
use App\Models\Agent;
use App\Models\User;
use App\Support\FileAnalyzer;
use Illuminate\Http\UploadedFile;

class AgentKnowledgeAnalyzeFileAction
{
    public function execute(UploadedFile $uploadedFile, User $user, ?Agent $agent = null): KnowledgeAnalyzeData
    {
        if ($agent) {
            $hash = md5_file($uploadedFile->getPathname());

            abort_if(
                boolean: $agent->files()->where('md5_hash', $hash)->exists(),
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
            'encrypted' => $info->encrypted,
            'filetype' => get_file_type($uploadedFile->getMimeType()),
            'is_audio_file' => is_audio_file($uploadedFile->getMimeType()),
        ]);
    }
}
