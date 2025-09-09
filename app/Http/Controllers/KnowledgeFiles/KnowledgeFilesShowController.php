<?php

namespace App\Http\Controllers\KnowledgeFiles;

use Illuminate\Http\Request;
use Inertia\Inertia;

class KnowledgeFilesShowController
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Sources');
    }
}
