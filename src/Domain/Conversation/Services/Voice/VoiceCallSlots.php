<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * How many voice calls run at once. Each call holds one `voice` queue worker
 * for its whole length, so a call only starts while a worker is free: a call
 * whose worker never comes would leave the caller listening to "Connecting…".
 * The capacity is the `voice` Horizon supervisor's worker count (Settings →
 * Platform → Queue workers).
 *
 * A slot is taken for a short time when the call opens and held for the
 * call's full length once the worker joins, so a call nobody joins frees its
 * slot on its own. The list sits in the cache under a lock.
 */
final class VoiceCallSlots
{
    private const string KEY = 'voice:slots';

    /**
     * Take a slot for the call, for this many seconds.
     *
     * @return bool false when every slot is in use
     */
    public function take(string $callId, int $seconds): bool
    {
        return $this->locked(function () use ($callId, $seconds): bool {
            $slots = $this->live();

            if (! isset($slots[$callId]) && count($slots) >= $this->capacity()) {
                return false;
            }

            $slots[$callId] = now()->getTimestamp() + $seconds;
            Cache::forever(self::KEY, $slots);

            return true;
        }) ?? false;
    }

    /** Keep the call's slot for this many more seconds. */
    public function hold(string $callId, int $seconds): void
    {
        $this->take($callId, $seconds);
    }

    public function release(string $callId): void
    {
        $this->locked(function () use ($callId): bool {
            $slots = $this->live();
            unset($slots[$callId]);
            Cache::forever(self::KEY, $slots);

            return true;
        });
    }

    /** The most calls at once: the `voice` supervisor's workers in this environment. */
    public function capacity(): int
    {
        $workers = config('horizon.environments.'.app()->environment().'.voice.maxProcesses')
            ?? config('horizon.defaults.voice.maxProcesses', 1);

        return max(1, (int) $workers);
    }

    /**
     * The slots that have not run out, call id → Unix time they end.
     *
     * @return array<string, int>
     */
    private function live(): array
    {
        $now = now()->getTimestamp();
        $slots = Cache::get(self::KEY, []);

        return array_filter(is_array($slots) ? $slots : [], static fn (mixed $until): bool => is_int($until) && $until > $now);
    }

    /**
     * Run the step under the slots' lock; null when the lock stays busy.
     *
     * @param  callable(): bool  $step
     */
    private function locked(callable $step): ?bool
    {
        try {
            return Cache::lock(self::KEY.':lock', 5)->block(3, $step);
        } catch (LockTimeoutException) {
            return null;
        }
    }
}
