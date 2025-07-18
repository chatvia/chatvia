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

        $file = $agent->addFile(
            file: $uploadedFile,
            options: [
                'number_of_pages' => $info->pages,
            ],
        );

        return [
            'id' => $file->id,
            'pages' => $info->pages,
            'mime_type' => $uploadedFile->getMimeType(),
            'encrypted' => $info->encrypted,
        ];
    }
}
