<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * The field-schema rules of an inline form, shared by the dashboard and API
 * form requests. The caller sets the top-level `schema` rule itself
 * (`required` on create, `sometimes` on a partial update).
 */
trait ValidatesFormSchema
{
    /**
     * The field types the chat can render.
     *
     * @var list<string>
     */
    public const array FIELD_TYPES = ['text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'multicheckbox', 'date'];

    /**
     * @return array<string, mixed>
     */
    protected function formSchemaFieldRules(): array
    {
        return [
            'schema.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/i'],
            'schema.*.label' => ['required', 'string', 'max:120'],
            'schema.*.type' => ['required', Rule::in(self::FIELD_TYPES)],
            'schema.*.required' => ['boolean'],
            'schema.*.placeholder' => ['nullable', 'string', 'max:120'],
            'schema.*.options' => ['nullable', 'array'],
            'schema.*.options.*.value' => ['required_with:schema.*.options', 'string', 'max:120'],
            'schema.*.options.*.label' => ['required_with:schema.*.options', 'string', 'max:120'],
        ];
    }
}
