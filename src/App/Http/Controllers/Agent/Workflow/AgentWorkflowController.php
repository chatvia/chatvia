<?php

namespace App\Http\Controllers\Agent\Workflow;

use App\Features\Workflow as WorkflowFeature;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Laravel\Pennant\Feature;

class AgentWorkflowController
{
    public function __invoke(Agent $agent)
    {
        if (! Feature::active(WorkflowFeature::class)) {
            return back()->with('error', 'Workflow feature is not enabled');
        }

        return Inertia::render('agents/Workflow', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
