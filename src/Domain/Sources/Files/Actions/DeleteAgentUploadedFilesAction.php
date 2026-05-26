<?php

namespace Domain\Sources\Files\Actions;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;

class DeleteAgentUploadedFilesAction
{
    public function execute(Agent $agent, User $user): int
    {
        $fileIds = $agent->files()
            ->where('source_files.status', 'uploaded')
            ->pluck('source_files.id');

        return SourceFile::whereIn('id', $fileIds)
            ->where('user_id', $user->id)
            ->delete();
    }
}
