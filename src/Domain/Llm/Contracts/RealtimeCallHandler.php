<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

/**
 * What a server worker does while it is joined to a live voice call
 * ({@see RealtimeCalls::join()}): it gets the connection once it opens,
 * every event the provider sends, a tick every second (for time limits and
 * hang-up requests), and a last call when the connection closes.
 */
interface RealtimeCallHandler
{
    public function opened(RealtimeConnection $connection): void;

    /**
     * @param  array<string, mixed>  $event  one decoded server event (`type` plus its fields)
     */
    public function received(array $event): void;

    public function tick(): void;

    public function closed(): void;
}
