<?php

namespace App\Http\Controllers\KnowledgeFiles;

use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Http\Request;

class KnowledgeFilesDownload
{
    public function __invoke(Request $request, SourceFile $agentFile)
    {
        return $agentFile->download();
    }
}
