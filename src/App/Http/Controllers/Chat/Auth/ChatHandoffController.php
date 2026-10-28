<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Auth;

use App\Identity\Chatter\Services\ChatterAuthTokens;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AgentLocator;
use Domain\Agent\Services\AgentUrlBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Response;

/**
 * Moves a dashboard sign-in into an agent's hosted chat. A two-factor
 * challenge started from a chat's sign-in link ends here, signed in on the
 * dashboard (`web`) guard: this mints a one-time chat token and sends the
 * staff account into the chat, where it signs them in on the chat's own
 * session (`chat_staff`). Only staff get here: chatters have no dashboard
 * sign-in. Staff keep their dashboard session.
 */
final class ChatHandoffController
{
    public function __invoke(Request $request, string $agentSlug, ChatterAuthTokens $tokens): RedirectResponse
    {
        $agent = AgentLocator::resolveAgentBySlug($agentSlug);

        abort_unless($agent instanceof Agent, Response::HTTP_NOT_FOUND);

        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canAccessAgent($agent), Response::HTTP_FORBIDDEN, __('You do not have access to this agent.'));

        $token = $tokens->generateToken($agent, $user, 5);

        // Absolute: the chat may live on the agent's custom domain.
        return redirect((string) Uri::of(AgentUrlBuilder::chatUrl($agent))->withQuery(['auth_token' => $token]));
    }
}
