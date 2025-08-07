<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Actions\Agents\AgentKnowledgeAnalyzeFileAction;
use App\Models\Agent;
use App\Support\PdfInfo;
use Illuminate\Http\Request;

class AgentKnowledgeFileUploadController
{
    public function __invoke(Request $request, Agent $agent, PdfInfo $pdfInfo, AgentKnowledgeAnalyzeFileAction $action)
    {
        $uploadedFile = $request->file('file');

        $info = $action->execute($uploadedFile, $request->user(), $agent);

        $file = $agent->addFile($uploadedFile);

        return [
            'id' => $file->id,
            'length' => $info->length,
            'mime_type' => $uploadedFile->getMimeType(),
            'encrypted' => $info->encrypted,
        ];
    }
}
