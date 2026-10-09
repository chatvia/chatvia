<?php

namespace Domain\Conversation\Services\Streaming\Requests;

use Domain\Conversation\Data\Turn\ReplaySnapshotData;
use Domain\Conversation\Services\Memory\CacheCipher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers finished answers by request id for a few minutes (encrypted), so
 * a retried request replays the answer instead of streaming a new one.
 */
class RequestDedupeStore
{
    /** How long an answer is kept when not configured. */
    private const int DEFAULT_TTL_SECONDS = 600;

    public function __construct(
        private readonly ?int $ttlSeconds = null,
        private readonly ?string $store = null,
    ) {}

    /** The completed answer for a request id, or null. */
    public function find(string $requestId): ?ReplaySnapshotData
    {
        $value = CacheCipher::decrypt($this->cache()->get($this->key($requestId)));

        return is_array($value) ? ReplaySnapshotData::from($value) : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     */
    public function remember(
        string $requestId,
        string $assistantMessageId,
        string $content,
        ?string $language,
        array $sources = [],
    ): void {
        $snapshot = new ReplaySnapshotData($assistantMessageId, $content, $language, $sources);

        $this->cache()->put($this->key($requestId), CacheCipher::encrypt($snapshot->toArray()), $this->ttl());
    }

    public function forget(string $requestId): void
    {
        $this->cache()->forget($this->key($requestId));
    }

    private function key(string $requestId): string
    {
        return 'chat:req:'.$requestId;
    }

    private function cache(): Repository
    {
        $store = $this->store ?? config('chatvia.sse.dedupe.store');

        return $store ? Cache::store($store) : Cache::store();
    }

    private function ttl(): int
    {
        return $this->ttlSeconds ?? (int) config('chatvia.sse.dedupe.ttl_seconds', self::DEFAULT_TTL_SECONDS);
    }
}
