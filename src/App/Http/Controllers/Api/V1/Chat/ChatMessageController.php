<?php

namespace App\Http\Controllers\Api\V1\Chat;

use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Chat
 *
 * Server-to-server chat is not yet exposed. End-user chat happens through
 * the agent's URL (`agent.chat_url`) with a JWT in `?token=` signed by the
 * customer using the account-wide `jwt_secret` — see the JWT identity flow
 * in the dashboard docs.
 *
 * This endpoint is a stable placeholder for the upcoming sync/stream API.
 */
class ChatMessageController
{
    /**
     * Send message (preview)
     *
     * Currently returns 501 with the chat URL the customer should redirect
     * their user to. A future release will accept `{ message, conversation_id,
     * stream }` here and run the full agent pipeline server-to-server.
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        return response()->json([
            'message' => 'Server-to-server chat is not enabled yet. Redirect end-users to chat_url with a JWT signed by your account jwt_secret.',
            'chat_url' => $agent->chat_url,
            'docs' => '/docs/api',
        ], 501);
    }
}
