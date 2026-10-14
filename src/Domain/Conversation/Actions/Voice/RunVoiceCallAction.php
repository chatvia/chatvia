<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Services\Voice\VoiceCallSession;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Exceptions\RealtimeCallFailedException;
use Illuminate\Support\Facades\Log;

/**
 * Joins a live voice call and stays on it until it ends ({@see
 * VoiceCallSession}): this is what a `voice` queue worker does for the
 * whole length of a call. A call that ended before the worker got to it
 * (the visitor gave up waiting, or hung up) is only cleaned up.
 */
class RunVoiceCallAction
{
    public function __construct(
        private readonly RealtimeCalls $calls,
        private readonly VoiceCallSession $session,
        private readonly VoiceCallStore $store,
        private readonly VoiceCallSlots $slots,
    ) {}

    public function execute(VoiceCallData $call): void
    {
        $agent = Agent::query()->find($call->agent_id);

        if ($agent === null || $this->store->isEnded($call->call_id) || now()->getTimestamp() >= $call->endsAt()) {
            $this->slots->release($call->call_id);
            $this->store->forget($call->call_id);

            return;
        }

        $session = $this->session->for($call, $agent);

        try {
            $this->calls->join($call->provider_call_id, $session);
        } catch (RealtimeCallFailedException $e) {
            // Usually a call that ended before the worker joined it.
            Log::warning('Voice call: could not join', ['call' => $call->call_id, 'error' => $e->getMessage()]);
        } finally {
            $session->finish();
        }
    }
}
