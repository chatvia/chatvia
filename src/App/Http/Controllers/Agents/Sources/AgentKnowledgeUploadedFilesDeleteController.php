<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Actions\DeleteAgentUploadedFilesAction;
use Illuminate\Http\Request;

class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Request $request, Agent $agent, DeleteAgentUploadedFilesAction $delete): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.delete'), 403);

        /** @var User $user */
        $user = $request->user();

        $delete->execute($agent, $user);
    }
}
