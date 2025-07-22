<?php

namespace App\Http\Controllers\KnowledgeFiles;

use App\Models\AgentFile;
use Illuminate\Http\Request;

class KnowledgeFilesDownload
{
    public function __invoke(Request $request, AgentFile $agentFile)
    {
        return $agentFile->download();
    }
}
