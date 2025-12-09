<?php

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('agents.{id}.crawler', function (User $user, $id) {
    $agent = Agent::find($id);

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{id}.crawler.progress', function (User $user, $id) {
    $agent = Agent::find($id);

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{id}.files.progress', function (User $user, $id) {
    $agent = Agent::find($id);

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{id}.texts.progress', function (User $user, $id) {
    $agent = Agent::find($id);

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{id}.ingestion.text', function (User $user, $id) {
    $agent = Agent::find($id);

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{id}.web.ingestion.url', function (User $user, $id) {
    $agent = Agent::find($id);

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

/*
|--------------------------------------------------------------------------
| Chat Channels (Presence Channels for Handoff)
|--------------------------------------------------------------------------
|
| These channels support both authenticated users (admins/staff) and widget users
| (via session-based auth). The widget will connect with a session token.
|
*/
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    if (! $user instanceof User) {
        return false;
    }

    $conversation = ChatConversation::with('session.agent')->find($conversationId);

    if (! $conversation) {
        return false;
    }

    $agent = $conversation->session?->agent;

    if (! $agent) {
        return false;
    }

    if (! Gate::forUser($user)->allows('access-agent', $agent)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'type' => 'agent',
    ];
});
