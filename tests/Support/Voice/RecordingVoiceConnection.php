<?php

declare(strict_types=1);

namespace Tests\Support\Voice;

use Domain\Llm\Contracts\RealtimeConnection;

/** A voice worker's {@see RealtimeConnection} that records what it sends. */
final class RecordingVoiceConnection implements RealtimeConnection
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public bool $closed = false;

    public function send(array $event): void
    {
        $this->sent[] = $event;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    /** @return list<array<string, mixed>> */
    public function ofType(string $type): array
    {
        return array_values(array_filter($this->sent, fn (array $event): bool => $event['type'] === $type));
    }
}
