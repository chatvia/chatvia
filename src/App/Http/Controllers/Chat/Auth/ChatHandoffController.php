<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Auth;

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moves a dashboard sign-in into an agent's hosted chat. A two-factor
 * challenge started from a chat's sign-in link ends here, signed in on the
 * dashboard (`web`) guard: this mints a one-time chat token and sends the user
 * into the chat, where it signs them in on the chat's own session.
 *
 * A chatter cannot use the dashboard, so their dashboard session is ended on
 * the way. Staff keep theirs.
 */
final class ChatHandoffController
{
    public function __invoke(Request $request, string $agentSlug, ChatterAuthTokenService $tokens): RedirectResponse
    {
        $agent = AgentLocator::resolveAgentBySlug($agentSlug);

        abort_unless($agent instanceof Agent, Response::HTTP_NOT_FOUND);

        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canAccessAgent($agent), Response::HTTP_FORBIDDEN, __('You do not have access to this agent.'));

        $token = $tokens->generateToken($agent, $user, 5);

        if ($user->isChatter()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect(route('chat.home', ['agentSlug' => $agentSlug, 'auth_token' => $token], absolute: false));
    }
}
