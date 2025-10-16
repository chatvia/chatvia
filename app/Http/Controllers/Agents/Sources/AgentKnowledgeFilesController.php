<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Agent\KnowledgeFileData;
use App\Models\Agent;
use Exception;
use Illuminate\Http\Request;

class AgentKnowledgeFilesController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $request->validate([
            'file_type' => ['required', 'in:pdf,word'],
        ]);

        $fileType = $request->input('file_type');

        $files = match ($fileType) {
            'pdf' => $agent->pdfFiles()->get(),
            'word' => $agent->wordFiles()->get(),
            default => throw new Exception('Invalid file type'),
        };

        return KnowledgeFileData::fromFiles($files);
    }
}
