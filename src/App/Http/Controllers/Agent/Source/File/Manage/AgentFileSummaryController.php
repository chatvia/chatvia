<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;

class AgentFileSummaryController
{
    public function __invoke(Request $request, Agent $agent, SourceFile $file)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        // {file} binds globally by id — confirm it belongs to the caller's
        // tenant before returning its summary metadata (cross-tenant leak).
        abort_if($request->user()->cannot('view', $file), 404);

        $metadata = $file
            ->summary()
            ->select('metadata')
            ->value('metadata');

        return $metadata ?? [];
    }
}
