<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Agent\Models\Agent;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Carbon;
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
        return Cache::driver('chat')->tags($this->sessionId);
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

    public function forget(string $key, ?string $conversationId = null): bool
    {
        return $this->tagsFor($conversationId)->forget($key);
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
     * Stamp an ownership marker for (user, conversation). Tagged with
     * the per-conversation tag so it gets swept by `flushConversation`
     * when the conversation is deleted. Stored `forever` because
     * chatters on protected agents keep their conversations until
     * explicitly deleted — matching how {@see TurnState::initializeSession()}
     * stores the session itself.
     */
    public static function rememberOwner(string $conversationId, int|string|null $userId): void
    {
        Cache::driver('chat')
            ->tags(self::conversationTag($conversationId))
            ->forever(self::ownerKey($userId, $conversationId), true);
    }

    /**
     * True iff the ownership marker for (user, conversation) exists —
     * i.e. the same user created it. Wrong user → different key → miss.
     */
    public static function isOwnedBy(string $conversationId, int|string|null $userId): bool
    {
        return Cache::driver('chat')
            ->tags(self::conversationTag($conversationId))
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
        $tags = [$this->sessionId];
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
    private function getMessageRetention(): Carbon
    {
        return now()->addDays($this->agent->messageRetentionInDays());
    }
}
