<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentFile;
use Illuminate\Http\Request;

class AgentFileSummaryController extends Controller
{
    public function __invoke(Request $request, Agent $agent, AgentFile $file)
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
