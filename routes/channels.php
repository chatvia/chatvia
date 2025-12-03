<?php

use App\Models\Agent;
use App\Models\ChatConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('agents.{id}.crawler', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.crawler.progress', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.files.progress', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.texts.progress', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.ingestion.text', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

Broadcast::channel('agents.{id}.web.ingestion.url', function ($user, $id) {
    return $user->id === Agent::findOrNew($id)->user_id;
});

/*
|--------------------------------------------------------------------------
| Chat Channels (Presence Channels for Handoff)
|--------------------------------------------------------------------------
|
| These channels support both authenticated users (admins) and widget users
| (via session-based auth). The widget will connect with a session token.
|
*/
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    // Admin authentication - check if user owns the agent
    if ($user instanceof \App\Models\User) {
        $conversation = ChatConversation::with('session.agent')->find($conversationId);

        if (! $conversation) {
            return false;
        }

        $agent = $conversation->session?->agent;

        if (! $agent || $agent->user_id !== $user->id) {
            return false;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'type' => 'agent',
        ];
    }

    // Widget user authentication is handled via custom auth endpoint
    return false;
});
