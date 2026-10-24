<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Responses;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Illuminate\Support\Str;
use JsonException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Reads a Responses API event stream as if it were a Chat Completions
 * stream, so the chat engine reads both the same way. Each event becomes one
 * Chat Completions chunk: answer text as `delta.content`, the thinking's
 * summary as `delta.reasoning`, function calls as indexed `delta.tool_calls`
 * pieces, and the token counts as `usage` before the closing `[DONE]`. Text
 * is passed on as soon as each event arrives, never held for a full read.
 *
 * A failed response ends the read with an exception, as a dropped stream
 * would.
 */
final class ResponsesStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private const int READ_BYTES = 1024;

    private const string DATA_PREFIX = 'data: ';

    /** Upstream bytes not yet split into whole lines. */
    private string $pending = '';

    /** Translated bytes not yet read. */
    private string $buffer = '';

    /** @var array<string, int> each function call item's id → its tool call index */
    private array $toolIndexes = [];

    /** Whether any thinking was passed on yet, to set the next summary part apart. */
    private bool $reasoned = false;

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
        $line = Str::rtrim($line, "\r");

        if (! Str::startsWith($line, self::DATA_PREFIX)) {
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
            'response.created' => $this->chunk(['id' => (string) ($event['response']['id'] ?? ''), 'choices' => []]),
            'response.output_text.delta' => $this->delta(['content' => (string) ($event['delta'] ?? '')]),
            'response.reasoning_summary_part.added' => $this->reasoned ? $this->delta(['reasoning' => "\n\n"]) : '',
            'response.reasoning_summary_text.delta' => $this->reasoning((string) ($event['delta'] ?? '')),
            'response.output_item.added' => $this->functionCall(is_array($event['item'] ?? null) ? $event['item'] : []),
            'response.function_call_arguments.delta' => $this->functionArguments((string) ($event['item_id'] ?? ''), (string) ($event['delta'] ?? '')),
            'response.completed', 'response.incomplete' => $this->finish(is_array($event['response']['usage'] ?? null) ? $event['response']['usage'] : []),
            'response.failed' => throw new RuntimeException('Responses API reply failed: '.($event['response']['error']['message'] ?? 'unknown error')),
            'error' => throw new RuntimeException('Responses API stream error: '.($event['message'] ?? $event['error']['message'] ?? 'unknown error')),
            default => '',
        };
    }

    private function reasoning(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $this->reasoned = true;

        return $this->delta(['reasoning' => $text]);
    }

    /**
     * The start of a function call: its id and name, at the next index.
     *
     * @param  array<array-key, mixed>  $item
     */
    private function functionCall(array $item): string
    {
        if (($item['type'] ?? null) !== 'function_call') {
            return '';
        }

        $index = count($this->toolIndexes);
        $this->toolIndexes[(string) ($item['id'] ?? $index)] = $index;

        return $this->delta(['tool_calls' => [[
            'index' => $index,
            'id' => (string) ($item['call_id'] ?? ''),
            'type' => 'function',
            'function' => ['name' => (string) ($item['name'] ?? ''), 'arguments' => (string) ($item['arguments'] ?? '')],
        ]]]);
    }

    private function functionArguments(string $itemId, string $arguments): string
    {
        $index = $this->toolIndexes[$itemId] ?? null;

        if ($index === null || $arguments === '') {
            return '';
        }

        return $this->delta(['tool_calls' => [['index' => $index, 'function' => ['arguments' => $arguments]]]]);
    }

    /**
     * The token counts under their Chat Completions names, then the end.
     *
     * @param  array<array-key, mixed>  $usage
     */
    private function finish(array $usage): string
    {
        $counts = [
            'prompt_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
            'prompt_tokens_details' => ['cached_tokens' => (int) ($usage['input_tokens_details']['cached_tokens'] ?? 0)],
            'completion_tokens_details' => ['reasoning_tokens' => (int) ($usage['output_tokens_details']['reasoning_tokens'] ?? 0)],
        ];

        return ($usage !== [] ? $this->chunk(['choices' => [], 'usage' => $counts]) : '').self::DATA_PREFIX."[DONE]\n\n";
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
