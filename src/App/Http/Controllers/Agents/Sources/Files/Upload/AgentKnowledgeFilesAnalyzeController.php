<?php

namespace App\Http\Controllers\Agents\Sources\Files\Upload;

use Domain\Agents\Actions\Sources\AgentSourceAnalyzeFileAction;
use Domain\Agents\Data\Sources\KnowledgeAnalyzeResourceData;
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
