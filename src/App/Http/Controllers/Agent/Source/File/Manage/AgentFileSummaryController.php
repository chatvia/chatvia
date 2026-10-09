<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;

/**
 * Returns the analysis metadata of a file's summary chunk (type, audience,
 * topics, …) for the file details panel.
 */
class AgentFileSummaryController
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request, Agent $agent, SourceFile $file): array
    {
        // {file} binds globally by id: confirm it belongs to the caller's
        // workspace before returning its metadata.
        abort_if($request->user()->cannot('view', $file), 404);

        return $file->summary()->value('metadata') ?? [];
    }
}
