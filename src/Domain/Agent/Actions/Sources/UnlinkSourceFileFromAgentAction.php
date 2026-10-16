<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Detaches a knowledge file from one agent without deleting the file, which
 * stays in the workspace's shared file pool.
 *
 * Only a shared file can be unlinked: one that at least one other agent of the
 * workspace still uses. The dashboard offers "Unlink" only for such a file and
 * "Delete" otherwise. A file no agent uses would stay in the pool with no agent
 * to show or delete it, yet still count as a duplicate on the next upload, so
 * the last link is never removed here.
 */
class UnlinkSourceFileFromAgentAction
{
    /**
     * @return bool true when unlinked; false when this agent does not use the
     *              file or no other agent does, so the caller should refuse
     */
    public function execute(Agent $agent, SourceFile $file): bool
    {
        $usedByThisAgent = $agent->files()->whereKey($file->id)->exists();
        $usedByAnotherAgent = $file->agents()->where('agents.id', '!=', $agent->id)->exists();

        if (! $usedByThisAgent || ! $usedByAnotherAgent) {
            return false;
        }

        $agent->files()->detach($file->id);

        return true;
    }
}
