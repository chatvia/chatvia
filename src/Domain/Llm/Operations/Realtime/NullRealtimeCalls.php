<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Realtime;

use Domain\Llm\Contracts\RealtimeCallHandler;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Data\Realtime\RealtimeCallData;
use Domain\Llm\Exceptions\RealtimeCallFailedException;

/**
 * Used when voice conversations are not set up. The voice button is hidden
 * then, and a call that is tried anyway fails with a clear "not set up"
 * error instead of a network or credentials error.
 */
class NullRealtimeCalls implements RealtimeCalls
{
    public function isEnabled(): bool
    {
        return false;
    }

    public function model(): string
    {
        return 'none';
    }

    public function create(string $offerSdp, array $session, string $safetyId): RealtimeCallData
    {
        throw $this->notSetUp();
    }

    public function join(string $callId, RealtimeCallHandler $handler): void
    {
        throw $this->notSetUp();
    }

    public function hangUp(string $callId): void {}

    private function notSetUp(): RealtimeCallFailedException
    {
        return new RealtimeCallFailedException('Voice conversations are not set up on this server.');
    }
}
