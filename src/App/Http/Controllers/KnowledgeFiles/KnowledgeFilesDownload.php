<?php

namespace App\Http\Controllers\KnowledgeFiles;

use Domain\Source\File\Models\SourceFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeFilesDownload
{
    public function __invoke(Request $request, SourceFile $agentFile): StreamedResponse
    {
        return $agentFile->download();
    }
}
