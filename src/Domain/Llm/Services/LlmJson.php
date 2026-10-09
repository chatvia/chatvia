<?php

namespace Domain\Llm\Services;

/**
 * Tolerant JSON decoding for model replies. Models asked for JSON sometimes
 * wrap it in a Markdown code fence or add a sentence around it; this finds the
 * JSON anyway: the whole reply first, then the outermost `{…}` object, then the
 * outermost `[…]` list.
 */
final class LlmJson
{
    /**
     * @return array<array-key, mixed>|null null when the reply holds no JSON object or list
     */
    public static function decode(?string $reply): ?array
    {
        $reply = self::withoutCodeFence(trim((string) $reply));

        if ($reply === '') {
            return null;
        }

        return self::decodeArray($reply)
            ?? self::decodeBetween($reply, '{', '}')
            ?? self::decodeBetween($reply, '[', ']');
    }

    private static function withoutCodeFence(string $reply): string
    {
        if (! str_starts_with($reply, '```')) {
            return $reply;
        }

        return preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $reply) ?? $reply;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decodeBetween(string $reply, string $open, string $close): ?array
    {
        $start = strpos($reply, $open);
        $end = strrpos($reply, $close);

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return self::decodeArray(substr($reply, $start, $end - $start + 1));
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decodeArray(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
