<?php

declare(strict_types=1);

namespace Tests\Support\Conversation\Voice;

use Closure;
use Domain\Llm\Contracts\RealtimeCallHandler;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Data\Realtime\RealtimeCallData;

/** {@see RealtimeCalls} that record hang-ups and play a scripted call on join. */
final class ScriptedRealtimeCalls implements RealtimeCalls
{
    /** @var list<string> */
    public array $hungUp = [];

    /** @var list<string> */
    public array $joined = [];

    /** @param  Closure(RealtimeCallHandler): void|null  $script */
    public function __construct(public ?Closure $script = null) {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function model(): string
    {
        return 'gpt-realtime-2.1';
    }

    public function create(string $offerSdp, array $session, string $safetyId): RealtimeCallData
    {
        return new RealtimeCallData('v=0 answer', 'rtc_test');
    }

    public function join(string $callId, RealtimeCallHandler $handler): void
    {
        $this->joined[] = $callId;
        ($this->script)?->__invoke($handler);
    }

    public function hangUp(string $callId): void
    {
        $this->hungUp[] = $callId;
    }
}
