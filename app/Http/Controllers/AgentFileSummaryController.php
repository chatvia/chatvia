<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentFile;
use Illuminate\Http\Request;

class AgentFileSummaryController extends Controller
{
    public function __invoke(Request $request, Agent $agent, AgentFile $file)
    {
        if (! $file->summary) {
            return response()->json([
                'error' => 'No summary available for this file',
            ], 404);
        }

        return $file->summary->metadata ?? [];
    }
}
