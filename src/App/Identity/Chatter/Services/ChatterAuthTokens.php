<?php

namespace App\Identity\Chatter\Services;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ChatViewerKind;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time tokens that sign someone in to one agent's hosted chat
 * (`/chat/{agentSlug}?auth_token=…`). They carry a sign-in from outside the
 * chat session — a dashboard preview (staff), an accepted invitation (a
 * chatter), an emailed sign-in link — into the chat's own session. The token
 * remembers whether it is for a chatter or a staff account, because their ids
 * come from different tables.
 *
 * A token is cached under its own key prefix, so no other cached value (such
 * as a widget session) can ever be used as one. It works only for the agent it
 * was minted for, and only once.
 */
class ChatterAuthTokens
{
    private const string PREFIX = 'chat-handoff:';

    public function generateToken(Agent $agent, Chatter|User $account, int $ttlMinutes = 60): string
    {
        $token = Str::random(48);

        Cache::put(self::PREFIX.$token, [
            'kind' => $account instanceof Chatter ? ChatViewerKind::Chatter->value : ChatViewerKind::Staff->value,
            'id' => $account->id,
            'agent_id' => $agent->id,
        ], now()->addMinutes($ttlMinutes));

        return $token;
    }

    /**
     * Use up a token for $agent. Returns the account it was minted for, or
     * null when the token is unknown, used, expired, for another agent, or its
     * account is gone. A chatter must still belong to the agent's workspace.
     */
    public function consume(string $token, Agent $agent): Chatter|User|null
    {
        if ($token === '') {
            return null;
        }

        $data = Cache::pull(self::PREFIX.$token);

        if (! is_array($data) || ($data['agent_id'] ?? null) !== $agent->id) {
            return null;
        }

        $id = (int) ($data['id'] ?? 0);

        return match (ChatViewerKind::tryFrom((string) ($data['kind'] ?? ''))) {
            ChatViewerKind::Chatter => Chatter::query()->whereKey($id)->where('workspace_id', $agent->workspace_id)->first(),
            ChatViewerKind::Staff => User::query()->find($id),
            default => null,
        };
    }
}
