<?php

namespace Domain\Knowledge\Source\File\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Data\KnowledgeBase\KnowledgeFileData;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Lists every knowledge file of the user's workspace with the agent that uses
 * it, for the account-wide knowledge base page. A file used by two agents is
 * listed twice. Scoped to the workspace, not to the signed-in row, so a
 * teammate sees the owner's files like every other surface.
 */
class ListUserKnowledgeFilesAction
{
    /**
     * @return list<KnowledgeFileData>
     */
    public function execute(User $user): array
    {
        $workspaceId = $user->workspace()->id;
        // Used both as the whereHas filter (a query builder) and as the eager
        // load constraint (the relation).
        $workspaceAgents = fn (Builder|MorphToMany $agents) => $agents->where('agents.user_id', $workspaceId);

        return SourceFile::query()
            ->whereHas('agents', $workspaceAgents)
            ->with(['agents' => $workspaceAgents])
            ->get()
            ->flatMap(fn (SourceFile $file): array => $file->agents
                ->map(fn (Agent $agent): KnowledgeFileData => KnowledgeFileData::fromFile($file, $agent))
                ->all())
            ->values()
            ->all();
    }
}
