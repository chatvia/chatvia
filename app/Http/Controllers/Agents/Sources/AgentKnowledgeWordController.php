<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Data\Resources\Agent\AgentResourceData;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentKnowledgeWordController extends Controller
{
    public function __invoke(Request $request, Agent $agent)
    {
        return Inertia::render('agents/Sources/Word', [
            'agent' => AgentResourceData::fromModel($agent),
            'fileType' => 'word',
        ]);
    }
}
