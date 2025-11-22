<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use App\Models\SourceFile;

class AgentSourceUnlinkFileController
{
    public function __invoke(Agent $agent, SourceFile $file)
    {
        abort_if(
            boolean: request()->user()->cannot('update', $agent),
            code: 404,
        );

        if ($file->agents()->count() > 1) {
            abort(400, 'File is linked to multiple agents and cannot be unlinked.');
        }

        $agent->files()->detach($file->id);
    }
}
