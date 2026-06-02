<?php

namespace App\Http\Controllers\Agent\Source\File\Upload;

use Domain\Agent\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Agent\Data\Sources\KnowledgeAnalyzeResourceData;
use Illuminate\Http\Request;

class AgentKnowledgeFilesAnalyzeController
{
    public function __invoke(Request $request, AgentSourceAnalyzeFileAction $action): KnowledgeAnalyzeResourceData
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,txt,md'],
        ]);

        $file = $request->file('file');

        return $action->execute($file, $request->user());
    }
}
