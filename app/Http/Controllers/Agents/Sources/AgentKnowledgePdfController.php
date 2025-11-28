<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Resources\Agent\AgentResourceData;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgePdfController extends Controller
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return Inertia::render('agents/Sources/Pdf', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
