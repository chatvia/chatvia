<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Models\Agent;
use Domain\Source\File\Models\SourceFile;
use Illuminate\Http\Request;

class AgentFileSummaryController
{
    public function __invoke(Request $request, Agent $agent, SourceFile $file)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $metadata = $file
            ->summary()
            ->select('metadata')
            ->value('metadata');

        return $metadata ?? [];
    }
}
