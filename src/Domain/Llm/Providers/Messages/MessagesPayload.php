<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Messages;

use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Providers\Anthropic;
use Illuminate\Support\Str;
use stdClass;

/**
 * Writes a Chat Completions request body as an Anthropic Messages API body,
 * so a turn built for Chat Completions (history, tools, tool results) can be
 * sent to `/messages` ({@see Anthropic}). System messages move to the
 * top-level `system`, tool calls and results become `tool_use` and
 * `tool_result` blocks, and turns of the same role are joined, as the API
 * wants them to alternate.
 */
final class MessagesPayload
{
    /**
     * The reply length when the request names none: the API needs one, and
     * the thinking counts toward it.
     */
    public const int DEFAULT_MAX_TOKENS = 16384;

    /** Request keys the Messages API takes as they are. */
    private const array KEPT = ['model', 'temperature', 'top_p', 'top_k', 'stream'];

    /**
     * The body. A reasoning effort turns on adaptive thinking at that effort;
     * the thinking text comes back only when it is shown.
     *
     * @param  array<string, mixed>  $payload  a Chat Completions body
     * @return array<string, mixed>
     */
    public static function fromChatCompletions(array $payload, bool $showsReasoning = false): array
    {
        $messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];

        $body = array_intersect_key($payload, array_flip(self::KEPT));
        $body['max_tokens'] = (int) ($payload['max_completion_tokens'] ?? $payload['max_tokens'] ?? self::DEFAULT_MAX_TOKENS);

        $system = self::system($messages);

        if ($system !== '') {
            $body['system'] = $system;
        }

        $body['messages'] = self::messages($messages);

        if (is_array($payload['tools'] ?? null) && $payload['tools'] !== []) {
            $body['tools'] = array_map(self::tool(...), $payload['tools']);

            $choice = self::toolChoice($payload['tool_choice'] ?? null, $payload['parallel_tool_calls'] ?? null);

            if ($choice !== null) {
                $body['tool_choice'] = $choice;
            }
        }

        $stop = $payload['stop'] ?? null;

        if (filled($stop)) {
            $body['stop_sequences'] = array_values((array) $stop);
        }

        $effort = $payload[AbstractLlmProvider::REASONING_EFFORT] ?? null;

        if (filled($effort)) {
            $body['output_config']['effort'] = $effort;

            // Thinking takes no sampling changes, and no forced tool call.
            if (! in_array($body['tool_choice']['type'] ?? null, ['any', 'tool'], true)) {
                $body['thinking'] = ['type' => 'adaptive', 'display' => $showsReasoning ? 'summarized' : 'omitted'];
                unset($body['temperature'], $body['top_p'], $body['top_k']);
            }
        }

        $schema = $payload['response_format']['json_schema']['schema'] ?? null;

        if (($payload['response_format']['type'] ?? null) === 'json_schema' && is_array($schema)) {
            $body['output_config']['format'] = ['type' => 'json_schema', 'schema' => MessagesSchema::from($schema)];
        }

        // Tool rounds and later turns resend the same system prompt and
        // tools; the provider caches up to the last block and bills a
        // repeat at a fraction of the price.
        $body['cache_control'] = ['type' => 'ephemeral'];

        return $body;
    }

    /**
     * The same body thinking on a token budget instead: the models before
     * adaptive thinking take only that, and neither an effort nor a
     * temperature beside it. The budget counts toward the reply length.
     *
     * @param  array<string, mixed>  $body  a body from {@see fromChatCompletions()}
     * @return array<string, mixed>
     */
    public static function withThinkingBudget(array $body): array
    {
        if (! isset($body['thinking'])) {
            return $body;
        }

        $budget = match ($body['output_config']['effort'] ?? null) {
            'low' => 2048,
            'medium' => 6144,
            default => 12288,
        };

        $body['thinking'] = ['type' => 'enabled', 'budget_tokens' => $budget];
        $body['max_tokens'] = (int) $body['max_tokens'] + $budget;
        unset($body['output_config']['effort'], $body['temperature'], $body['top_p'], $body['top_k']);

        if (($body['output_config'] ?? null) === []) {
            unset($body['output_config']);
        }

        return $body;
    }

    /**
     * Every system (or developer) message, in order, as one text.
     *
     * @param  array<int, mixed>  $messages
     */
    private static function system(array $messages): string
    {
        $texts = [];

        foreach ($messages as $message) {
            if (is_array($message) && in_array($message['role'] ?? null, ['system', 'developer'], true)) {
                $texts[] = self::text($message['content'] ?? '');
            }
        }

        return implode("\n\n", array_filter($texts, static fn (string $text): bool => $text !== ''));
    }

    /**
     * The history as alternating user and assistant turns of blocks. A staff
     * member's reply in the history reads as the assistant's; a tool result
     * is the user's.
     *
     * @param  array<int, mixed>  $messages
     * @return list<array{role: string, content: list<array<string, mixed>>}>
     */
    private static function messages(array $messages): array
    {
        $turns = [];

        foreach ($messages as $message) {
            if (! is_array($message) || in_array($message['role'] ?? null, ['system', 'developer'], true)) {
                continue;
            }

            $role = in_array($message['role'] ?? null, ['assistant', 'agent'], true) ? 'assistant' : 'user';
            $blocks = match (true) {
                ($message['role'] ?? null) === 'tool' => [self::toolResult($message)],
                $role === 'assistant' => self::assistantBlocks($message),
                default => self::blocks($message['content'] ?? ''),
            };

            if ($blocks === []) {
                continue;
            }

            $last = array_key_last($turns);

            if ($last !== null && $turns[$last]['role'] === $role) {
                array_push($turns[$last]['content'], ...$blocks);
            } else {
                $turns[] = ['role' => $role, 'content' => $blocks];
            }
        }

        return $turns;
    }

    /**
     * An assistant turn's text, then one `tool_use` block per call. Arguments
     * that are not a JSON object are sent as an empty one.
     *
     * @param  array<array-key, mixed>  $message
     * @return list<array<string, mixed>>
     */
    private static function assistantBlocks(array $message): array
    {
        $blocks = self::blocks($message['content'] ?? '');

        foreach (is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [] as $call) {
            $input = json_decode((string) ($call['function']['arguments'] ?? ''), true);

            $blocks[] = [
                'type' => 'tool_use',
                'id' => (string) ($call['id'] ?? ''),
                'name' => (string) ($call['function']['name'] ?? ''),
                'input' => is_array($input) && ! array_is_list($input) ? $input : new stdClass,
            ];
        }

        return $blocks;
    }

    /**
     * @param  array<array-key, mixed>  $message
     * @return array<string, mixed>
     */
    private static function toolResult(array $message): array
    {
        $content = $message['content'] ?? '';

        return [
            'type' => 'tool_result',
            'tool_use_id' => (string) ($message['tool_call_id'] ?? ''),
            'content' => is_scalar($content) ? (string) $content : (string) json_encode($content),
        ];
    }

    /**
     * Message content as Messages blocks. Empty text is left out: the API
     * refuses an empty text block.
     *
     * @return list<array<string, mixed>>
     */
    private static function blocks(mixed $content): array
    {
        $parts = is_array($content) ? $content : [['type' => 'text', 'text' => (string) $content]];

        return array_values(array_filter(
            array_map(self::block(...), $parts),
            static fn (?array $block): bool => $block !== null && ($block['type'] !== 'text' || Str::trim((string) $block['text']) !== ''),
        ));
    }

    /**
     * A Chat Completions content part as a Messages block: text, an image, or
     * a PDF (`document_url`, a data URL or a link).
     *
     * @return array<string, mixed>|null
     */
    private static function block(mixed $part): ?array
    {
        if (! is_array($part)) {
            return ['type' => 'text', 'text' => (string) $part];
        }

        return match ($part['type'] ?? null) {
            'text' => ['type' => 'text', 'text' => (string) ($part['text'] ?? '')],
            'image_url' => ['type' => 'image', 'source' => self::source((string) ($part['image_url']['url'] ?? $part['image_url'] ?? ''))],
            'document_url' => ['type' => 'document', 'source' => self::source((string) ($part['document_url']['url'] ?? $part['document_url'] ?? ''))],
            default => null,
        };
    }

    /**
     * A file's source: the bytes of a `data:` URL, or the link.
     *
     * @return array<string, string>
     */
    private static function source(string $url): array
    {
        if (! Str::startsWith($url, 'data:')) {
            return ['type' => 'url', 'url' => $url];
        }

        [$meta, $data] = array_pad(explode(',', Str::chopStart($url, 'data:'), 2), 2, '');

        return ['type' => 'base64', 'media_type' => Str::before($meta, ';'), 'data' => $data];
    }

    /**
     * A Chat Completions tool in Messages form: the function's name and
     * description, its parameters as `input_schema`.
     *
     * @return array<string, mixed>
     */
    private static function tool(mixed $tool): array
    {
        $function = is_array($tool) && is_array($tool['function'] ?? null) ? $tool['function'] : [];

        return array_filter([
            'name' => $function['name'] ?? null,
            'description' => $function['description'] ?? null,
            'input_schema' => $function['parameters'] ?? ['type' => 'object', 'properties' => new stdClass],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * The tool choice in Messages form; null leaves it to the model.
     *
     * @return array<string, mixed>|null
     */
    private static function toolChoice(mixed $choice, mixed $parallel): ?array
    {
        $type = match (true) {
            $choice === 'none' => ['type' => 'none'],
            $choice === 'required' => ['type' => 'any'],
            is_array($choice) && isset($choice['function']['name']) => ['type' => 'tool', 'name' => (string) $choice['function']['name']],
            $parallel === false => ['type' => 'auto'],
            default => null,
        };

        if ($type !== null && $parallel === false && $type['type'] !== 'none') {
            $type['disable_parallel_tool_use'] = true;
        }

        return $type;
    }

    /** Content as plain text: the text of its parts, joined. */
    private static function text(mixed $content): string
    {
        if (! is_array($content)) {
            return (string) $content;
        }

        return implode('', array_map(static fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? '') : (string) $part, $content));
    }
}
