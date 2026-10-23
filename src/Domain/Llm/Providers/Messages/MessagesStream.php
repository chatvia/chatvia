<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Messages;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use JsonException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Reads an Anthropic Messages event stream as if it were a Chat Completions
 * stream, so the chat engine reads both the same way. Each event becomes one
 * Chat Completions chunk: answer text as `delta.content`, the thinking as
 * `delta.reasoning`, tool calls as indexed `delta.tool_calls` pieces, and the
 * token counts as `usage` before the closing `[DONE]`. Text is passed on as
 * soon as each event arrives, never held for a full read.
 *
 * An error event ends the read with an exception, as a dropped stream would.
 */
final class MessagesStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private const int READ_BYTES = 1024;

    private const string DATA_PREFIX = 'data: ';

    /** Upstream bytes not yet split into whole lines. */
    private string $pending = '';

    /** Translated bytes not yet read. */
    private string $buffer = '';

    /** @var array<int, int> each tool_use block's index → its tool call index */
    private array $toolIndexes = [];

    /** Whether any thinking was passed on yet, to set the next thinking block apart. */
    private bool $reasoned = false;

    /**
     * The counts so far: `message_start` gives the input, each
     * `message_delta` the output so far.
     *
     * @var array<string, mixed>
     */
    private array $usage = [];

    public function __construct(private StreamInterface $stream) {}

    public function read(int $length): string
    {
        while ($this->buffer === '' && ! $this->stream->eof()) {
            $this->pending .= $this->stream->read(self::READ_BYTES);

            while (($newline = strpos($this->pending, "\n")) !== false) {
                $this->buffer .= $this->translate(substr($this->pending, 0, $newline));
                $this->pending = substr($this->pending, $newline + 1);
            }
        }

        if ($this->buffer === '' && $this->pending !== '') {
            $this->buffer = $this->translate($this->pending);
            $this->pending = '';
        }

        $out = substr($this->buffer, 0, $length);
        $this->buffer = substr($this->buffer, strlen($out));

        return $out;
    }

    public function eof(): bool
    {
        return $this->buffer === '' && $this->pending === '' && $this->stream->eof();
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function isWritable(): bool
    {
        return false;
    }

    /** One upstream line as Chat Completions SSE (often nothing). */
    private function translate(string $line): string
    {
        $line = rtrim($line, "\r");

        if (! str_starts_with($line, self::DATA_PREFIX)) {
            return '';
        }

        try {
            $event = json_decode(substr($line, strlen(self::DATA_PREFIX)), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        if (! is_array($event)) {
            return '';
        }

        return match ($event['type'] ?? null) {
            'message_start' => $this->start(is_array($event['message'] ?? null) ? $event['message'] : []),
            'content_block_start' => $this->blockStart((int) ($event['index'] ?? 0), is_array($event['content_block'] ?? null) ? $event['content_block'] : []),
            'content_block_delta' => $this->blockDelta((int) ($event['index'] ?? 0), is_array($event['delta'] ?? null) ? $event['delta'] : []),
            'message_delta' => $this->count(is_array($event['usage'] ?? null) ? $event['usage'] : []),
            'message_stop' => $this->finish(),
            'error' => throw new RuntimeException('Messages API stream error: '.($event['error']['message'] ?? 'unknown error')),
            default => '',
        };
    }

    /**
     * The message id, for the generation id, and the input counts.
     *
     * @param  array<array-key, mixed>  $message
     */
    private function start(array $message): string
    {
        $this->count(is_array($message['usage'] ?? null) ? $message['usage'] : []);

        return $this->chunk(['id' => (string) ($message['id'] ?? ''), 'choices' => []]);
    }

    /**
     * The start of a tool_use block: its id and name, at the next tool call
     * index. Text and thinking blocks start empty and say nothing yet.
     *
     * @param  array<array-key, mixed>  $block
     */
    private function blockStart(int $index, array $block): string
    {
        if (($block['type'] ?? null) === 'thinking') {
            return $this->reasoned ? $this->delta(['reasoning' => "\n\n"]) : '';
        }

        if (($block['type'] ?? null) !== 'tool_use') {
            return '';
        }

        $toolIndex = count($this->toolIndexes);
        $this->toolIndexes[$index] = $toolIndex;

        return $this->delta(['tool_calls' => [[
            'index' => $toolIndex,
            'id' => (string) ($block['id'] ?? ''),
            'type' => 'function',
            'function' => ['name' => (string) ($block['name'] ?? ''), 'arguments' => ''],
        ]]]);
    }

    /**
     * @param  array<array-key, mixed>  $delta
     */
    private function blockDelta(int $index, array $delta): string
    {
        $text = match ($delta['type'] ?? null) {
            'text_delta' => (string) ($delta['text'] ?? ''),
            'thinking_delta' => (string) ($delta['thinking'] ?? ''),
            'input_json_delta' => (string) ($delta['partial_json'] ?? ''),
            default => '',
        };

        if ($text === '') {
            return '';
        }

        if ($delta['type'] === 'thinking_delta') {
            $this->reasoned = true;
        }

        return match ($delta['type']) {
            'text_delta' => $this->delta(['content' => $text]),
            'thinking_delta' => $this->delta(['reasoning' => $text]),
            default => isset($this->toolIndexes[$index])
                ? $this->delta(['tool_calls' => [['index' => $this->toolIndexes[$index], 'function' => ['arguments' => $text]]]])
                : '',
        };
    }

    /**
     * Keep the latest counts: the events carry running totals.
     *
     * @param  array<array-key, mixed>  $usage
     */
    private function count(array $usage): string
    {
        $this->usage = array_merge($this->usage, array_filter($usage, static fn (mixed $value): bool => $value !== null));

        return '';
    }

    /** The token counts under their Chat Completions names, then the end. */
    private function finish(): string
    {
        $usage = $this->usage === [] ? '' : $this->chunk(['choices' => [], 'usage' => MessagesReply::usage($this->usage)]);

        return $usage.self::DATA_PREFIX."[DONE]\n\n";
    }

    /**
     * @param  array<string, mixed>  $delta
     */
    private function delta(array $delta): string
    {
        return $this->chunk(['choices' => [['index' => 0, 'delta' => $delta]]]);
    }

    /**
     * @param  array<string, mixed>  $chunk
     */
    private function chunk(array $chunk): string
    {
        return self::DATA_PREFIX.json_encode($chunk, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
    }
}
