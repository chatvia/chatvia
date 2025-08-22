<?php

namespace App\Services\Memory;

/**
 * Manages knowledge sources for chat messages.
 */
class SourceManager
{
    public function __construct(
        private readonly ChatCache $cache
    ) {}

    public function storeSourcesForMessage(string $conversationId, string $messageId, array $sources): void
    {
        $cacheKey = $this->cache->generateSourcesKey($conversationId);
        $cachedSources = $this->cache->get($cacheKey, []);

        $cachedSources[$messageId] = $sources;

        $this->cache->put($cacheKey, $cachedSources);
    }

    public function getSourcesForConversation(string $conversationId): array
    {
        $cacheKey = $this->cache->generateSourcesKey($conversationId);

        return $this->cache->get($cacheKey, []);
    }

    public function getSourcesForMessage(string $conversationId, string $messageId): array
    {
        $sources = $this->getSourcesForConversation($conversationId);

        return $sources[$messageId] ?? [];
    }
}
