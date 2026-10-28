<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hosted chat's "choose a new password" page, opened from a chatter's
 * reset email. Shows the chatter's email only when the link's chatter belongs
 * to this agent's workspace; the token itself is checked on submit.
 */
class ChatShowResetPasswordController
{
    public function __invoke(Request $request, #[HostedChatAgent] Agent $agent, string $token): Response
    {
        $chatterId = $request->string('chatter')->toString();

        $email = Chatter::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('external_id', $chatterId)
            ->value('email');

        return Inertia::render('auth/pages/ResetPasswordChatter', [
            'token' => $token,
            'chatter' => $chatterId,
            'email' => $email,
            'agentName' => $agent->name,
        ]);
    }
}
