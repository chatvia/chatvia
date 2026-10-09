<?php

namespace Domain\Integration\Function\Services;

use Domain\Integration\Function\Rules\ValidParametersSchema;

/**
 * Validates that a user-supplied parameters schema is well-formed enough for
 * the LLM to use it as a tool definition.
 *
 * This is *shape* validation — does the JSON look like a JSON Schema for an
 * object whose properties an LLM can populate? It does not fully validate
 * the schema against the JSON Schema spec. Function create/update requests
 * run it through {@see ValidParametersSchema}.
 */
class FunctionSchemaValidator
{
    /**
     * Primitive JSON Schema `type` values an LLM can populate.
     *
     * @var list<string>
     */
    private const array ALLOWED_PROPERTY_TYPES = [
        'string', 'number', 'integer', 'boolean', 'array', 'object', 'null',
    ];

    private const string PROPERTY_NAME_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    private const int MAX_PROPERTIES = 100;

    private const int MAX_DEPTH = 5;

    /**
     * Validate the shape of a parameters schema.
     *
     * @param  mixed  $schema  the value the user submitted
     * @return list<string> zero or more error messages; empty = valid
     */
    public function validateShape(mixed $schema): array
    {
        if (! is_array($schema)) {
            return ['The parameters schema must be a JSON object.'];
        }

        $errors = [];

        if (($schema['type'] ?? null) !== 'object') {
            $errors[] = 'The parameters schema must have type "object".';
        }

        $properties = $schema['properties'] ?? null;
        if ($properties !== null && ! is_array($properties)) {
            $errors[] = 'The parameters schema "properties" must be an object.';
        }

        if (is_array($properties)) {
            array_push($errors, ...$this->validateProperties($properties));
        }

        $required = $schema['required'] ?? null;
        if ($required !== null) {
            array_push($errors, ...$this->validateRequired($required, is_array($properties) ? array_keys($properties) : []));
        }

        return $errors;
    }

    public function isValidShape(mixed $schema): bool
    {
        return $this->validateShape($schema) === [];
    }

    /**
     * @param  array<array-key, mixed>  $properties
     * @return list<string>
     */
    private function validateProperties(array $properties): array
    {
        $errors = [];

        if (count($properties) > self::MAX_PROPERTIES) {
            $errors[] = 'The parameters schema has too many properties (max '.self::MAX_PROPERTIES.').';
        }

        foreach ($properties as $name => $definition) {
            $nameErrors = $this->validatePropertyName((string) $name);
            if ($nameErrors !== []) {
                array_push($errors, ...$nameErrors);

                continue;
            }

            array_push($errors, ...$this->validatePropertyDefinition((string) $name, $definition, depth: 1));
        }

        return $errors;
    }

    /**
     * @param  array<int, array-key>  $propertyNames
     * @return list<string>
     */
    private function validateRequired(mixed $required, array $propertyNames): array
    {
        if (! (is_array($required) && array_is_list($required))) {
            return ['The parameters schema "required" must be an array of property names.'];
        }

        $errors = [];

        foreach ($required as $i => $req) {
            if (! is_string($req)) {
                $errors[] = "The parameters schema required[{$i}] must be a string.";

                continue;
            }
            if ($propertyNames !== [] && ! in_array($req, $propertyNames, true)) {
                $errors[] = "The required property \"{$req}\" is not declared in properties.";
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validatePropertyName(string $name): array
    {
        if (! preg_match(self::PROPERTY_NAME_REGEX, $name)) {
            return ["Invalid property name \"{$name}\" — must start with a letter or underscore and contain only letters, numbers, and underscores."];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function validatePropertyDefinition(string $name, mixed $definition, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            return ["Property \"{$name}\" is nested too deeply (max depth ".self::MAX_DEPTH.')'];
        }

        if (! is_array($definition)) {
            return ["Property \"{$name}\" definition must be an object."];
        }

        $type = $definition['type'] ?? null;

        // `type` is technically optional in JSON Schema, but for LLM tool
        // calling the LLM needs a hint. We allow null + warn, but require
        // anything non-null to be a known primitive.
        if ($type === null) {
            return [];
        }

        $types = is_array($type) ? $type : [$type];
        $errors = [];

        foreach ($types as $t) {
            if (! is_string($t) || ! in_array($t, self::ALLOWED_PROPERTY_TYPES, true)) {
                $errors[] = "Property \"{$name}\" has unsupported type ".json_encode($t).'.';
            }
        }

        // Recurse into nested object properties.
        if (in_array('object', $types, true) && isset($definition['properties']) && is_array($definition['properties'])) {
            foreach ($definition['properties'] as $childName => $childDef) {
                $childErrors = $this->validatePropertyName((string) $childName);
                if ($childErrors !== []) {
                    array_push($errors, ...$childErrors);

                    continue;
                }
                array_push($errors, ...$this->validatePropertyDefinition("{$name}.{$childName}", $childDef, $depth + 1));
            }
        }

        // Recurse into array item definitions.
        if (in_array('array', $types, true) && isset($definition['items']) && is_array($definition['items'])) {
            array_push($errors, ...$this->validatePropertyDefinition("{$name}[]", $definition['items'], $depth + 1));
        }

        return $errors;
    }
}
