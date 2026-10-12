<?php

namespace Domain\Conversation\Services\Memory;

use App\Identity\Chatter\Actions\DeleteChatterAction;
use Carbon\CarbonInterface;
use Domain\Agent\Actions\Account\PurgeAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;

/**
 * The tagged cache store behind the live chat memory. Every entry carries the
 * workspace ⊃ agent ⊃ session tags (plus the conversation tag for
 * conversation-scoped entries), so a workspace, an agent, a session or one
 * conversation can each be dropped with a single flush. Values are encrypted
 * at rest ({@see CacheCipher}).
 */
class ChatCache
{
    public function __construct(
        private readonly string $sessionId,
        private readonly Agent $agent
    ) {}

    /**
     * The session tag on its own: every entry of the session carries it, so
     * flushing it drops the whole session's memory at once.
     */
    private function getTaggedCache(): TaggedCache
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

    /**
     * Write a session-wide entry with its own lifetime instead of the
     * per-plan retention: forever when `$until` is null.
     */
    public function putUntil(string $key, mixed $value, ?CarbonInterface $until): void
    {
        $until instanceof CarbonInterface
            ? $this->tagsFor(null)->put($key, CacheCipher::encrypt($value), $until)
            : $this->tagsFor(null)->forever($key, CacheCipher::encrypt($value));
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
     * {@see self::tagsFor()} and {@see self::rememberOwner()}), so account
     * teardown can drop an entire workspace's Redis chat memory with a single
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
     * {@see PurgeAgentAction} relies on instead of walking
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
     * workspace ⊃ agent ⊃ session, broad to narrow. Tagged-cache reads hash
     * the full ordered tag set, so every reader and writer must build it here
     * ({@see self::tagsFor()} does): any difference in tags or order gives a
     * different namespace and the read silently misses.
     *
     * @return list<string>
     */
    public static function sessionTags(string $workspaceExternalId, string $agentExternalId, string $sessionId): array
    {
        return [self::workspaceTag($workspaceExternalId), self::agentTag($agentExternalId), self::sessionTag($sessionId)];
    }

    /**
     * Cache key proving `$viewer` owns `$conversationId`. The key itself
     * encodes the (viewer, conversation) pair — a different viewer resolves
     * to a different key, so a `has()` under the wrong viewer is a cache
     * miss. The viewer key carries its kind (`chatter:5` / `user:5`), so a
     * chatter and a staff account with the same id never share a marker.
     * Guests bucket under `guest`.
     */
    public static function ownerKey(?ChatViewerData $viewer, string $conversationId): string
    {
        $owner = $viewer?->key() ?? 'guest';

        return "owner:{$owner}:{$conversationId}";
    }

    /**
     * Stamp an ownership marker for (viewer, conversation). Tagged with both
     * the per-conversation tag (so it gets swept by `flushConversation` when
     * the conversation is deleted) and the workspace tag (so account teardown
     * sweeps it too — see {@see self::workspaceTag()}). Stored `forever`
     * because chatters on protected agents keep their conversations until
     * explicitly deleted — matching how {@see StartChatSessionAction}
     * stores a signed-in chatter's session, which is exactly why the workspace tag
     * matters: without it these markers never expire and outlive the deleted
     * workspace. `$workspaceExternalId` is the agent owner's external id
     * ({@see Agent::workspaceExternalId()}).
     */
    public static function rememberOwner(string $conversationId, ?ChatViewerData $viewer, string $workspaceExternalId): void
    {
        Cache::driver('chat')
            ->tags([self::conversationTag($conversationId), self::workspaceTag($workspaceExternalId)])
            ->forever(self::ownerKey($viewer, $conversationId), true);
    }

    /**
     * True iff the ownership marker for (viewer, conversation) exists — i.e. the
     * same viewer created it. Wrong viewer → different key → miss.
     * `$workspaceExternalId` must be the same agent-owner external id passed to
     * {@see self::rememberOwner()}; the tag set is part of the lookup
     * namespace, so a mismatch reads as a miss (→ a spurious 403 on protected
     * agents).
     */
    public static function isOwnedBy(string $conversationId, ?ChatViewerData $viewer, string $workspaceExternalId): bool
    {
        return Cache::driver('chat')
            ->tags([self::conversationTag($conversationId), self::workspaceTag($workspaceExternalId)])
            ->has(self::ownerKey($viewer, $conversationId));
    }

    public function generateConversationKey(string $conversationId): string
    {
        return "{$this->sessionId}:conversations:{$conversationId}";
    }

    public function generateSourcesKey(string $conversationId): string
    {
        return "conversations:{$conversationId}:sources";
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
        // set, so the prefix is centralized in {@see self::sessionTags()}.
        $tags = self::sessionTags($this->agent->workspaceExternalId(), $this->agent->external_id, $this->sessionId);
        if ($conversationId !== null) {
            $tags[] = self::conversationTag($conversationId);
        }

        return Cache::driver('chat')->tags($tags);
    }

    /**
     * The install's conversation retention applied as the TTL on every
     * entry, the same days that are stamped on chat_conversations.expires_at.
     */
    private function getMessageRetention(): CarbonInterface
    {
        return now()->addDays($this->agent->messageRetentionInDays());
    }
}
