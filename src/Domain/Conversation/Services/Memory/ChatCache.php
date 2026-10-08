<?php

namespace Domain\Conversation\Services\Memory;

use App\Identity\Chatter\Actions\DeleteChatterAction;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Agent\Observers\AgentObserver;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;

class ChatCache
{
    public function __construct(
        private readonly string $sessionId,
        private readonly Agent $agent
    ) {}

    /**
     * Session-wide tagged cache. Use this for entries that span the whole
     * session (the conversation list, session data). Conversation-scoped
     * writes go through {@see self::put()} with the $conversationId arg so
     * they also pick up a per-conversation tag.
     */
    public function getTaggedCache(): TaggedCache
    {
        return Cache::driver('chat')->tags(self::sessionTag($this->sessionId));
    }

    /**
     * Write a cache entry with the per-plan TTL. Passing $conversationId
     * tags it with the conversation in addition to the session, so a
     * single conversation's entries can be wiped without flushing the
     * whole session (used by the delete + prune paths).
     */
    public function put(string $key, mixed $value, ?string $conversationId = null): void
    {
        $this->tagsFor($conversationId)->put(
            key: $key,
            value: CacheCipher::encrypt($value),
            ttl: $this->getMessageRetention()
        );
    }

    public function get(string $key, mixed $default = [], ?string $conversationId = null): mixed
    {
        return CacheCipher::decrypt(
            $this->tagsFor($conversationId)->get($key),
            $default
        );
    }

    public function has(string $key, ?string $conversationId = null): bool
    {
        return $this->tagsFor($conversationId)->has($key);
    }

    public function flush(): bool
    {
        return $this->getTaggedCache()->flush();
    }

    /**
     * Nuke every cache entry tagged with this conversation in one shot.
     * Called when the conversation is deleted (manual or via the daily
     * prune) so Redis matches the DB without waiting on per-entry TTL.
     */
    public function flushConversation(string $conversationId): bool
    {
        return Cache::driver('chat')->tags(self::conversationTag($conversationId))->flush();
    }

    /**
     * Per-conversation tag string. Exposed statically so callers without
     * a ChatCache instance (e.g. {@see Conversation::booted}, the
     * prune command) can flush a conversation's cache without having to
     * resolve the session.
     */
    public static function conversationTag(string $conversationId): string
    {
        return "conversation:{$conversationId}";
    }

    /**
     * Per-workspace tag. Every chat-memory entry carries it (see
     * {@see self::tagsFor()}, {@see self::rememberOwner()} and
     * {@see TurnState::getChatCache()}), so account teardown can drop an
     * entire workspace's Redis chat memory with a single
     * `tags(workspaceTag($id))->flush()` instead of walking every session and
     * conversation — including the `forever` ownership markers and member
     * session entries that otherwise never expire.
     *
     * Keyed by the workspace's `external_id` (uuid7), not the integer
     * `user_id`, to stay consistent with every other id in the tag scheme
     * (session/conversation tags are uuids too) and avoid any ambiguity with
     * a bare integer. Resolve it via {@see Agent::workspaceExternalId()}.
     */
    public static function workspaceTag(string $workspaceExternalId): string
    {
        return "workspace:{$workspaceExternalId}";
    }

    /**
     * Per-agent tag. Mirrors {@see self::workspaceTag()} one level down the
     * hierarchy: every session-scoped entry carries it (see
     * {@see self::sessionTags()}), so deleting a single agent can drop its
     * entire Redis chat memory with one `tags(agentTag($id))->flush()` — the
     * same one-shot pattern workspace teardown uses, and what
     * {@see AgentObserver} relies on instead of walking
     * every session and conversation by hand.
     *
     * Keyed by the agent's `external_id` (uuid7), consistent with every other
     * id in the tag scheme.
     */
    public static function agentTag(string $agentExternalId): string
    {
        return "agent:{$agentExternalId}";
    }

    /**
     * Per-session tag. The narrowest scope in the {@see self::sessionTags()}
     * prefix, and the single tag a "wipe this whole session" flush targets
     * ({@see self::getTaggedCache()},
     * {@see DeleteChatterAction}). Prefixed like
     * the rest of the scheme (workspace:/agent:/conversation:) so the session
     * id is never a bare tag, and keyed by the session's `external_id` (uuid7).
     */
    public static function sessionTag(string $sessionExternalId): string
    {
        return "session:{$sessionExternalId}";
    }

    /**
     * The ordered tag prefix every session-scoped entry shares —
     * workspace ⊃ agent ⊃ session, broad to narrow. Single source of truth so
     * the two builders that touch the session-data key — {@see self::tagsFor()}
     * (which may append a conversation tag) and {@see TurnState::getChatCache()}
     * — cannot drift: tagged-cache reads hash the full ordered tag set, so any
     * divergence in tags or order yields a different namespace and the read
     * silently misses.
     *
     * @return list<string>
     */
    public static function sessionTags(string $workspaceExternalId, string $agentExternalId, string $sessionId): array
    {
        return [self::workspaceTag($workspaceExternalId), self::agentTag($agentExternalId), self::sessionTag($sessionId)];
    }

    /**
     * Cache key proving `$userId` owns `$conversationId`. The key itself
     * encodes the (user, conversation) pair — a different user resolves
     * to a different key, so a `has()` under the wrong user is a cache
     * miss. Guests bucket under `user:guest` so an anonymous owner
     * doesn't collide with a real user id.
     */
    public static function ownerKey(int|string|null $userId, string $conversationId): string
    {
        $user = $userId === null || $userId === '' ? 'guest' : $userId;

        return "user:{$user}:{$conversationId}";
    }

    /**
     * Stamp an ownership marker for (user, conversation). Tagged with both
     * the per-conversation tag (so it gets swept by `flushConversation` when
     * the conversation is deleted) and the workspace tag (so account teardown
     * sweeps it too — see {@see self::workspaceTag()}). Stored `forever`
     * because chatters on protected agents keep their conversations until
     * explicitly deleted — matching how {@see TurnState::initializeSession()}
     * stores the session itself, which is exactly why the workspace tag
     * matters: without it these markers never expire and outlive the deleted
     * workspace. `$workspaceExternalId` is the agent owner's external id
     * ({@see Agent::workspaceExternalId()}).
     */
    public static function rememberOwner(string $conversationId, int|string|null $userId, string $workspaceExternalId): void
    {
        Cache::driver('chat')
            ->tags([self::conversationTag($conversationId), self::workspaceTag($workspaceExternalId)])
            ->forever(self::ownerKey($userId, $conversationId), true);
    }

    /**
     * True iff the ownership marker for (user, conversation) exists — i.e. the
     * same user created it. Wrong user → different key → miss.
     * `$workspaceExternalId` must be the same agent-owner external id passed to
     * {@see self::rememberOwner()}; the tag set is part of the lookup
     * namespace, so a mismatch reads as a miss (→ a spurious 403 on protected
     * agents).
     */
    public static function isOwnedBy(string $conversationId, int|string|null $userId, string $workspaceExternalId): bool
    {
        return Cache::driver('chat')
            ->tags([self::conversationTag($conversationId), self::workspaceTag($workspaceExternalId)])
            ->has(self::ownerKey($userId, $conversationId));
    }

    public function generateConversationKey(string $conversationId): string
    {
        return "{$this->sessionId}:conversations:{$conversationId}";
    }

    public function generateSourcesKey(string $conversationId): string
    {
        return "conversations:{$conversationId}:sources";
    }

    public function getAgent(): Agent
    {
        return $this->agent;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Tag combo for a write. Conversation-scoped writes carry both the
     * session tag (so a session-wide flush still wipes them) and the
     * conversation tag (so a single-conversation flush works too).
     */
    private function tagsFor(?string $conversationId): TaggedCache
    {
        // The workspace ⊃ agent ⊃ session prefix lets account- and
        // single-agent teardown each flush all matching chat memory in one
        // call; the optional conversation tag keeps the per-conversation flush
        // working. ORDER MATTERS: tagged-cache reads hash the full ordered tag
        // set, so the prefix is centralized in {@see self::sessionTags()} and
        // MUST match every other reader of the session-data key — in particular
        // {@see TurnState::getChatCache()} — or the namespaces diverge and
        // reads silently miss.
        $tags = self::sessionTags($this->agent->workspaceExternalId(), $this->agent->external_id, $this->sessionId);
        if ($conversationId !== null) {
            $tags[] = self::conversationTag($conversationId);
        }

        return Cache::driver('chat')->tags($tags);
    }

    /**
     * Per-plan retention applied as the TTL on every entry. Agent reads
     * through team->owner->capabilities->retentionDays so this stays in
     * lock-step with what's stamped on chat_conversations.expires_at.
     */
    private function getMessageRetention(): CarbonInterface
    {
        return now()->addDays($this->agent->messageRetentionInDays());
    }
}
