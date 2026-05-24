<?php

namespace App\Http\Controllers\Agents\Analytics;

use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;

class AgentSourcesDataController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $sources = $agent->mostAccessedFiles()
            ->limit(50)
            ->get()
            ->map(function ($file) {
                return [
                    'name' => $file->filename,
                    'accessed' => $file->retrieval_count,
                    'mime_type' => $file->mime_type,
                    'last_retrieved_at' => $file->last_retrieved_at,
                ];
            });

        return response()->json($sources);
    }
}
