<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use Domain\Llm\Data\Realtime\RealtimeCallData;
use Domain\Llm\Exceptions\RealtimeCallFailedException;

/**
 * Live voice calls with a speech-to-speech model. The browser's audio goes
 * straight to the provider over WebRTC: this opens the call for the browser's
 * offer (the provider key never leaves the server), then lets a server
 * worker join the same call to steer it — run tools, save the transcript,
 * hang up. The implementation is chosen from config (`chatvia.ai.voice`).
 */
interface RealtimeCalls
{
    /** Whether voice conversations are set up (Settings → AI models). */
    public function isEnabled(): bool;

    /** The speech-to-speech model calls use. */
    public function model(): string;

    /**
     * Open a call for the browser's WebRTC offer, with the session settings
     * the call starts with.
     *
     * @param  array<string, mixed>  $session
     * @param  string  $safetyId  a stable, anonymous id of the caller the provider uses against abuse
     *
     * @throws RealtimeCallFailedException
     */
    public function create(string $offerSdp, array $session, string $safetyId): RealtimeCallData;

    /**
     * Join a call's server connection and feed its events to the handler
     * until the call ends. Blocks for the length of the call.
     *
     * @throws RealtimeCallFailedException when the connection cannot be opened
     */
    public function join(string $callId, RealtimeCallHandler $handler): void;

    /** End a call from the server. Never throws: a call that is already over is fine. */
    public function hangUp(string $callId): void;
}
