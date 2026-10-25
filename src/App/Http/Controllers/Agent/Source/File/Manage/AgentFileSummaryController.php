<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;

/**
 * Returns the analysis metadata of a file's summary chunk (type, audience,
 * topics, …) for the file details panel. Agent access and the
 * `agent.sources.train.files` permission are checked by the route middleware.
 */
class AgentFileSummaryController
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request, Agent $agent, SourceFile $file): array
    {
        // {file} binds by id inside the workspace: it must also be one of this
        // agent's files before its metadata is returned.
        abort_unless($agent->files()->whereKey($file->id)->exists(), 404);
        abort_if($request->user()->cannot('view', $file), 404);

        return $file->summary()->value('metadata') ?? [];
    }
}
