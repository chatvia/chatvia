<?php

namespace App\Http\Controllers\KnowledgeFiles;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeFilesDownload
{
    public function __invoke(Request $request, SourceFile $agentFile): StreamedResponse
    {
        // {agentFile} binds by integer id and resolves globally, so authorize
        // ownership before streaming (SourceFilePolicy::view → user_id match).
        abort_if($request->user()->cannot('view', $agentFile), 404);

        return $agentFile->download();
    }
}
