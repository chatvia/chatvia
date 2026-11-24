<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Actions\Sources\UnlinkSourceFileFromAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Removes several files from an agent in one call (the "delete selected"
 * button of the trained files list).
 *
 * A file that another agent also uses is only unlinked from this agent, so
 * the other agent keeps its knowledge; a file only this agent uses is
 * deleted. Ids of files the agent does not use are skipped.
 */
class RemoveSourceFilesFromAgentAction
{
    public function __construct(
        private readonly UnlinkSourceFileFromAgentAction $unlink,
        private readonly DeleteSourceFileFromAgentAction $delete,
    ) {}

    /**
     * @param  list<int>  $fileIds
     * @return int how many files were removed from the agent
     */
    public function execute(Agent $agent, array $fileIds): int
    {
        $files = $agent->files()->whereIn('source_files.id', $fileIds)->get();

        $files->each(function (SourceFile $file) use ($agent): void {
            // Unlinking refuses a file no other agent uses: that one is deleted.
            if (! $this->unlink->execute($agent, $file)) {
                $this->delete->execute($agent, $file);
            }
        });

        return $files->count();
    }
}
