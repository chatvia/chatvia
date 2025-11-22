<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\SourceFile;
use Illuminate\Http\Request;

class AgentFileSummaryController extends Controller
{
    public function __invoke(Request $request, Agent $agent, SourceFile $file)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        if (! $file->summary) {
            return response()->json([
                'error' => 'No summary available for this file',
            ], 404);
        }

        return $file->summary->metadata ?? [];
    }
}
