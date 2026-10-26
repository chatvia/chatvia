<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\BroadcastAuthRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\MediaChannelAuthData;
use Domain\Conversation\Services\Realtime\MediaChannelAuthorizer;
use Illuminate\Http\JsonResponse;

/**
 * WebSocket channel permission for the hosted chat. Visitors may have no
 * login, and the dashboard's `/broadcasting/auth` reads the dashboard's
 * sign-in, so the hosted chat asks here instead, under its own session: a
 * visitor may listen on the media channel of their own conversations only.
 * Anything else is a 403. Answers 200, as WebSocket clients expect.
 */
class ChatBroadcastAuthController
{
    public function __invoke(
        BroadcastAuthRequest $request,
        #[HostedChatAgent] Agent $agent,
        HostedChatSession $session,
        MediaChannelAuthorizer $authorizer,
    ): JsonResponse {
        $sessionId = $session->find($agent);

        $auth = $sessionId !== null
            ? $authorizer->authorize($agent, $sessionId, $request->channelName(), $request->socketId())
            : null;

        abort_unless($auth instanceof MediaChannelAuthData, 403);

        return response()->json($auth->toArray());
    }
}
