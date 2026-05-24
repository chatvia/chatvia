<?php

namespace Tests\Unit\Services\Chat\Tooling;

use Domain\Chat\Services\Tooling\FunctionSchemaValidator;
use PHPUnit\Framework\TestCase;

class FunctionSchemaValidatorTest extends TestCase
{
    private FunctionSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FunctionSchemaValidator;
    }

    public function test_accepts_a_minimal_well_formed_object_schema(): void
    {
        // Laravel decodes incoming JSON {} as an empty PHP array; the
        // validator must accept that representation.
        $this->assertSame([], $this->validator->validateShape([
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ]));
    }

    public function test_accepts_the_get_weather_template(): void
    {
        $this->assertTrue($this->validator->isValidShape([
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'City name'],
            ],
            'required' => ['city'],
        ]));
    }

    public function test_rejects_non_object_input(): void
    {
        $errors = $this->validator->validateShape('not an object');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('must be a JSON object', $errors[0]);
    }

    public function test_rejects_non_object_root_type(): void
    {
        $errors = $this->validator->validateShape(['type' => 'string']);
        $this->assertContains('The parameters schema must have type "object".', $errors);
    }

    public function test_rejects_invalid_property_names(): void
    {
        $errors = $this->validator->validateShape([
            'type' => 'object',
            'properties' => [
                'good_one' => ['type' => 'string'],
                '1bad' => ['type' => 'string'],
                'with space' => ['type' => 'string'],
            ],
        ]);
        $found = implode("\n", $errors);
        $this->assertStringContainsString('1bad', $found);
        $this->assertStringContainsString('with space', $found);
    }

    public function test_rejects_unsupported_property_types(): void
    {
        $errors = $this->validator->validateShape([
            'type' => 'object',
            'properties' => [
                'x' => ['type' => 'function'],
            ],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('unsupported type', $errors[0]);
    }

    public function test_rejects_required_referencing_undeclared_property(): void
    {
        $errors = $this->validator->validateShape([
            'type' => 'object',
            'properties' => ['a' => ['type' => 'string']],
            'required' => ['a', 'b'],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('"b"', $errors[0]);
    }

    public function test_required_must_be_a_list(): void
    {
        $errors = $this->validator->validateShape([
            'type' => 'object',
            'properties' => ['a' => ['type' => 'string']],
            'required' => ['key' => 'a'],
        ]);
        $this->assertContains('The parameters schema "required" must be an array of property names.', $errors);
    }

    public function test_recurses_into_nested_objects(): void
    {
        $errors = $this->validator->validateShape([
            'type' => 'object',
            'properties' => [
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'bad-name' => ['type' => 'string'],
                    ],
                ],
            ],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('bad-name', $errors[0]);
    }

    public function test_recurses_into_array_items(): void
    {
        $errors = $this->validator->validateShape([
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'nope'],
                ],
            ],
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('tags[]', $errors[0]);
    }

    public function test_accepts_union_type_arrays_for_nullable_fields(): void
    {
        $errors = $this->validator->validateShape([
            'type' => 'object',
            'properties' => [
                'maybe' => ['type' => ['string', 'null']],
            ],
        ]);
        $this->assertSame([], $errors);
    }

    public function test_rejects_too_deep_nesting(): void
    {
        $deep = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]];
        for ($i = 0; $i < 10; $i++) {
            $deep = ['type' => 'object', 'properties' => ['nest' => $deep]];
        }

        $errors = $this->validator->validateShape($deep);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('too deeply', implode("\n", $errors));
    }
}
