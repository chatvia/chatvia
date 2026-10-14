<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Conversation\Data\Voice\VoiceCallData;
use Illuminate\Support\Facades\Cache;

/**
 * The live voice calls, in the cache for as long as each can last: what the
 * worker joined to a call needs, and the "hang up" flag the browser's End
 * button sets (the worker checks it every second and ends the call).
 */
final class VoiceCallStore
{
    private const string PREFIX = 'voice:call:';

    /** Kept a little past the call's end, so a late hang-up still finds it. */
    private const int GRACE_SECONDS = 600;

    public function put(VoiceCallData $call): void
    {
        Cache::put(self::PREFIX.$call->call_id, $call->toArray(), $this->ttl($call));
    }

    public function find(string $callId): ?VoiceCallData
    {
        $call = Cache::get(self::PREFIX.$callId);

        return is_array($call) ? VoiceCallData::from($call) : null;
    }

    /** Ask the worker on the call to hang up. */
    public function end(VoiceCallData $call): void
    {
        Cache::put(self::PREFIX.$call->call_id.':ended', true, $this->ttl($call));
    }

    public function isEnded(string $callId): bool
    {
        return Cache::has(self::PREFIX.$callId.':ended');
    }

    public function forget(string $callId): void
    {
        Cache::forget(self::PREFIX.$callId);
        Cache::forget(self::PREFIX.$callId.':ended');
    }

    private function ttl(VoiceCallData $call): int
    {
        return max(1, $call->endsAt() - now()->getTimestamp()) + self::GRACE_SECONDS;
    }
}
