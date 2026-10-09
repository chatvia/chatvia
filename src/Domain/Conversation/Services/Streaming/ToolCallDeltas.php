<?php

namespace Domain\Conversation\Services\Streaming;

/**
 * Accumulates the tool calls an OpenAI-compatible stream sends in pieces.
 * The first fragment of a call carries its `id` and (usually)
 * `function.name`; later fragments only carry more of `function.arguments`,
 * a JSON string to append. Fragments are matched by their `index`.
 *
 * Pure function, shared by the chat engine's ChatContext and the analytics
 * chat runner so both read tool calls the same way.
 */
final class ToolCallDeltas
{
    /**
     * @param  array<int, array{id: string, name: string, arguments: string}>  $pending
     * @param  array<string, mixed>  $delta  one entry of `choices[0].delta.tool_calls`
     * @return array<int, array{id: string, name: string, arguments: string}>
     */
    public static function merge(array $pending, array $delta): array
    {
        $index = (int) ($delta['index'] ?? 0);
        $current = $pending[$index] ?? ['id' => '', 'name' => '', 'arguments' => ''];

        if (isset($delta['id']) && is_string($delta['id'])) {
            $current['id'] = $delta['id'];
        }

        $function = $delta['function'] ?? null;

        if (is_array($function)) {
            if (isset($function['name']) && is_string($function['name'])) {
                $current['name'] = $function['name'];
            }

            if (isset($function['arguments']) && is_string($function['arguments'])) {
                $current['arguments'] .= $function['arguments'];
            }
        }

        $pending[$index] = $current;

        return $pending;
    }
}
