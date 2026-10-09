<?php

namespace App\Http\Controllers\Chat\Auth;

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Moves a signed-in chatter to another agent's hosted chat with a short-lived
 * sign-in token.
 */
class ChatterSwitchAgentController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokenService $authTokenService)
    {
        /** @var User $user */
        $user = $request->user();

        if (Gate::denies('view', $agent)) {
            throw ValidationException::withMessages([
                'agent' => __('You do not have access to this agent'),
            ]);
        }

        $authToken = $authTokenService->generateToken($agent, $user, 5);

        return Inertia::location($agent->chat_url.'?auth_token='.$authToken);
    }
}
