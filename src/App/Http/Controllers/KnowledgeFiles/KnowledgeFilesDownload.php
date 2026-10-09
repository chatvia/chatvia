<?php

namespace App\Http\Controllers\KnowledgeFiles;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a stored knowledge file of the caller's workspace.
 */
class KnowledgeFilesDownload
{
    public function __invoke(Request $request, SourceFile $sourceFile): StreamedResponse
    {
        // {sourceFile} binds globally by id, so ownership is checked before
        // streaming (SourceFilePolicy::view → workspace match).
        abort_if($request->user()->cannot('view', $sourceFile), 404);

        return Storage::download($sourceFile->filepath);
    }
}
