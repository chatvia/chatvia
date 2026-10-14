<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Contracts\RealtimeCalls;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Hangs up a voice call when the visitor presses End: the call is ended with
 * the provider at once, and the worker on it (if one joined) sees the flag
 * within a second and wraps up. The browser closes its own side too; this
 * makes sure the call (and its cost) ends with it, even when no worker ever
 * joined.
 *
 * @throws ModelNotFoundException when the call is not the visitor's
 */
class EndVoiceCallAction
{
    public function __construct(
        private readonly VoiceCallStore $store,
        private readonly RealtimeCalls $calls,
    ) {}

    public function execute(Agent $agent, string $sessionId, string $callId): void
    {
        $call = $this->store->find($callId);

        if (! $call instanceof VoiceCallData || ! $call->belongsTo((int) $agent->id, $sessionId)) {
            throw new ModelNotFoundException;
        }

        $this->store->end($call);
        $this->calls->hangUp($call->provider_call_id);
    }
}
