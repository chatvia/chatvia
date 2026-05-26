<?php

namespace App\Http\Controllers\Agents\Sources\Video;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Actions\DeleteSourceFileFromAgentAction;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\Request;

class AgentSourceFileDeleteController
{
    public function __invoke(Request $request, Agent $agent, SourceFile $file, DeleteSourceFileFromAgentAction $delete): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.delete'), 403);

        $delete->execute($agent, $file);
    }
}
