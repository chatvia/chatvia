<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Detaches a knowledge file from one agent without deleting the file, which
 * stays in the workspace's shared file pool.
 *
 * By design this refuses a file that several agents use (the dashboard shows
 * "linked to multiple agents"), so a shared file is only managed from the
 * workspace pool.
 */
class UnlinkSourceFileFromAgentAction
{
    /**
     * Returns true if unlinked, false if the file is linked to multiple agents
     * (in which case caller should refuse).
     */
    public function execute(Agent $agent, SourceFile $file): bool
    {
        if ($file->agents()->count() > 1) {
            return false;
        }

        $agent->files()->detach($file->id);

        return true;
    }
}
