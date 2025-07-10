<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeUploadController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:10000',
        ]);

        $file = $request->file('file');

        return $agent
            ->addFile($file)
            ->only(['id', 'name', 'size_in_kb', 'mime_type', 'status', 'created_at']);
    }
}
