<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Models\Conversation;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Llm\Contracts\RealtimeCallHandler;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Contracts\RealtimeConnection;
use Illuminate\Support\Facades\Log;

/**
 * The server's side of one live voice call, joined over the provider's
 * sideband connection while the visitor's browser carries the audio.
 *
 * - On joining it turns on listening ({@see VoiceSessionConfig::turnDetection()}):
 *   the browser waits for that before it opens the microphone.
 * - When the visitor stops talking it checks the per-turn gates (message
 *   limits, rate limit, a teammate taking over) and only then asks for a
 *   reply, so nothing is answered unchecked.
 * - It runs the tools the model calls ({@see VoiceToolRunner}) and asks
 *   for the next part of the reply.
 * - It saves the visitor's words and each finished reply, in that order
 *   ({@see VoiceTurnRecorder}).
 * - It hangs up when the visitor presses End, at the call's time limit, or
 *   when a gate fails — after a short spoken goodbye where it can.
 *
 * Each live call gets its own copy ({@see for()}).
 */
final class VoiceCallSession implements RealtimeCallHandler
{
    /** How long a finished reply waits for the visitor's words to be written down. */
    private const int TRANSCRIPT_WAIT_SECONDS = 5;

    /** The longest a goodbye may take before the call is closed anyway. */
    private const int GOODBYE_SECONDS = 12;

    /** How long the provider gets to close the call before the socket is dropped. */
    private const int CLOSE_SECONDS = 2;

    private VoiceCallData $call;

    private StreamRequestData $request;

    /** @var array<string, ToolEntry> */
    private array $tools = [];

    private ?RealtimeConnection $connection = null;

    /** @var array<string, VoiceTurn> by the provider's id of the visitor's speech */
    private array $turns = [];

    /** @var array<string, VoiceTurn|null> by response id; null for a spoken notice */
    private array $responses = [];

    private bool $hangingUp = false;

    private ?float $hangUpAt = null;

    private ?float $closeAt = null;

    private bool $finished = false;

    public function __construct(
        private readonly VoiceTurnFactory $factory,
        private readonly VoiceToolCatalog $catalog,
        private readonly VoiceToolRunner $toolRunner,
        private readonly VoiceTurnRecorder $recorder,
        private readonly VoiceTurnGate $gate,
        private readonly VoiceCallStore $store,
        private readonly VoiceCallSlots $slots,
        private readonly RealtimeCalls $calls,
    ) {}

    /** A session for one call, with the call's tools as they were offered. */
    public function for(VoiceCallData $call, Agent $agent): self
    {
        $session = clone $this;
        $session->call = $call;
        $session->request = $this->factory->request($call, $agent);
        $session->tools = $this->catalog->for($agent, $call->conversation_id);

        return $session;
    }

    public function opened(RealtimeConnection $connection): void
    {
        $this->connection = $connection;

        // The slot was taken for the join only; keep it for the whole call.
        $this->slots->hold($this->call->call_id, max(60, $this->call->endsAt() - now()->getTimestamp() + 120));

        $this->send(VoiceSessionConfig::turnDetection());
    }

    public function received(array $event): void
    {
        match ($event['type'] ?? null) {
            'input_audio_buffer.committed' => $this->committed((string) ($event['item_id'] ?? '')),
            'conversation.item.input_audio_transcription.completed' => $this->transcribed((string) ($event['item_id'] ?? ''), (string) ($event['transcript'] ?? '')),
            'conversation.item.input_audio_transcription.failed' => $this->transcribed((string) ($event['item_id'] ?? ''), ''),
            'response.created' => $this->responseCreated((array) ($event['response'] ?? [])),
            'response.function_call_arguments.done' => $this->functionCalled($event),
            'response.done' => $this->responseDone((array) ($event['response'] ?? [])),
            'session.closed' => $this->connection?->close(),
            'error' => Log::info('Voice call: the provider reported an error', [
                'call' => $this->call->call_id,
                'error' => $event['error']['message'] ?? null,
            ]),
            default => null,
        };
    }

    public function tick(): void
    {
        $now = microtime(true);

        if ($this->closeAt !== null) {
            if ($now >= $this->closeAt) {
                $this->connection?->close();
            }

            return;
        }

        // The visitor pressed End: their side is closed already.
        if ($this->store->isEnded($this->call->call_id)) {
            $this->closeNow();

            return;
        }

        if ($this->hangingUp) {
            if ($now >= (float) $this->hangUpAt) {
                $this->closeNow();
            }

            return;
        }

        if (now()->getTimestamp() >= $this->call->endsAt()) {
            $this->hangUp(__('Our time for this call is up. You can keep chatting in the text box.'));

            return;
        }

        $this->saveRepliesWaitingTooLong($now);
    }

    public function closed(): void
    {
        $this->finish();
    }

    /**
     * Save whatever is still waiting, free the call's worker slot and forget
     * the call. Runs once, when the connection closes or the worker stops.
     */
    public function finish(): void
    {
        if ($this->finished) {
            return;
        }

        $this->finished = true;

        foreach ($this->turns as $turn) {
            if ($turn->replyReadyAt !== null && ! $turn->userStored && ! $turn->refused) {
                $this->recorder->visitorSaid($turn, '');
            }

            $this->saveReplyWhenReady($turn);
        }

        $this->slots->release($this->call->call_id);
        $this->store->forget($this->call->call_id);
    }

    /** The visitor stopped talking: check the gates, then ask for the reply. */
    private function committed(string $itemId): void
    {
        if ($itemId === '' || $this->hangingUp || isset($this->turns[$itemId])) {
            return;
        }

        $turn = $this->factory->turn($this->request, $itemId);
        $this->turns[$itemId] = $turn;

        $refusal = $this->isHandedOff()
            ? VoiceCallRefusal::HandedOff
            : $this->gate->refusal($this->request->agent, $this->call->chatter_id);

        if (! $refusal instanceof VoiceCallRefusal) {
            $this->createResponse($turn);

            return;
        }

        // Not answered and not stored: drop the speech from the call too.
        $turn->refused = true;
        $turn->userStored = true;
        $this->send(['type' => 'conversation.item.delete', 'item_id' => $itemId]);

        $refusal === VoiceCallRefusal::RateLimited
            ? $this->say($refusal->message())
            : $this->hangUp($refusal->message());
    }

    /** The visitor's words were written down (blank when that failed). */
    private function transcribed(string $itemId, string $transcript): void
    {
        $turn = $this->turns[$itemId] ?? null;

        if ($turn === null || $turn->userStored) {
            return;
        }

        $guard = $this->recorder->visitorSaid($turn, $transcript);

        if (! $guard->blocked) {
            $this->saveReplyWhenReady($turn);

            return;
        }

        // A guardrail refuses the turn. The reply may already be spoken; it
        // is stopped, kept out of the history, and the refusal said instead.
        $turn->refused = true;

        $responseId = array_search($turn, $this->responses, true);

        if (is_string($responseId)) {
            $this->send(['type' => 'response.cancel', 'response_id' => $responseId]);
        }

        $this->send(['type' => 'conversation.item.delete', 'item_id' => $itemId]);

        $refusal = $guard->refusalMessage ?? __('Sorry, I can\'t help with that.');
        $this->recorder->refusal($turn, $refusal);
        $this->say($refusal);
    }

    /** @param  array<string, mixed>  $response */
    private function responseCreated(array $response): void
    {
        $id = (string) ($response['id'] ?? '');

        if ($id === '') {
            return;
        }

        $metadata = (array) ($response['metadata'] ?? []);

        $this->responses[$id] = isset($metadata['notice'])
            ? null
            : $this->turns[(string) ($metadata['turn'] ?? '')] ?? $this->latestTurn();
    }

    /** @param  array<string, mixed>  $event */
    private function functionCalled(array $event): void
    {
        $callId = (string) ($event['call_id'] ?? '');

        if ($callId === '') {
            return;
        }

        $turn = $this->responses[(string) ($event['response_id'] ?? '')] ?? null;

        $output = $turn instanceof VoiceTurn && ! $turn->refused
            ? $this->toolRunner->run($turn, $this->tools, $callId, (string) ($event['name'] ?? ''), (string) ($event['arguments'] ?? '{}'))
            : 'Tool error: this tool cannot run any more in this call.';

        $this->send([
            'type' => 'conversation.item.create',
            'item' => ['type' => 'function_call_output', 'call_id' => $callId, 'output' => $output],
        ]);
    }

    /** @param  array<string, mixed>  $response */
    private function responseDone(array $response): void
    {
        $id = (string) ($response['id'] ?? '');
        $turn = array_key_exists($id, $this->responses) ? $this->responses[$id] : $this->latestTurn();
        unset($this->responses[$id]);

        $spoken = $this->spokenText($response);

        // A notice (a goodbye, "too busy"): close once it has been said.
        if (! $turn instanceof VoiceTurn) {
            if ($this->hangingUp) {
                $this->hangUpAt = min((float) $this->hangUpAt, microtime(true) + $this->secondsToSay($spoken));
            }

            return;
        }

        $usage = (array) ($response['usage'] ?? []);
        $turn->addUsage((int) ($usage['input_tokens'] ?? 0), (int) ($usage['output_tokens'] ?? 0));

        if ($turn->refused || $turn->replyStored) {
            return;
        }

        $turn->addSpoken($spoken);

        // The model called tools: their results are in, so it goes on.
        if (($response['status'] ?? null) === 'completed' && $this->calledTools($response) && ! $this->hangingUp) {
            $turn->toolRounds++;
            $this->createResponse($turn, $turn->toolRounds >= max(1, (int) config('chatvia.agent.tools.max_iterations', 5)) ? 'none' : null);

            return;
        }

        // Interrupted before a word was said: nothing to save.
        if ($turn->spoken === '') {
            return;
        }

        $turn->context->markEndTime();
        $turn->replyReadyAt ??= microtime(true);
        $this->saveReplyWhenReady($turn);
    }

    private function saveReplyWhenReady(VoiceTurn $turn): void
    {
        if ($turn->replyStored || $turn->refused || $turn->replyReadyAt === null || ! $turn->userStored) {
            return;
        }

        $this->recorder->reply($turn);
    }

    /** A reply whose visitor words never came is saved with a placeholder. */
    private function saveRepliesWaitingTooLong(float $now): void
    {
        foreach ($this->turns as $itemId => $turn) {
            if ($turn->replyReadyAt !== null && ! $turn->userStored && $now - $turn->replyReadyAt >= self::TRANSCRIPT_WAIT_SECONDS) {
                $this->recorder->visitorSaid($turn, '');
                $this->saveReplyWhenReady($turn);
            }

            // Done turns are no longer needed.
            if ($turn->userStored && ($turn->replyStored || $turn->refused) && ! in_array($turn, $this->responses, true)) {
                unset($this->turns[$itemId]);
            }
        }
    }

    /** Ask for the reply to a turn, or its next part after tool calls. */
    private function createResponse(VoiceTurn $turn, ?string $toolChoice = null): void
    {
        $response = ['metadata' => ['turn' => $turn->itemId]];

        if ($toolChoice !== null) {
            $response['tool_choice'] = $toolChoice;
        }

        $this->send(['type' => 'response.create', 'response' => $response]);
    }

    /** Say something that is not an answer (not saved, not counted). */
    private function say(string $text): void
    {
        $this->send([
            'type' => 'response.create',
            'response' => [
                'instructions' => prompt('voice/say', ['text' => $text]),
                'tool_choice' => 'none',
                'metadata' => ['notice' => '1'],
            ],
        ]);
    }

    /** Say goodbye, then close the call once it has been said. */
    private function hangUp(string $message): void
    {
        if ($this->hangingUp) {
            return;
        }

        $this->hangingUp = true;
        $this->hangUpAt = microtime(true) + self::GOODBYE_SECONDS;
        $this->say($message);
    }

    private function closeNow(): void
    {
        if ($this->closeAt !== null) {
            return;
        }

        $this->closeAt = microtime(true) + self::CLOSE_SECONDS;
        $this->send(['type' => 'session.close']);
        $this->calls->hangUp($this->call->provider_call_id);
    }

    private function isHandedOff(): bool
    {
        return Conversation::query()
            ->where('agent_id', $this->request->agent->id)
            ->where('external_id', $this->call->conversation_id)
            ->first()
            ?->isHandedOff() ?? false;
    }

    private function latestTurn(): ?VoiceTurn
    {
        $last = array_key_last($this->turns);

        return $last === null ? null : $this->turns[$last];
    }

    /**
     * What the response said out loud (the transcript of its audio).
     *
     * @param  array<string, mixed>  $response
     */
    private function spokenText(array $response): string
    {
        return collect((array) ($response['output'] ?? []))
            ->filter(static fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'message')
            ->flatMap(static fn (array $item): array => (array) ($item['content'] ?? []))
            ->map(static fn (mixed $part): string => is_array($part) ? (string) ($part['transcript'] ?? $part['text'] ?? '') : '')
            ->filter(filled(...))
            ->implode(' ');
    }

    /** @param  array<string, mixed>  $response */
    private function calledTools(array $response): bool
    {
        return collect((array) ($response['output'] ?? []))
            ->contains(static fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'function_call');
    }

    /** About how long it takes to say a text, so the call ends after the goodbye. */
    private function secondsToSay(string $text): float
    {
        $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return min(self::GOODBYE_SECONDS, max(2.0, $words / 2.5 + 1.5));
    }

    /** @param  array<string, mixed>  $event */
    private function send(array $event): void
    {
        $this->connection?->send($event);
    }
}
