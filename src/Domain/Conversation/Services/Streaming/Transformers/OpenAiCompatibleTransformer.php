<?php

namespace Domain\Conversation\Services\Streaming\Transformers;

use Domain\Conversation\Contracts\StreamTransformer;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Turn\TurnStops;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Reads an OpenAI-compatible server-sent-event stream from an LLM provider
 * and turns it into envelope events: text deltas, the generation id, and —
 * collected on the {@see ChatContext} — usage and tool calls.
 *
 * A reasoning model's thinking comes in `delta.reasoning_content` or
 * `delta.reasoning` (OpenRouter also in `delta.reasoning_details`), or
 * inline in the reply as a leading `<think>…</think>` block. It never
 * becomes part of the answer; it is sent as `reasoning` events only when the
 * turn shows it ({@see ChatContext::$showsReasoning}), and dropped otherwise.
 *
 * Some open-weight models (Qwen3, Gemma 3, gpt-oss on Chat Completions) write
 * a tool call as JSON in the reply text instead of in `tool_calls`. When
 * tools were offered, text that starts like JSON is held back and, at the
 * end of the stream, turned into a tool call if it names an offered tool;
 * anything else is streamed as the answer.
 */
class OpenAiCompatibleTransformer implements StreamTransformer
{
    private const int READ_BYTES = 1024;

    private const string DATA_PREFIX = 'data: ';

    private const string END_OF_STREAM = '[DONE]';

    private const string THINK_OPEN = '<think>';

    private const string THINK_CLOSE = '</think>';

    public function __construct(
        private readonly TurnStops $stops = new TurnStops,
    ) {}

    public function transform(ResponseInterface $upstream, ChatContext $context): Generator
    {
        $state = new StreamReadState(toolsOffered: $context->offeredToolNames !== []);

        try {
            foreach ($this->payloads($upstream->getBody(), $context) as $payload) {
                if ($payload === self::END_OF_STREAM) {
                    $state->finalized = true;
                    yield from $this->finalize($context, $state);

                    return;
                }

                $chunk = $this->decode($payload);

                if ($chunk !== null) {
                    yield from $this->handleChunk($context, $state, $chunk);
                }
            }
        } finally {
            if (! $state->finalized) {
                yield from $this->finalize($context, $state);
            }
        }
    }

    /**
     * The payload of every `data:` line, until the stream ends or the turn is
     * stopped. A read failure means the provider closed the stream.
     *
     * @return Generator<int, string>
     */
    private function payloads(StreamInterface $body, ChatContext $context): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            // The visitor's Stop ends the turn here, and leaving the read
            // closes the provider's stream. A browser that went away does
            // not: the answer is read to the end and saved.
            $this->stops->throwIfStoppedLately($context);

            try {
                $buffer .= $body->read(self::READ_BYTES);
            } catch (RuntimeException $e) {
                throw new UpstreamStreamClosed(previous: $e);
            }

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);

                if (filled($line) && Str::startsWith($line, self::DATA_PREFIX)) {
                    yield substr($line, strlen(self::DATA_PREFIX));
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $payload): ?array
    {
        try {
            $chunk = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($chunk) ? $chunk : null;
    }

    /**
     * @param  array<string, mixed>  $chunk
     * @return Generator<int, StreamEvent>
     */
    private function handleChunk(ChatContext $context, StreamReadState $state, array $chunk): Generator
    {
        if (! $state->generationIdSeen && filled($chunk['id'] ?? null)) {
            $state->generationIdSeen = true;
            $context->generationId = $chunk['id'];

            yield StreamEvent::meta($context->assistantMessageId, 'generation_id', $chunk['id']);
        }

        // The final chunk carries `usage` when the request asked for it: the
        // authoritative prompt + completion count.
        if (isset($chunk['usage']) && is_array($chunk['usage'])) {
            $context->usage = $chunk['usage'];
        }

        $delta = $chunk['choices'][0]['delta'] ?? [];

        if (is_array($delta)) {
            yield from $this->reasoning($context, $this->reasoningText($delta));
        }

        $text = $delta['content'] ?? null;

        // Not filled(): a whitespace-only delta (" ", "\n") is real output.
        if (($text ?? '') !== '') {
            yield from $this->handleContent($context, $state, $text);
        }

        // Tool calls arrive as indexed pieces: the first carries the id and
        // name, later ones only more of the JSON arguments. They are collected
        // on the context; the tool loop acts on them when the stream ends.
        if (is_array($delta['tool_calls'] ?? null)) {
            foreach ($delta['tool_calls'] as $toolCallDelta) {
                if (is_array($toolCallDelta)) {
                    $context->applyToolCallDelta($toolCallDelta);
                }
            }
        }
    }

    /**
     * The thinking in a delta's own field: `reasoning_content` (vLLM, LM
     * Studio, DeepSeek), `reasoning` (OpenRouter, Groq), or else the text and
     * summary parts of OpenRouter's `reasoning_details`.
     *
     * @param  array<array-key, mixed>  $delta
     */
    private function reasoningText(array $delta): string
    {
        foreach (['reasoning_content', 'reasoning'] as $key) {
            if (($delta[$key] ?? '') !== '') {
                return $delta[$key];
            }
        }

        $parts = is_array($delta['reasoning_details'] ?? null) ? $delta['reasoning_details'] : [];

        return implode('', array_map(
            fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? $part['summary'] ?? '') : '',
            $parts,
        ));
    }

    /**
     * A `reasoning` event for the client when the turn shows the thinking.
     * Thinking never joins the streamed answer.
     *
     * @return Generator<int, StreamEvent>
     */
    private function reasoning(ChatContext $context, string $text): Generator
    {
        if ($text !== '' && $context->showsReasoning) {
            yield StreamEvent::reasoning($context->assistantMessageId, $text);
        }
    }

    /**
     * Reply text, with a leading inline `<think>…</think>` block taken out
     * as reasoning. A tag split over two chunks is held back until it is
     * whole, so no part of it reaches the answer.
     *
     * @return Generator<int, StreamEvent>
     */
    private function handleContent(ChatContext $context, StreamReadState $state, string $text): Generator
    {
        if ($state->think === ThinkPhase::Done) {
            yield from $this->handleText($context, $state, $text);

            return;
        }

        $state->thinkBuffer .= $text;

        if ($state->think === ThinkPhase::Undecided) {
            $leading = Str::ltrim($state->thinkBuffer);

            if (Str::startsWith($leading, self::THINK_OPEN)) {
                $state->think = ThinkPhase::Thinking;
                $state->thinkBuffer = substr($leading, strlen(self::THINK_OPEN));
            } elseif ($leading === '' || Str::startsWith(self::THINK_OPEN, $leading)) {
                return;
            } else {
                $state->think = ThinkPhase::Done;
                $held = $state->thinkBuffer;
                $state->thinkBuffer = '';

                yield from $this->handleText($context, $state, $held);

                return;
            }
        }

        $close = strpos($state->thinkBuffer, self::THINK_CLOSE);

        if ($close === false) {
            // Keep back what may be the start of the closing tag.
            $keep = $this->partialTagLength($state->thinkBuffer, self::THINK_CLOSE);
            $thought = substr($state->thinkBuffer, 0, strlen($state->thinkBuffer) - $keep);
            $state->thinkBuffer = substr($state->thinkBuffer, strlen($thought));

            yield from $this->reasoning($context, $thought);

            return;
        }

        $state->think = ThinkPhase::Done;
        $answer = Str::ltrim(substr($state->thinkBuffer, $close + strlen(self::THINK_CLOSE)));
        $thought = substr($state->thinkBuffer, 0, $close);
        $state->thinkBuffer = '';

        yield from $this->reasoning($context, $thought);

        if ($answer !== '') {
            yield from $this->handleText($context, $state, $answer);
        }
    }

    /** How many of the text's last characters could begin the tag. */
    private function partialTagLength(string $text, string $tag): int
    {
        for ($length = min(strlen($tag) - 1, strlen($text)); $length > 0; $length--) {
            if (Str::endsWith($text, substr($tag, 0, $length))) {
                return $length;
            }
        }

        return 0;
    }

    /**
     * Stream reply text, or hold it back while it may be a JSON tool call.
     * The first non-whitespace character decides: `{`, `[` or a backtick
     * (a code fence) with tools offered holds; anything else streams.
     *
     * @return Generator<int, StreamEvent>
     */
    private function handleText(ChatContext $context, StreamReadState $state, string $text): Generator
    {
        if ($state->mode === ContentMode::Text) {
            yield $this->streamText($context, $text);

            return;
        }

        $state->heldContent .= $text;

        if ($state->mode === ContentMode::HeldAsJson) {
            return;
        }

        $leading = Str::ltrim($state->heldContent);

        if ($leading === '') {
            return;
        }

        if ($state->toolsOffered && in_array($leading[0], ['{', '[', '`'], true)) {
            $state->mode = ContentMode::HeldAsJson;

            return;
        }

        $state->mode = ContentMode::Text;
        $held = $state->heldContent;
        $state->heldContent = '';

        yield $this->streamText($context, $held);
    }

    private function streamText(ChatContext $context, string $text): StreamEvent
    {
        $context->appendStreamedMessage($text);

        return StreamEvent::delta($context->assistantMessageId, $text);
    }

    /**
     * The stream ended: decide what the held-back text was. Real tool calls
     * that arrived in `tool_calls` win, and the held text is dropped. Otherwise
     * it becomes a tool call — but only when it names a tool offered this turn.
     * Anything else (a JSON answer, unparseable text) streams as the answer.
     *
     * @return Generator<int, StreamEvent>
     */
    private function finalize(ChatContext $context, StreamReadState $state): Generator
    {
        // A reply that was only the start of "<think>", or a block never
        // closed: still text and thinking, in that order.
        if ($state->thinkBuffer !== '') {
            $rest = $state->thinkBuffer;
            $state->thinkBuffer = '';

            if ($state->think === ThinkPhase::Thinking) {
                yield from $this->reasoning($context, $rest);
            } else {
                $state->think = ThinkPhase::Done;
                yield from $this->handleText($context, $state, $rest);
            }
        }

        if ($state->mode !== ContentMode::HeldAsJson || $state->heldContent === '' || $context->pendingToolCalls !== []) {
            return;
        }

        $calls = JsonToolCallParser::parse($state->heldContent);

        if ($calls !== [] && $this->namesAnOfferedTool($calls, $context->offeredToolNames)) {
            $this->recordSynthesisedCalls($context, $calls);

            return;
        }

        yield $this->streamText($context, $state->heldContent);
    }

    /**
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $calls
     */
    private function recordSynthesisedCalls(ChatContext $context, array $calls): void
    {
        foreach ($calls as $index => $call) {
            $context->applyToolCallDelta([
                'index' => $index,
                'id' => 'call_'.bin2hex(random_bytes(12)),
                'function' => [
                    'name' => $call['name'],
                    'arguments' => json_encode($call['arguments']),
                ],
            ]);
        }

        Log::info('Synthesised tool_calls from JSON content', [
            'count' => count($calls),
            'names' => array_column($calls, 'name'),
            'assistant_message_id' => $context->assistantMessageId,
        ]);
    }

    /**
     * True when at least one call targets an offered tool. Other names in the
     * same batch still pass through: the tool loop refuses them with a tool
     * error, which tells the model the tool does not exist.
     *
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $calls
     * @param  array<int, string>  $offeredToolNames
     */
    private function namesAnOfferedTool(array $calls, array $offeredToolNames): bool
    {
        return array_any($calls, static fn (array $call): bool => in_array($call['name'], $offeredToolNames, true));
    }
}
