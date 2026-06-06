<?php

namespace Domain\Conversation\Services\Streaming\Requests;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class RequestDedupeStore
{
    public function __construct(
        private readonly ?int $ttlSeconds = null,
        private readonly ?string $store = null,
    ) {}

    /**
     * Look up a completed stream's snapshot by request_id.
     *
     * @return array{assistant_message_id: string, content: string, language: ?string, sources: array}|null
     */
    public function find(string $requestId): ?array
    {
        $value = $this->cache()->get($this->key($requestId));

        return is_array($value) ? $value : null;
    }

    public function remember(
        string $requestId,
        string $assistantMessageId,
        string $content,
        ?string $language,
        array $sources = [],
    ): void {
        $this->cache()->put(
            $this->key($requestId),
            [
                'assistant_message_id' => $assistantMessageId,
                'content' => $content,
                'language' => $language,
                'sources' => $sources,
            ],
            $this->ttl()
        );
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
        return $this->ttlSeconds ?? (int) config('chatvia.sse.dedupe.ttl_seconds', 600);
    }
}
