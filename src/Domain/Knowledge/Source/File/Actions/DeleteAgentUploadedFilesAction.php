<?php

namespace Domain\Knowledge\Source\File\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;

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
