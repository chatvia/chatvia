<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgePdfController extends Controller
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Pdf', [
            'agent' => $agent,
        ]);
    }
}
