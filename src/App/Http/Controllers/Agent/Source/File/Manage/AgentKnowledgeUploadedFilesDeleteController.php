<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;

class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Request $request, Agent $agent): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $fileIds = $agent->files()
            ->where('source_files.status', 'uploaded')
            ->pluck('source_files.id');

        SourceFile::whereIn('id', $fileIds)
            ->where('user_id', $request->user()->id)
            ->delete();
    }
}
