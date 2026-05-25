<?php

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('agents.{externalId}.crawler', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.crawler.progress', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.files.progress', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.texts.progress', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.ingestion.text', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.web.ingestion.url', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.ingestion.qa', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.qa.progress', function (User $user, string $externalId) {
    $agent = Agent::where('external_id', $externalId)->first();

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

    $conversation = ChatConversation::with('session.agent')
        ->where('external_id', $conversationId)->first();

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
