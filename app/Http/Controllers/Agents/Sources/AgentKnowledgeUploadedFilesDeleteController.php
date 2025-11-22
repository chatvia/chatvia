<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Models\Agent;
use App\Models\SourceFile;
use Illuminate\Http\Request;

class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $fileIds = $agent->files()
            ->where('source_files.status', 'uploaded')
            ->pluck('source_files.id');

        SourceFile::whereIn('id', $fileIds)
            ->where('user_id', $request->user()->id)
            ->delete();
    }
}
