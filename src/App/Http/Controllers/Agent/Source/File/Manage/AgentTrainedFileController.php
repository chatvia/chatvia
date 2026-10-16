<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use App\Http\Requests\Agent\Source\File\ListTrainedFilesRequest;
use Domain\Agent\Actions\Sources\UnlinkSourceFileFromAgentAction;
use Domain\Agent\Data\Resources\Sources\AgentSourcesListData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Actions\AttachSourceToAgentAction;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;

/**
 * The shared file pool of a workspace: lists trained files an agent does not
 * use yet, and links or unlinks one. Agent access is checked by the route
 * middleware; access to the file by its policy.
 */
class AgentTrainedFileController
{
    /**
     * @return AgentSourcesListData[]
     */
    public function index(ListTrainedFilesRequest $request, Agent $agent): array
    {
        // Files belong to the workspace (the agent's owner), not to the
        // teammate who is looking.
        $files = SourceFile::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('status', SourceStatus::Completed)
            ->where('filetype', $request->fileType())
            ->whereDoesntHave('agents', fn ($query) => $query->where('agents.id', $agent->id))
            ->with('agents', fn ($query) => $query->select('agents.id', 'name'))
            ->select('id', 'filename', 'filetype', 'length')
            ->get();

        return AgentSourcesListData::fromSources($files);
    }

    public function store(Request $request, Agent $agent, SourceFile $file, AttachSourceToAgentAction $attach): void
    {
        // Another workspace's file answers like an unknown id (`can.access.agent`).
        abort_if($request->user()->cannot('view', $file), 403);

        $attach->execute($agent, $file);
    }

    public function destroy(Agent $agent, SourceFile $file, UnlinkSourceFileFromAgentAction $unlink): void
    {
        if (! $unlink->execute($agent, $file)) {
            abort(400, __('Only a file that other agents also use can be unlinked. Delete it instead.'));
        }
    }
}
