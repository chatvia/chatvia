<?php

namespace Domain\Conversation\Services\Streaming\Transformers;

use JsonException;

/**
 * Reads tool calls a model wrote as JSON in its reply text. Accepts the
 * shapes open-weight models produce, with or without a Markdown code fence:
 *
 *   - {"name": "...", "parameters": {...}}           one call
 *   - {"name": "...", "arguments": {...}}            one call (OpenAI-like)
 *   - {"tool_calls": [{"function": {...}}, ...]}     the OpenAI wrapper
 *   - [{"name": "..."}, ...]                         a list of calls
 */
final class JsonToolCallParser
{
    /**
     * @return array<int, array{name: string, arguments: array<string, mixed>}>
     */
    public static function parse(string $content): array
    {
        $parsed = self::decode(self::withoutCodeFence($content));

        if ($parsed === null) {
            return [];
        }

        if (isset($parsed['tool_calls']) && is_array($parsed['tool_calls'])) {
            return self::calls($parsed['tool_calls']);
        }

        if (array_is_list($parsed)) {
            return self::calls($parsed);
        }

        $call = self::call($parsed);

        return $call === null ? [] : [$call];
    }

    private static function withoutCodeFence(string $content): string
    {
        $text = trim($content);
        $text = preg_replace('/^```(?:json)?\s*\n?/i', '', $text) ?? $text;
        $text = preg_replace('/\n?```\s*$/', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<mixed>|null
     */
    private static function decode(string $json): ?array
    {
        if ($json === '') {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int|string, mixed>  $items
     * @return array<int, array{name: string, arguments: array<string, mixed>}>
     */
    private static function calls(array $items): array
    {
        $calls = [];

        foreach ($items as $item) {
            $call = is_array($item) ? self::call($item) : null;

            if ($call !== null) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * @param  array<mixed>  $item
     * @return array{name: string, arguments: array<string, mixed>}|null
     */
    private static function call(array $item): ?array
    {
        if (isset($item['function']) && is_array($item['function'])) {
            $name = $item['function']['name'] ?? null;
            $arguments = $item['function']['arguments'] ?? null;

            if (is_string($arguments)) {
                $arguments = self::decode($arguments);
            }
        } else {
            $name = $item['name'] ?? null;
            $arguments = $item['parameters'] ?? $item['arguments'] ?? $item['input'] ?? [];
        }

        if (! is_string($name) || $name === '') {
            return null;
        }

        return ['name' => $name, 'arguments' => is_array($arguments) ? $arguments : []];
    }
}
