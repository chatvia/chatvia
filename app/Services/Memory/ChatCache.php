<?php

namespace App\Services\Memory;

use App\Models\Agent;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ChatCache
{
    public function __construct(
        private readonly string $sessionId,
        private readonly Agent $agent
    ) {}

    public function getTaggedCache(): TaggedCache
    {
        return Cache::driver('chat')->tags($this->sessionId);
    }

    public function put(string $key, mixed $value): void
    {
        $this->getTaggedCache()->put(
            key: $key,
            value: $value,
            ttl: $this->getMessageRetention()
        );
    }

    public function get(string $key, mixed $default = []): mixed
    {
        return $this->getTaggedCache()->get($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->getTaggedCache()->has($key);
    }

    public function forget(string $key): bool
    {
        return $this->getTaggedCache()->forget($key);
    }

    public function flush(): bool
    {
        return $this->getTaggedCache()->flush();
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

    private function getMessageRetention(): Carbon
    {
        return now()->addDays($this->agent->messageRetentionInDays());
    }
}
