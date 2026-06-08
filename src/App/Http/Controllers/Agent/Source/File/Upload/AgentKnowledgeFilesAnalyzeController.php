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
            // Extension-based: exotic OCR formats share generic MIMEs, so the
            // mimes rule can't gate them. max:51200 = Mistral's 50 MB cap.
            'file' => ['required', 'file', 'extensions:'.implode(',', ocr_supported_extensions()), 'max:51200'],
        ]);

        $file = $request->file('file');

        return $action->execute($file, $request->user());
    }
}
