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

        $delete->execute($agent, $file);
    }
}
