<?php

namespace App\Http\Controllers\Agent\Analytics\Tone;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentToneController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        /** @var User $user */
        $user = $agent->user;

        return Inertia::render('agents/Tone', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
