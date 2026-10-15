<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Models\Conversation;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Knowledge\Ingestion\Models\Chunk;
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
 * - For an agent with knowledge, every reply starts with a required
 *   knowledge search (the model writes the query; an empty one for small
 *   talk), as a typed turn always searches: left to choose, the model
 *   answered questions blind.
 * - It runs the tools the model calls ({@see VoiceToolRunner}) and asks for
 *   the next part of the reply. While a real search or tool runs, the agent
 *   says a short "let me check" (not saved), and the reply follows once it
 *   has been said.
 * - A tool that needs a person's approval is held: the agent asks, and the
 *   tool runs only when the model calls it again in the reply to the
 *   visitor's next words, once those are written down ({@see VoiceToolApprovals}).
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

    /** How long a reply waits for its "let me check" before it goes on anyway. */
    private const int FILLER_WAIT_SECONDS = 8;

    /** The answer to a tool call of a turn that will not go on. */
    private const string CANCELLED_RESULT = 'Tool error: this turn was cancelled.';

    private VoiceCallData $call;

    private StreamRequestData $request;

    /** @var array<string, ToolEntry> */
    private array $tools = [];

    /** The actions held for the visitor's yes. */
    private VoiceToolApprovals $approvals;

    private ?RealtimeConnection $connection = null;

    /** @var array<string, VoiceTurn> by the provider's id of the visitor's speech */
    private array $turns = [];

    /** @var array<string, VoiceTurn|null> by response id; null for a spoken notice */
    private array $responses = [];

    /** Whether the agent has knowledge to search: then every reply starts with a search. */
    private bool $searchesKnowledge = false;

    /** The turn whose reply goes on once the "let me check" has been said. */
    private ?VoiceTurn $waitingTurn = null;

    private ?float $waitingSince = null;

    /** The tool choice of the reply that goes on: `none` once the tool rounds are used up. */
    private ?string $waitingToolChoice = null;

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
        $session->approvals = new VoiceToolApprovals;
        $session->searchesKnowledge = Chunk::query()->hasContentForAgent($agent);

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
        $now = $this->clock();

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

        // The "let me check" never finished: the reply goes on anyway.
        if ($this->waitingTurn instanceof VoiceTurn && $now - (float) $this->waitingSince >= self::FILLER_WAIT_SECONDS) {
            $this->continueWaitingTurn();
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

        // The visitor spoke again: an earlier reply still waiting is dropped,
        // and so is a yes still being written down. These words answer the
        // question about a held action, if one waits.
        $this->stopWaiting();
        $this->cancelParkedCalls();
        $this->approvals->visitorSpoke($itemId);

        $refusal = $this->isHandedOff()
            ? VoiceCallRefusal::HandedOff
            : $this->gate->refusal($this->request->agent, $this->call->chatter_id);

        if (! $refusal instanceof VoiceCallRefusal) {
            // An answer to "shall I…?" is not a question: a forced search
            // would have the model reply instead of acting on the yes.
            $search = $this->searchesKnowledge && ! $this->approvals->isAnswer($itemId);
            $this->createResponse($turn, $search ? $this->knowledgeSearch() : null);

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
            $this->runParkedCalls($turn);
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
        $this->runParkedCalls($turn);

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

    /** @param  array<string, mixed>  $response */
    private function responseDone(array $response): void
    {
        $id = (string) ($response['id'] ?? '');
        $turn = array_key_exists($id, $this->responses) ? $this->responses[$id] : $this->latestTurn();
        unset($this->responses[$id]);

        $spoken = $this->spokenText($response);

        // A notice (a goodbye, "too busy", "let me check"): close once a
        // goodbye has been said; go on with the reply after "let me check",
        // unless the visitor cut it short by talking.
        if (! $turn instanceof VoiceTurn) {
            if ($this->hangingUp) {
                $this->hangUpAt = min((float) $this->hangUpAt, $this->clock() + $this->secondsToSay($spoken));
            }

            if (isset(((array) ($response['metadata'] ?? []))['filler'])) {
                ($response['status'] ?? null) === 'cancelled' ? $this->stopWaiting() : $this->continueWaitingTurn();
            }

            return;
        }

        $usage = (array) ($response['usage'] ?? []);
        $turn->addUsage((int) ($usage['input_tokens'] ?? 0), (int) ($usage['output_tokens'] ?? 0));

        $calls = $this->toolCalls($response);

        if ($turn->refused || $turn->replyStored) {
            // Every call gets an answer, so the conversation stays valid.
            foreach ($calls as $call) {
                $this->sendToolOutput($call['call_id'], self::CANCELLED_RESULT);
            }

            return;
        }

        $turn->addSpoken($spoken);

        // The model called tools: run them, then it goes on.
        if (($response['status'] ?? null) === 'completed' && $calls !== [] && ! $this->hangingUp) {
            $this->runTools($turn, $calls);

            return;
        }

        // Interrupted before a word was said: nothing to save.
        if ($turn->spoken === '') {
            return;
        }

        $turn->context->markEndTime();
        $turn->replyReadyAt ??= $this->clock();
        $this->saveReplyWhenReady($turn);
    }

    private function saveReplyWhenReady(VoiceTurn $turn): void
    {
        if ($turn->replyStored || $turn->refused || $turn->replyReadyAt === null || ! $turn->userStored) {
            return;
        }

        $this->recorder->reply($turn);
    }

    /**
     * A reply whose visitor words never came is saved with a placeholder; a
     * yes that never came is no yes, so its calls are held again.
     */
    private function saveRepliesWaitingTooLong(float $now): void
    {
        foreach ($this->turns as $itemId => $turn) {
            if ($turn->parkedAt !== null && ! $turn->userStored && $now - $turn->parkedAt >= self::TRANSCRIPT_WAIT_SECONDS) {
                $this->recorder->visitorSaid($turn, '');
                $this->runParkedCalls($turn);
            }

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

    /**
     * Run the tools a response called, then ask for the next part of the
     * reply. A real search or tool says "let me check" first (once a turn),
     * and the reply goes on after it has been said; a small-talk search
     * (empty query) goes on at once.
     *
     * @param  list<array{call_id: string, name: string, arguments: string}>  $calls
     */
    private function runTools(VoiceTurn $turn, array $calls): void
    {
        // A yes counts only once it is written down and the guardrails have
        // read it: until then, the calls it confirms wait.
        if (! $turn->userStored && array_any($calls, fn (array $call): bool => $this->confirms($turn, $call))) {
            $turn->parkedCalls = $calls;
            $turn->parkedAt = $this->clock();

            return;
        }

        $filler = $turn->toolRounds === 0
            && array_any($calls, fn (array $call): bool => $this->takesAMoment($call) && ! $this->isHeld($turn, $call));

        if ($filler) {
            $this->sayFiller($this->searchQuery($calls) ?? $turn->words);
        }

        foreach ($calls as $call) {
            $this->sendToolOutput($call['call_id'], $this->runTool($turn, $call));
        }

        $turn->toolRounds++;
        $toolChoice = $turn->toolRounds >= max(1, (int) config('chatvia.agent.tools.max_iterations', 5)) ? 'none' : null;

        if (! $filler) {
            $this->createResponse($turn, $toolChoice);

            return;
        }

        $this->waitingTurn = $turn;
        $this->waitingSince = $this->clock();
        $this->waitingToolChoice = $toolChoice;
    }

    /**
     * Run one call. One that needs a person's approval is held, and the
     * model asks, unless it repeats a held call the visitor said yes to.
     *
     * @param  array{call_id: string, name: string, arguments: string}  $call
     */
    private function runTool(VoiceTurn $turn, array $call): string
    {
        if (! $this->isGated($call)) {
            return $this->toolRunner->run($turn, $this->tools, $call['call_id'], $call['name'], $call['arguments']);
        }

        if ($this->isHeld($turn, $call)) {
            $this->approvals->hold($call['name'], $call['arguments']);

            return VoiceToolRunner::CONFIRM_FIRST_RESULT;
        }

        $this->approvals->release($call['name'], $call['arguments']);

        return $this->toolRunner->runApproved($turn, $call['call_id'], $call['name'], $call['arguments']);
    }

    /** @param  array{call_id: string, name: string, arguments: string}  $call */
    private function isGated(array $call): bool
    {
        return $this->toolRunner->requiresApproval($this->request->agent, $this->tools, $call['name']);
    }

    /**
     * Whether the call repeats a held one in the reply to the visitor's
     * answer (whether that answer was a yes is the model's call).
     *
     * @param  array{call_id: string, name: string, arguments: string}  $call
     */
    private function confirms(VoiceTurn $turn, array $call): bool
    {
        return $this->isGated($call) && $this->approvals->isConfirmedBy($turn->itemId, $call['name'], $call['arguments']);
    }

    /**
     * Whether a call that needs approval waits for the visitor's yes: it
     * confirms nothing, or the answer could not be written down.
     *
     * @param  array{call_id: string, name: string, arguments: string}  $call
     */
    private function isHeld(VoiceTurn $turn, array $call): bool
    {
        return $this->isGated($call) && (! $this->confirms($turn, $call) || ! filled($turn->words));
    }

    /** The visitor's words are written down: the calls waiting for them go on. */
    private function runParkedCalls(VoiceTurn $turn): void
    {
        $calls = $turn->parkedCalls;
        $turn->parkedCalls = [];
        $turn->parkedAt = null;

        if ($calls === []) {
            return;
        }

        if ($turn->refused || $this->hangingUp) {
            foreach ($calls as $call) {
                $this->sendToolOutput($call['call_id'], self::CANCELLED_RESULT);
            }

            return;
        }

        $this->runTools($turn, $calls);
    }

    /** The visitor spoke again before their earlier answer was written down: it no longer counts. */
    private function cancelParkedCalls(): void
    {
        foreach ($this->turns as $turn) {
            foreach ($turn->parkedCalls as $call) {
                $this->sendToolOutput($call['call_id'], self::CANCELLED_RESULT);
            }

            $turn->parkedCalls = [];
            $turn->parkedAt = null;
        }
    }

    /**
     * Whether a call is worth a "let me check": any tool, or a knowledge
     * search with a query (an empty one is small talk).
     *
     * @param  array{call_id: string, name: string, arguments: string}  $call
     */
    private function takesAMoment(array $call): bool
    {
        return $call['name'] !== VoiceToolCatalog::KNOWLEDGE_TOOL || $this->searchQuery([$call]) !== null;
    }

    /**
     * The query of the first knowledge search among the calls, if any. It is
     * in the visitor's language, so "let me check" is said in it too.
     *
     * @param  list<array{call_id: string, name: string, arguments: string}>  $calls
     */
    private function searchQuery(array $calls): ?string
    {
        foreach ($calls as $call) {
            $arguments = json_decode($call['arguments'], true);

            if ($call['name'] === VoiceToolCatalog::KNOWLEDGE_TOOL && is_array($arguments) && is_string($arguments['query'] ?? null) && filled(trim($arguments['query']))) {
                return trim($arguments['query']);
            }
        }

        return null;
    }

    /** The reply waiting for its "let me check" goes on. */
    private function continueWaitingTurn(): void
    {
        $turn = $this->waitingTurn;
        $toolChoice = $this->waitingToolChoice;
        $this->stopWaiting();

        if ($turn instanceof VoiceTurn && ! $turn->refused && ! $this->hangingUp) {
            $this->createResponse($turn, $toolChoice);
        }
    }

    private function stopWaiting(): void
    {
        $this->waitingTurn = null;
        $this->waitingSince = null;
        $this->waitingToolChoice = null;
    }

    private function sendToolOutput(string $callId, string $output): void
    {
        $this->send([
            'type' => 'conversation.item.create',
            'item' => ['type' => 'function_call_output', 'call_id' => $callId, 'output' => $output],
        ]);
    }

    /**
     * The tool choice that makes a reply start with a knowledge search.
     *
     * @return array{type: string, name: string}
     */
    private function knowledgeSearch(): array
    {
        return ['type' => 'function', 'name' => VoiceToolCatalog::KNOWLEDGE_TOOL];
    }

    /**
     * Ask for the reply to a turn, or its next part after tool calls.
     *
     * @param  string|array<string, string>|null  $toolChoice
     */
    private function createResponse(VoiceTurn $turn, string|array|null $toolChoice = null): void
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

    /**
     * Say a short "let me check" while a search or tool runs. It is
     * out-of-band (`conversation: none`), so it never enters the call's
     * history, and it gets no conversation to read (`input: []`): with the
     * question in front of it the model answers instead. The visitor's words
     * come with the instructions, only for the language. Not saved or
     * counted.
     */
    private function sayFiller(?string $question): void
    {
        $this->send([
            'type' => 'response.create',
            'response' => [
                'conversation' => 'none',
                'input' => [],
                'instructions' => prompt('voice/filler', ['question' => $question]),
                'tool_choice' => 'none',
                'metadata' => ['notice' => '1', 'filler' => '1'],
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
        $this->hangUpAt = $this->clock() + self::GOODBYE_SECONDS;
        $this->say($message);
    }

    private function closeNow(): void
    {
        if ($this->closeAt !== null) {
            return;
        }

        $this->closeAt = $this->clock() + self::CLOSE_SECONDS;
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

    /**
     * The tool calls a finished response made.
     *
     * @param  array<string, mixed>  $response
     * @return list<array{call_id: string, name: string, arguments: string}>
     */
    private function toolCalls(array $response): array
    {
        return collect((array) ($response['output'] ?? []))
            ->filter(static fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'function_call' && filled($item['call_id'] ?? null))
            ->map(static fn (array $item): array => [
                'call_id' => (string) $item['call_id'],
                'name' => (string) ($item['name'] ?? ''),
                'arguments' => (string) ($item['arguments'] ?? '{}'),
            ])
            ->values()
            ->all();
    }

    /** About how long it takes to say a text, so the call ends after the goodbye. */
    private function secondsToSay(string $text): float
    {
        $words = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return min(self::GOODBYE_SECONDS, max(2.0, $words / 2.5 + 1.5));
    }

    /** Seconds now, with microseconds; through Carbon, so tests can move the clock. */
    private function clock(): float
    {
        return now()->getPreciseTimestamp(6) / 1_000_000;
    }

    /** @param  array<string, mixed>  $event */
    private function send(array $event): void
    {
        $this->connection?->send($event);
    }
}
