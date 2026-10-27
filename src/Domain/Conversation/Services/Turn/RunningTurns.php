<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Turn;

use Generator;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Which conversations have a turn being answered right now. A reload in the
 * middle of a reply cuts the browser off, but the turn goes on until it
 * notices and saves what it has; the reloaded chat asks here, so it waits
 * for that reply instead of asking for a second one (and running its tools,
 * a generated image, again).
 *
 * Kept in the chat cache, with an expiry so a turn whose process died never
 * holds a conversation for long.
 */
final class RunningTurns
{
    /** Longer than any turn runs (long tool chains included). */
    private const int TTL_SECONDS = 600;

    /**
     * Mark the conversation's turn as running from now until its events are
     * done, the turn fails, or the stream is dropped. The events are built at
     * once (the turn does its first work before the stream is read), inside
     * the mark.
     *
     * @param  callable(): Generator  $events  builds the turn's events
     */
    public function track(string $conversationId, callable $events): Generator
    {
        $this->start($conversationId);

        try {
            $stream = $events();
        } catch (Throwable $e) {
            $this->finish($conversationId);

            throw $e;
        }

        return $this->until($conversationId, $stream);
    }

    public function isRunning(string $conversationId): bool
    {
        return Cache::driver('chat')->has($this->key($conversationId));
    }

    private function until(string $conversationId, Generator $events): Generator
    {
        try {
            return yield from $events;
        } finally {
            $this->finish($conversationId);
        }
    }

    private function start(string $conversationId): void
    {
        Cache::driver('chat')->put($this->key($conversationId), true, self::TTL_SECONDS);
    }

    private function finish(string $conversationId): void
    {
        try {
            Cache::driver('chat')->forget($this->key($conversationId));
        } catch (Throwable $e) {
            // The flag expires on its own; never fail the turn's end for it.
            report($e);
        }
    }

    private function key(string $conversationId): string
    {
        return "turn-running:{$conversationId}";
    }
}
