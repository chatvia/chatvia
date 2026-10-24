<?php

namespace Domain\Llm\Services;

use Illuminate\Support\Str;

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
        $reply = self::withoutCodeFence(Str::trim($reply ?? ''));

        if ($reply === '') {
            return null;
        }

        return self::decodeArray($reply)
            ?? self::decodeBetween($reply, '{', '}')
            ?? self::decodeBetween($reply, '[', ']');
    }

    private static function withoutCodeFence(string $reply): string
    {
        if (! Str::startsWith($reply, '```')) {
            return $reply;
        }

        return Str::replaceMatches('/^```[a-zA-Z]*\s*|\s*```$/', '', $reply) ?? $reply;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decodeBetween(string $reply, string $open, string $close): ?array
    {
        $after = Str::after($reply, $open);

        if (! Str::contains($reply, $open) || ! Str::contains($after, $close)) {
            return null;
        }

        return self::decodeArray($open.Str::beforeLast($after, $close).$close);
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
