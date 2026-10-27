<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

// Per-user notification channel used by Laravel's broadcast notifications
// (e.g. DataExportReadyNotification). Only the owning user may subscribe.
Broadcast::channel('App.Identity.Workspace.Models.User.{id}', fn (User $user, int $id): bool => $user->id === $id);

Broadcast::channel('agents.{externalId}.crawler', function (User $user, string $externalId): bool {
    $agent = Agent::query()->where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.crawler.progress', function (User $user, string $externalId): bool {
    $agent = Agent::query()->where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.files.progress', function (User $user, string $externalId): bool {
    $agent = Agent::query()->where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.texts.progress', function (User $user, string $externalId): bool {
    $agent = Agent::query()->where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.ingestion.text', function (User $user, string $externalId): bool {
    $agent = Agent::query()->where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.web.ingestion.url', function (User $user, string $externalId): bool {
    $agent = Agent::query()->where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

Broadcast::channel('agents.{externalId}.evals', function (User $user, string $externalId): bool {
    $agent = Agent::query()->where('external_id', $externalId)->first();

    return $agent && Gate::forUser($user)->allows('access-agent', $agent);
});

/*
|--------------------------------------------------------------------------
| Chat Channels (Presence Channels for Handoff)
|--------------------------------------------------------------------------
|
| These channels support both authenticated users (admins/staff) and widget users
| (via session-based auth). The widget will connect with a session token.
| Staff join the conversation's live view, so they need the same
| `agent.chat.view` permission the dashboard's chat log asks for.
|
*/
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId): false|array {
    if (! $user instanceof User) {
        return false;
    }

    $conversation = Conversation::query()->with('session.agent')
        ->where('external_id', $conversationId)->first();

    if (! $conversation) {
        return false;
    }

    $agent = $conversation->session?->agent;

    if (! $agent) {
        return false;
    }

    if (! Gate::forUser($user)->allows('access-agent', $agent)
        || ! app(TeamPermissionPolicy::class)->allowsOnAgent($user, $agent, 'agent.chat.view')) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'type' => 'agent',
    ];
});
