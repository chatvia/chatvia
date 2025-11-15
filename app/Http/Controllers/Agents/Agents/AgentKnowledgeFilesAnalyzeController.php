<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Actions\Agents\AgentSourceAnalyzeFileAction;
use Illuminate\Http\Request;

class AgentKnowledgeFilesAnalyzeController
{
    public function __invoke(Request $request, AgentSourceAnalyzeFileAction $action)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,txt,md'],
        ]);

        $file = $request->file('file');

        return $action->execute($file, $request->user());
    }
}
