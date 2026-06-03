<?php

namespace App\Http\Controllers\Agent\Source\Video;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\DeleteSourceFileFromAgentAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
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
