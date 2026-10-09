<?php

namespace Domain\Integration\Function\Rules;

use Closure;
use Domain\Integration\Function\Services\FunctionSchemaValidator;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Save-time check that a function's `parameters_schema` is an object schema
 * the LLM can fill in. Accepts the dashboard's JSON string and the API's
 * decoded array; the shape rules live in {@see FunctionSchemaValidator}.
 */
class ValidParametersSchema implements ValidationRule
{
    /**
     * @param  array<string, mixed>|null  $current  the schema already stored on the function being edited
     */
    public function __construct(private readonly ?array $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $schema = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($schema)) {
            $fail('parameters_schema must be valid JSON describing the parameters.');

            return;
        }

        // A stored schema can be older than these shape rules. Sending it back
        // unchanged must not block edits to the function's other fields.
        if ($this->current !== null && $schema == $this->current) {
            return;
        }

        foreach ((new FunctionSchemaValidator)->validateShape($schema) as $error) {
            $fail($error);
        }
    }
}
