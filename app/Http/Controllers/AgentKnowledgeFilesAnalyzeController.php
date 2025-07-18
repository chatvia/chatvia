<?php

namespace App\Http\Controllers;

use App\Actions\Agents\AgentKnowledgeAnalyzeFileAction;
use Illuminate\Http\Request;

class AgentKnowledgeFilesAnalyzeController
{
    public function __invoke(Request $request, AgentKnowledgeAnalyzeFileAction $action)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,docx,txt,md'],
        ]);

        $file = $request->file('file');

        return $action->execute($file, $request->user());
    }
}
