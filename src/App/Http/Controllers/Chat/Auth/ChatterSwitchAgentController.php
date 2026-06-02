<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Workspace\Models\User;
use App\Chatter\Services\ChatterAuthTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChatterSwitchAgentController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokenService $authTokenService)
    {
        /** @var User $user */
        $user = $request->user();

        if (Gate::denies('view', $agent)) {
            throw ValidationException::withMessages([
                'agent' => 'You do not have access to this agent',
            ]);
        }

        // Switch the user's agent
        $authToken = $authTokenService->generateToken($agent, $user, 5);

        return Inertia::location($agent->chat_url.'?auth_token='.$authToken);
    }
}
