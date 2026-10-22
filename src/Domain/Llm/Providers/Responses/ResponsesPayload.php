<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Responses;

use Domain\Llm\AbstractLlmProvider;
use stdClass;

/**
 * Writes a Chat Completions request body as a Responses API body, so a turn
 * built for Chat Completions (history, tools, tool results) can be sent to a
 * provider's `/responses` ({@see StreamsThroughResponses}). Every call is
 * stateless: the whole history goes each time and nothing is stored at the
 * provider.
 */
final class ResponsesPayload
{
    /** Request keys the Responses API takes as they are. */
    private const array KEPT = ['model', 'temperature', 'top_p', 'presence_penalty', 'frequency_penalty', 'parallel_tool_calls', 'tool_choice', 'user'];

    /**
     * The body. A reasoning effort in the request becomes `reasoning.effort`.
     *
     * @param  array<string, mixed>  $payload  a Chat Completions body
     * @return array<string, mixed>
     */
    public static function fromChatCompletions(array $payload): array
    {
        $body = array_intersect_key($payload, array_flip(self::KEPT));
        $body['input'] = self::input(is_array($payload['messages'] ?? null) ? $payload['messages'] : []);
        $body['stream'] = true;
        $body['store'] = false;

        $maxTokens = $payload['max_completion_tokens'] ?? $payload['max_tokens'] ?? null;

        if ($maxTokens !== null) {
            $body['max_output_tokens'] = $maxTokens;
        }

        if (is_array($payload['tools'] ?? null) && $payload['tools'] !== []) {
            $body['tools'] = array_map(self::tool(...), $payload['tools']);
        }

        $effort = $payload[AbstractLlmProvider::REASONING_EFFORT] ?? null;

        if (is_string($effort)) {
            $body['reasoning'] = ['effort' => $effort];
        }

        return $body;
    }

    /**
     * The same body asking for a summary of the thinking as well, so it can
     * be shown. Some providers refuse it (OpenAI, until it has verified the
     * organization).
     *
     * @param  array<string, mixed>  $body  a body from {@see fromChatCompletions()}
     * @return array<string, mixed>
     */
    public static function withSummary(array $body): array
    {
        if (is_array($body['reasoning'] ?? null)) {
            $body['reasoning']['summary'] = 'auto';
        }

        return $body;
    }

    /**
     * The chat history as Responses input items. An assistant turn that
     * called tools becomes its text (if any) and one `function_call` item per
     * call; a tool result becomes a `function_call_output` item.
     *
     * @param  array<int, mixed>  $messages
     * @return list<array<string, mixed>>
     */
    private static function input(array $messages): array
    {
        $items = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? 'user');
            $content = $message['content'] ?? '';

            if ($role === 'tool') {
                $items[] = [
                    'type' => 'function_call_output',
                    'call_id' => (string) ($message['tool_call_id'] ?? ''),
                    'output' => is_string($content) ? $content : (string) json_encode($content),
                ];

                continue;
            }

            if ($role === 'assistant') {
                array_push($items, ...self::assistant($message));

                continue;
            }

            $items[] = ['role' => $role, 'content' => is_array($content) ? array_map(self::inputPart(...), $content) : (string) $content];
        }

        return $items;
    }

    /**
     * @param  array<array-key, mixed>  $message
     * @return list<array<string, mixed>>
     */
    private static function assistant(array $message): array
    {
        $content = $message['content'] ?? '';
        $text = is_array($content)
            ? implode('', array_map(static fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? '') : '', $content))
            : (string) $content;

        $items = $text !== '' ? [['role' => 'assistant', 'content' => $text]] : [];

        foreach (is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [] as $call) {
            $items[] = [
                'type' => 'function_call',
                'call_id' => (string) ($call['id'] ?? ''),
                'name' => (string) ($call['function']['name'] ?? ''),
                'arguments' => (string) ($call['function']['arguments'] ?? '{}'),
            ];
        }

        return $items;
    }

    /**
     * A Chat Completions content part in Responses form. Parts it has no
     * other name for are sent as they are.
     *
     * @return array<array-key, mixed>
     */
    private static function inputPart(mixed $part): array
    {
        if (! is_array($part)) {
            return ['type' => 'input_text', 'text' => (string) $part];
        }

        return match ($part['type'] ?? null) {
            'text' => ['type' => 'input_text', 'text' => (string) ($part['text'] ?? '')],
            'image_url' => [
                'type' => 'input_image',
                'image_url' => (string) ($part['image_url']['url'] ?? $part['image_url'] ?? ''),
                'detail' => (string) ($part['image_url']['detail'] ?? 'auto'),
            ],
            default => $part,
        };
    }

    /**
     * A Chat Completions tool in Responses form: the function's fields move
     * up one level. Strict schemas stay off unless asked for, since the
     * Responses API turns them on by default and most tool schemas (MCP
     * servers, functions) do not meet its rules.
     *
     * @return array<string, mixed>
     */
    private static function tool(mixed $tool): array
    {
        $function = is_array($tool) && is_array($tool['function'] ?? null) ? $tool['function'] : [];

        return array_filter([
            'type' => 'function',
            'name' => $function['name'] ?? null,
            'description' => $function['description'] ?? null,
            'parameters' => $function['parameters'] ?? ['type' => 'object', 'properties' => new stdClass],
        ], static fn (mixed $value): bool => $value !== null) + ['strict' => (bool) ($function['strict'] ?? false)];
    }
}
