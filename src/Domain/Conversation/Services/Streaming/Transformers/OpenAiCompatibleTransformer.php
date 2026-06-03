<?php

namespace Domain\Conversation\Services\Streaming\Transformers;

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\UpstreamStreamClosed;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Generator;
use Illuminate\Support\Facades\Log;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class OpenAiCompatibleTransformer implements StreamTransformer
{
    public function transform(ResponseInterface $upstream, ChatContext $context): Generator
    {
        $body = $upstream->getBody();
        $buffer = '';
        $generationIdYielded = false;
        $contentBuffer = '';
        $contentMode = 'unknown';
        $finalized = false;

        try {
            while (! $body->eof()) {
                if (connection_status() !== CONNECTION_NORMAL || connection_aborted()) {
                    return;
                }

                try {
                    $chunk = $body->read(1024);
                } catch (RuntimeException $e) {
                    throw new UpstreamStreamClosed(previous: $e);
                }
                if ($chunk === '') {
                    continue;
                }

                $buffer .= $chunk;

                while (($newlinePos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $newlinePos);
                    $buffer = substr($buffer, $newlinePos + 1);

                    if (trim($line) === '' || ! str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $payload = substr($line, 6);
                    if ($payload === '[DONE]') {
                        $finalized = true;
                        yield from $this->finalize($context, $contentBuffer, $contentMode);

                        return;
                    }

                    try {
                        $json = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        continue;
                    }

                    if (! is_array($json)) {
                        continue;
                    }

                    if (! $generationIdYielded && isset($json['id']) && is_string($json['id'])) {
                        $context->generationId = $json['id'];
                        $generationIdYielded = true;
                        yield StreamEvent::meta($context->assistantMessageId, 'generation_id', $json['id']);
                    }

                    $delta = $json['choices'][0]['delta']['content'] ?? null;
                    if (is_string($delta) && $delta !== '') {
                        // Some open-weight models on OpenAI-compatible endpoints
                        // (Qwen3, Gemma 3, gpt-oss-* on Chat Completions) emit
                        // tool calls as JSON inside `content` instead of via
                        // `delta.tool_calls`. Sniff the first non-whitespace
                        // character: `{`/`[`/backtick means hold the buffer
                        // and try to extract a synthetic tool_call once the
                        // stream completes. Otherwise stream as plain text.
                        if ($contentMode === 'unknown') {
                            $contentBuffer .= $delta;
                            $leading = ltrim($contentBuffer);
                            if ($leading !== '') {
                                $first = $leading[0];
                                if ($first === '{' || $first === '[' || $first === '`') {
                                    $contentMode = 'json';
                                } else {
                                    $contentMode = 'text';
                                    $context->appendStreamedMessage($contentBuffer);
                                    yield StreamEvent::delta($context->assistantMessageId, $contentBuffer);
                                    $contentBuffer = '';
                                }
                            }
                        } elseif ($contentMode === 'json') {
                            $contentBuffer .= $delta;
                        } else {
                            $context->appendStreamedMessage($delta);
                            yield StreamEvent::delta($context->assistantMessageId, $delta);
                        }
                    }

                    // OpenAI tool-calls stream as a list of indexed partial
                    // chunks. First chunk per index carries `id` + name; later
                    // chunks carry only the `function.arguments` string
                    // fragment. We accumulate into ChatContext and let
                    // LlmSource act on them once the stream finishes.
                    $toolCalls = $json['choices'][0]['delta']['tool_calls'] ?? null;
                    if (is_array($toolCalls)) {
                        foreach ($toolCalls as $tc) {
                            if (is_array($tc)) {
                                $context->applyToolCallDelta($tc);
                            }
                        }
                    }
                }
            }
        } finally {
            if (! $finalized) {
                yield from $this->finalize($context, $contentBuffer, $contentMode);
            }
        }
    }

    /**
     * After the upstream stream ends, decide what to do with any content the
     * sniffer held back. If real tool_calls already arrived structurally, the
     * held content is irrelevant and dropped. Otherwise try to parse the
     * buffered text as a JSON tool call and synthesise a delta so LlmSource
     * can dispatch it; if that fails, flush the text as a normal delta so
     * the user still sees the message.
     */
    private function finalize(ChatContext $context, string $contentBuffer, string $contentMode): Generator
    {
        if ($contentMode !== 'json' || $contentBuffer === '') {
            return;
        }

        if ($context->pendingToolCalls !== []) {
            return;
        }

        $extracted = $this->extractToolCalls($contentBuffer);
        if ($extracted !== []) {
            foreach ($extracted as $index => $call) {
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
                'count' => count($extracted),
                'names' => array_column($extracted, 'name'),
                'assistant_message_id' => $context->assistantMessageId,
            ]);

            return;
        }

        $context->appendStreamedMessage($contentBuffer);
        yield StreamEvent::delta($context->assistantMessageId, $contentBuffer);
    }

    /**
     * Extract tool calls from a raw text buffer that may contain JSON.
     * Handles four shapes:
     *   - {"name": "...", "parameters": {...}}                single call
     *   - {"name": "...", "arguments": {...}}                 single call (OpenAI-ish)
     *   - {"tool_calls": [{"function": {...}}, ...]}          OpenAI wrapper
     *   - [{"name": "..."}, ...]                              list of calls
     * Markdown code fences around the JSON are stripped first.
     *
     * @return array<int, array{name: string, arguments: array<string, mixed>}>
     */
    private function extractToolCalls(string $content): array
    {
        $stripped = trim($content);
        $stripped = preg_replace('/^```(?:json)?\s*\n?/i', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\n?```\s*$/', '', $stripped) ?? $stripped;
        $stripped = trim($stripped);
        if ($stripped === '') {
            return [];
        }

        try {
            $parsed = json_decode($stripped, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($parsed)) {
            return [];
        }

        if (isset($parsed['tool_calls']) && is_array($parsed['tool_calls'])) {
            return $this->normaliseList($parsed['tool_calls']);
        }

        if (array_is_list($parsed)) {
            return $this->normaliseList($parsed);
        }

        $single = $this->normaliseCall($parsed);

        return $single === null ? [] : [$single];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{name: string, arguments: array<string, mixed>}>
     */
    private function normaliseList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $call = $this->normaliseCall($item);
            if ($call !== null) {
                $out[] = $call;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{name: string, arguments: array<string, mixed>}|null
     */
    private function normaliseCall(array $item): ?array
    {
        $name = null;
        $arguments = null;

        if (isset($item['function']) && is_array($item['function'])) {
            $name = $item['function']['name'] ?? null;
            $arguments = $item['function']['arguments'] ?? null;
            if (is_string($arguments)) {
                try {
                    $arguments = json_decode($arguments, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $arguments = null;
                }
            }
        } else {
            $name = $item['name'] ?? null;
            $arguments = $item['parameters'] ?? $item['arguments'] ?? $item['input'] ?? [];
        }

        if (! is_string($name) || $name === '') {
            return null;
        }
        if (! is_array($arguments)) {
            $arguments = [];
        }

        return ['name' => $name, 'arguments' => $arguments];
    }
}
