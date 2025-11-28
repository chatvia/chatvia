<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use App\Models\SourceFile;
use Illuminate\Http\Request;

class AgentSourcesLinkFileController
{
    public function __invoke(Request $request, Agent $agent, int $fileId)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 403,
        );

        $request->validate([
            'file_id' => ['required', 'exists:source_files,id'],
        ]);

        $user = $request->user();

        /** @var SourceFile $file */
        $file = SourceFile::find($fileId);

        abort_if(
            boolean: $user->cannot('view', $file),
            code: 403,
        );

        $agent->files()->attach($file->id);
    }
}
