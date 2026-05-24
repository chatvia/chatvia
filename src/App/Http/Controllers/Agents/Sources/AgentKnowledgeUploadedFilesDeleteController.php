<?php

namespace App\Http\Controllers\Agents\Sources;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\Request;

class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: ! has_permission_to_agent($agent, 'agent.sources.delete'),
            code: 403,
        );

        $fileIds = $agent->files()
            ->where('source_files.status', 'uploaded')
            ->pluck('source_files.id');

        SourceFile::whereIn('id', $fileIds)
            ->where('user_id', $request->user()->id)
            ->delete();
    }
}
