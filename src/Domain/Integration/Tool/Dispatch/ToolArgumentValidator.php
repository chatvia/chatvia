<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Dispatch;

use Domain\Integration\Tool\Exceptions\InvalidToolArgumentsException;
use Illuminate\Support\Arr;

/**
 * Checks the arguments the model wrote for a tool call against the tool's
 * stored JSON schema before they leave the app. The model's output is
 * untrusted (a prompt injection can steer it), so a webhook or MCP server
 * only receives the top-level keys its schema declares, with the basic
 * types it declares, and every required key present.
 *
 * Deliberately small: only the top level is checked (`properties`,
 * `required`, `type`, `enum`, `additionalProperties`). Nested values pass
 * as they are; the receiving tool still validates its own input.
 */
final class ToolArgumentValidator
{
    /**
     * The arguments with undeclared top-level keys dropped.
     *
     * @param  array<array-key, mixed>  $schema  the tool's parameters (a JSON schema object)
     * @param  array<array-key, mixed>  $arguments  the model's decoded arguments
     * @return array<array-key, mixed>
     *
     * @throws InvalidToolArgumentsException
     */
    public function validated(array $schema, array $arguments): array
    {
        $properties = $schema['properties'] ?? null;

        // A schema that declares no properties says nothing to check against.
        if (! is_array($properties)) {
            return $arguments;
        }

        $arguments = $this->withoutUndeclaredKeys($schema, $properties, $arguments);
        $problems = [];

        foreach ((array) ($schema['required'] ?? []) as $required) {
            if (is_scalar($required) && ! array_key_exists((string) $required, $arguments)) {
                $problems[] = sprintf('"%s" is required', $required);
            }
        }

        foreach ($arguments as $key => $value) {
            $property = $properties[$key] ?? null;

            if (is_array($property)) {
                $problem = $this->problemWith((string) $key, $property, $value);

                if ($problem !== null) {
                    $problems[] = $problem;
                }
            }
        }

        if ($problems !== []) {
            throw InvalidToolArgumentsException::because($problems);
        }

        return $arguments;
    }

    /**
     * Keys the schema does not declare are dropped, unless the schema allows
     * extra keys on purpose (`additionalProperties` true or a schema).
     *
     * @param  array<array-key, mixed>  $schema
     * @param  array<array-key, mixed>  $properties
     * @param  array<array-key, mixed>  $arguments
     * @return array<array-key, mixed>
     */
    private function withoutUndeclaredKeys(array $schema, array $properties, array $arguments): array
    {
        $additional = $schema['additionalProperties'] ?? false;

        if ($additional === true || is_array($additional)) {
            return $arguments;
        }

        return array_intersect_key($arguments, $properties);
    }

    /**
     * @param  array<array-key, mixed>  $property
     */
    private function problemWith(string $key, array $property, mixed $value): ?string
    {
        $types = array_values(array_filter((array) ($property['type'] ?? []), is_scalar(...)));

        if ($types !== [] && ! array_any($types, fn (mixed $type): bool => $this->isOfType((string) $type, $value))) {
            return sprintf('"%s" must be of type %s', $key, Arr::join($types, ' or '));
        }

        $allowed = $property['enum'] ?? null;

        if (is_array($allowed) && $allowed !== [] && ! collect($allowed)->containsStrict($value)) {
            return sprintf('"%s" must be one of the allowed values', $key);
        }

        return null;
    }

    private function isOfType(string $type, mixed $value): bool
    {
        $actual = get_debug_type($value);

        return match ($type) {
            'string' => $actual === 'string',
            'integer' => $actual === 'int' || ($actual === 'float' && floor($value) === $value),
            'number' => $actual === 'int' || $actual === 'float',
            'boolean' => $actual === 'bool',
            'array' => is_array($value) && array_is_list($value),
            'object' => is_array($value) && ($value === [] || ! array_is_list($value)),
            'null' => $value === null,
            // A type this check does not know is not held against the call.
            default => true,
        };
    }
}
