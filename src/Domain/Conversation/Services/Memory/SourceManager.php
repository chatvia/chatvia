<?php

namespace Domain\Conversation\Services\Memory;

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
        $cachedSources = $this->cache->get($cacheKey, [], $conversationId);

        $cachedSources[$messageId] = $sources;

        $this->cache->put($cacheKey, $cachedSources, $conversationId);
    }

    public function getSourcesForConversation(string $conversationId): array
    {
        $cacheKey = $this->cache->generateSourcesKey($conversationId);

        return $this->cache->get($cacheKey, [], $conversationId);
    }
}
