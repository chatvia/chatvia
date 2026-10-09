<?php

namespace Domain\Conversation\Services\Streaming\Transformers;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Generator;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Reads an OpenAI-compatible server-sent-event stream from an LLM provider
 * and turns it into envelope events: text deltas, the generation id, and —
 * collected on the {@see ChatContext} — usage and tool calls.
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

    public function transform(ResponseInterface $upstream, ChatContext $context): Generator
    {
        $state = new StreamReadState(toolsOffered: $context->offeredToolNames !== []);

        try {
            foreach ($this->payloads($upstream->getBody()) as $payload) {
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
     * The payload of every `data:` line, until the stream ends or the client
     * goes away. A read failure means the provider closed the stream.
     *
     * @return Generator<int, string>
     */
    private function payloads(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            if (connection_status() !== CONNECTION_NORMAL || connection_aborted()) {
                return;
            }

            try {
                $buffer .= $body->read(self::READ_BYTES);
            } catch (RuntimeException $e) {
                throw new UpstreamStreamClosed(previous: $e);
            }

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);

                if (trim($line) !== '' && str_starts_with($line, self::DATA_PREFIX)) {
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
        if (! $state->generationIdSeen && isset($chunk['id']) && is_string($chunk['id'])) {
            $state->generationIdSeen = true;
            $context->generationId = $chunk['id'];

            yield StreamEvent::meta($context->assistantMessageId, 'generation_id', $chunk['id']);
        }

        // The final chunk carries `usage` when the request asked for it: the
        // authoritative prompt + completion count, also handed to the cost
        // tracker.
        if (isset($chunk['usage']) && is_array($chunk['usage'])) {
            $context->usage = $chunk['usage'];
            $context->spectraTracker?->setUsage($chunk['usage']);
        }

        $delta = $chunk['choices'][0]['delta'] ?? [];
        $text = $delta['content'] ?? null;

        if (is_string($text) && $text !== '') {
            yield from $this->handleText($context, $state, $text);
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

        $leading = ltrim($state->heldContent);

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
        $context->spectraTracker?->appendContent($text);

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
