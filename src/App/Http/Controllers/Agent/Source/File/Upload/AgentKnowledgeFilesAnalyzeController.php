<?php

namespace App\Http\Controllers\Agent\Source\File\Upload;

use App\Http\Requests\Agent\Source\File\AnalyzeKnowledgeFileRequest;
use Domain\Agent\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Agent\Data\Sources\KnowledgeAnalyzeResourceData;

/**
 * Measures a file (pages or minutes) before it is uploaded, so the owner sees
 * its length and kind.
 */
class AgentKnowledgeFilesAnalyzeController
{
    public function __invoke(AnalyzeKnowledgeFileRequest $request, AgentSourceAnalyzeFileAction $action): KnowledgeAnalyzeResourceData
    {
        return $action->execute($request->file('file'), $request->user());
    }
}
