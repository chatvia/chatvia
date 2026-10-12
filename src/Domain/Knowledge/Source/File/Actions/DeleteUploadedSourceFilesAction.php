<?php

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Discards the agent's files that were uploaded but never sent to training
 * (the "clear" button of the upload list).
 *
 * Only the workspace's own files are touched, and each file is deleted as a
 * model so its observer also removes the stored object and the agent links.
 */
class DeleteUploadedSourceFilesAction
{
    /**
     * @return int how many files were deleted
     */
    public function execute(Agent $agent): int
    {
        $files = $agent->files()
            ->where('source_files.status', SourceStatus::Uploaded)
            ->where('source_files.workspace_id', $agent->workspace_id)
            ->get();

        $files->each(fn (SourceFile $file) => $file->delete());

        return $files->count();
    }
}
