<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Turn;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\TurnStopped;
use Illuminate\Support\Facades\Cache;

/**
 * The hosted chat's Stop button. A turn keeps running after its browser goes
 * away, so stopping it is a request of its own: the stop endpoint marks the
 * turn (by its scoped request id, so a stop never reaches another visitor's
 * turn or the next one), and the turn checks the mark as it reads the model's
 * answer and before each tool call, then ends where it is.
 *
 * Kept in the chat cache; a mark expires on its own and is removed once the
 * turn has seen it.
 */
final class TurnStops
{
    /** Longer than any turn runs. */
    private const int TTL_SECONDS = 600;

    /** How often the answer's read loop looks, so a read costs no cache call. */
    private const float LOOK_EVERY_SECONDS = 0.5;

    /** @var array<string, float> when each turn last looked */
    private array $lookedAt = [];

    public function request(string $requestId): void
    {
        Cache::driver('chat')->put($this->key($requestId), true, self::TTL_SECONDS);
    }

    /**
     * End the turn when its Stop was asked for. Before a tool call or a new
     * model call, where looking is rare and stopping matters most.
     *
     * @throws TurnStopped
     */
    public function throwIfStopped(ChatContext $context): void
    {
        $requestId = $this->requestIdOf($context);

        if ($requestId === '') {
            return;
        }

        $this->lookedAt[$requestId] = now()->getPreciseTimestamp(3) / 1000;

        if (Cache::driver('chat')->pull($this->key($requestId)) === true) {
            throw new TurnStopped;
        }
    }

    /**
     * The same, for the loop that reads the model's answer: it looks at most
     * every half second.
     *
     * @throws TurnStopped
     */
    public function throwIfStoppedLately(ChatContext $context): void
    {
        $requestId = $this->requestIdOf($context);
        $now = now()->getPreciseTimestamp(3) / 1000;

        if ($requestId === '' || $now - ($this->lookedAt[$requestId] ?? 0.0) < self::LOOK_EVERY_SECONDS) {
            return;
        }

        $this->throwIfStopped($context);
    }

    /** The turn's scoped request id; none for a turn that cannot be stopped (no id sent). */
    private function requestIdOf(ChatContext $context): string
    {
        return $context->requestData->requestId ?? '';
    }

    private function key(string $requestId): string
    {
        return "turn-stop:{$requestId}";
    }
}
