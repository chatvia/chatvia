<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Voice;

use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Data\Voice\VoiceCallerData;
use Domain\Conversation\Data\Voice\VoiceCallStartedData;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Exceptions\VoiceCallRefusedException;
use Domain\Conversation\Jobs\JoinVoiceCallJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Services\Voice\VoiceAvailability;
use Domain\Conversation\Services\Voice\VoiceCallSlots;
use Domain\Conversation\Services\Voice\VoiceCallStore;
use Domain\Conversation\Services\Voice\VoiceSessionConfig;
use Domain\Conversation\Services\Voice\VoiceToolCatalog;
use Domain\Conversation\Services\Voice\VoiceTurnGate;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Enums\RealtimeVoice;
use Domain\Llm\Exceptions\RealtimeCallFailedException;
use Domain\Security\Services\VisitorBlocklist;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Opens a voice call in one of the visitor's conversations: checks that
 * voice is on, the visitor is not blocked and may still send, takes one of the voice
 * workers, opens the call with the provider for the browser's WebRTC offer
 * (the agent's speaking instructions, voice and tools go with it), and sends
 * a worker to join it ({@see JoinVoiceCallJob}). Returns the answer the
 * browser applies.
 *
 * @throws VoiceCallRefusedException when the call may not or could not start
 * @throws ModelNotFoundException when the conversation is not the visitor's
 */
class StartVoiceCallAction
{
    /** The most minutes a call may last, whatever the setting says (the queue retries after ~31). */
    private const int MAX_CALL_MINUTES = 25;

    public function __construct(
        private readonly RealtimeCalls $calls,
        private readonly VoiceAvailability $availability,
        private readonly VoiceTurnGate $gate,
        private readonly VoiceSessionConfig $sessionConfig,
        private readonly VoiceCallSlots $slots,
        private readonly VoiceCallStore $store,
        private readonly VoiceToolCatalog $tools,
        private readonly VisitorBlocklist $blocklist,
    ) {}

    public function execute(VoiceCallerData $caller, string $offerSdp): VoiceCallStartedData
    {
        $agent = $caller->agent;

        if (! $this->availability->isOnFor($agent) || ! $agent->surfaceEnabled($caller->surface)) {
            throw new VoiceCallRefusedException(VoiceCallRefusal::NotAvailable);
        }

        // Only a conversation of the visitor's own session with this agent.
        $conversation = Conversation::query()
            ->inSession($caller->sessionId, $agent->id)
            ->where('external_id', $caller->conversationId)
            ->firstOrFail();

        if ($conversation->isHandedOff()) {
            throw new VoiceCallRefusedException(VoiceCallRefusal::HandedOff);
        }

        // As on a typed turn: only an anonymous visitor can be blocked. A block
        // made during a call applies from the next call.
        if ($caller->chatterId === null && $caller->actorUserId === null
            && $this->blocklist->blocks((int) $agent->workspace_id, $caller->ipAddress, $caller->userAgent)) {
            throw new VoiceCallRefusedException(VoiceCallRefusal::Blocked);
        }

        $refusal = $this->gate->refusal($agent, $caller->chatterId);

        if ($refusal instanceof VoiceCallRefusal) {
            throw new VoiceCallRefusedException($refusal);
        }

        $callId = Str::uuid7()->toString();
        $joinTimeout = max(5, (int) config('chatvia.voice.join_timeout', 20));

        // Held only until the worker joins; it then holds it for the call.
        if (! $this->slots->take($callId, $joinTimeout + 30)) {
            throw new VoiceCallRefusedException(VoiceCallRefusal::Busy);
        }

        $voice = RealtimeVoice::forSetting($agent->settings('appearance')->get('voice'));
        // Approval tools are offered to a signed-in caller only, as in the
        // worker's own session (VoiceCallSession).
        $viewer = match (true) {
            $caller->chatterId !== null => ChatViewerData::chatter($caller->chatterId),
            $caller->actorUserId !== null => ChatViewerData::staff($caller->actorUserId),
            default => null,
        };
        $tools = $this->tools->for($agent, $caller->conversationId, $viewer);

        try {
            $opened = $this->calls->create($offerSdp, $this->sessionConfig->build($caller, $voice, $tools), $this->safetyId($caller));
        } catch (RealtimeCallFailedException $e) {
            $this->slots->release($callId);
            report($e);

            throw new VoiceCallRefusedException(VoiceCallRefusal::Failed, $e);
        }

        $call = VoiceCallData::forCaller($caller, $callId, $opened->callId, $this->calls->model(), $voice->value, $this->maxSeconds());

        $this->store->put($call);
        JoinVoiceCallJob::dispatch($call);

        return new VoiceCallStartedData(
            sdp: $opened->answerSdp,
            call_id: $callId,
            conversation_id: $caller->conversationId,
            max_seconds: $call->max_seconds,
            join_timeout: $joinTimeout,
            tools: $this->tools->labels($agent, $tools),
            knowledge_tool: VoiceToolCatalog::KNOWLEDGE_TOOL,
        );
    }

    private function maxSeconds(): int
    {
        return min(self::MAX_CALL_MINUTES, max(1, (int) config('chatvia.voice.max_call_minutes', 10))) * 60;
    }

    /**
     * A stable, anonymous id of the caller for the provider's abuse checks:
     * the signed-in chatter, else the visitor session, within the workspace,
     * hashed with the app key so it cannot be traced back.
     */
    private function safetyId(VoiceCallerData $caller): string
    {
        $who = $caller->chatterId !== null ? "chatter:{$caller->chatterId}" : "session:{$caller->sessionId}";

        return hash_hmac('sha256', "{$caller->agent->workspace_id}:{$who}", (string) config('app.key'));
    }
}
