<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Messages;

use Domain\Llm\Providers\Anthropic;
use Illuminate\Support\Arr;

/**
 * Rewrites a reply's JSON schema into one Anthropic's structured outputs
 * accept ({@see Anthropic}). The API refuses number and length limits, array
 * sizes above one item and open objects, so those limits are taken out and
 * written into the field's description instead, where the model still reads
 * them, and every object is closed. The official SDKs do the same.
 */
final class MessagesSchema
{
    /** Limits the API refuses wherever they appear. */
    private const array UNSUPPORTED = [
        'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf',
        'minLength', 'maxLength', 'maxItems', 'uniqueItems', 'minProperties', 'maxProperties',
    ];

    /** Keys whose value is one schema. */
    private const array SCHEMA_KEYS = ['items', 'not'];

    /** Keys whose value is a list of schemas. */
    private const array SCHEMA_LIST_KEYS = ['anyOf', 'allOf', 'oneOf', 'prefixItems'];

    /** Keys whose value maps names to schemas. */
    private const array SCHEMA_MAP_KEYS = ['properties', '$defs', 'definitions'];

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function from(array $schema): array
    {
        $dropped = Arr::only($schema, self::UNSUPPORTED);
        $schema = Arr::except($schema, self::UNSUPPORTED);

        // Only "none" or "at least one" may be required of an array.
        if (is_int($schema['minItems'] ?? null) && $schema['minItems'] > 1) {
            $dropped['minItems'] = $schema['minItems'];
            unset($schema['minItems']);
        }

        foreach (self::SCHEMA_KEYS as $key) {
            if (is_array($schema[$key] ?? null)) {
                $schema[$key] = self::from($schema[$key]);
            }
        }

        foreach ([...self::SCHEMA_LIST_KEYS, ...self::SCHEMA_MAP_KEYS] as $key) {
            if (is_array($schema[$key] ?? null)) {
                $schema[$key] = array_map(static fn (mixed $child): mixed => is_array($child) ? self::from($child) : $child, $schema[$key]);
            }
        }

        if (($schema['type'] ?? null) === 'object' || isset($schema['properties'])) {
            $schema['additionalProperties'] = false;
        }

        if ($dropped !== []) {
            $schema['description'] = self::describe($schema['description'] ?? null, $dropped);
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $limits
     */
    private static function describe(mixed $description, array $limits): string
    {
        $note = collect($limits)
            ->map(static fn (mixed $value, string $key): string => $key.': '.json_encode($value))
            ->implode(', ');

        return filled($description) ? "{$description} ({$note})" : "({$note})";
    }
}
