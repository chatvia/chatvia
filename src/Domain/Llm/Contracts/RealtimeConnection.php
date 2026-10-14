<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

/**
 * A server worker's open connection to a live voice call: it sends client
 * events (session updates, tool results, replies to create) and can end the
 * connection.
 */
interface RealtimeConnection
{
    /**
     * @param  array<string, mixed>  $event  one client event (`type` plus its fields)
     */
    public function send(array $event): void;

    public function close(): void;
}
