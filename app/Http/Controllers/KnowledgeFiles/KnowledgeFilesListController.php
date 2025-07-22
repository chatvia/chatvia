<?php

namespace App\Http\Controllers\KnowledgeFiles;

use Illuminate\Http\Request;

class KnowledgeFilesListController
{
    public function __invoke(Request $request)
    {
        return $request->user()->agentFiles()->with('agent')->get();
    }
}
