<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Realtime;

use Domain\Llm\Contracts\RealtimeConnection;
use Ratchet\Client\WebSocket;

/**
 * A worker's open sideband socket to an OpenAI voice call: client events go
 * out as JSON text frames.
 */
final readonly class OpenAiRealtimeConnection implements RealtimeConnection
{
    public function __construct(
        private WebSocket $socket,
    ) {}

    public function send(array $event): void
    {
        $this->socket->send(json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function close(): void
    {
        $this->socket->close();
    }
}
