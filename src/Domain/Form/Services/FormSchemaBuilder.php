<?php

namespace Domain\Form\Services;

/**
 * Turns our normalized field array (the FormDefinition `schema`, the single
 * source of truth) into the two things both sides need:
 *   - a FormKit-ready schema the client renders (built server-side — we never
 *     ship raw FormKit `$el`/`$cmp` nodes, so an admin can't inject markup), and
 *   - Laravel validation rules the submit path enforces (the authority).
 *
 * A field is `{key, label, type, required, placeholder?, options?, help?}`.
 */
class FormSchemaBuilder
{
    /** Normalized type → FormKit input type. */
    private const FORMKIT_TYPES = [
        'text' => 'text',
        'email' => 'email',
        'tel' => 'tel',
        'number' => 'number',
        'textarea' => 'textarea',
        'select' => 'select',
        'radio' => 'radio',
        'checkbox' => 'checkbox',
        'multicheckbox' => 'checkbox',
        'date' => 'date',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    public function toFormKitSchema(array $fields): array
    {
        $schema = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');
            if ($key === '' || ! isset(self::FORMKIT_TYPES[$type])) {
                continue;
            }

            $node = [
                '$formkit' => self::FORMKIT_TYPES[$type],
                'name' => $key,
                'label' => (string) ($field['label'] ?? $key),
            ];

            if (! empty($field['placeholder'])) {
                $node['placeholder'] = (string) $field['placeholder'];
            }
            if (! empty($field['help'])) {
                $node['help'] = (string) $field['help'];
            }
            if (in_array($type, ['select', 'radio', 'multicheckbox'], true)) {
                $node['options'] = $this->options($field);
            }
            if ($type === 'multicheckbox') {
                $node['multiple'] = true;
            }

            $validation = $this->formKitValidation($field, $type);
            if ($validation !== '') {
                $node['validation'] = $validation;
            }

            $schema[] = $node;
        }

        return $schema;
    }

    /**
     * Laravel rules keyed by field name — the server-side authority.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? 'text');
            if ($key === '' || ! isset(self::FORMKIT_TYPES[$type])) {
                continue;
            }

            $required = ! empty($field['required']);
            $rule = [$required ? 'required' : 'nullable'];

            switch ($type) {
                case 'email':
                    $rule[] = 'email';
                    $rule[] = 'max:255';
                    break;
                case 'number':
                    $rule[] = 'numeric';
                    break;
                case 'tel':
                    $rule[] = 'string';
                    $rule[] = 'max:50';
                    break;
                case 'date':
                    $rule[] = 'date';
                    break;
                case 'textarea':
                    $rule[] = 'string';
                    $rule[] = 'max:5000';
                    break;
                case 'select':
                case 'radio':
                    $rule[] = 'string';
                    $rule[] = 'in:'.implode(',', $this->optionValues($field));
                    break;
                case 'multicheckbox':
                    $rules[$key] = $required ? ['required', 'array'] : ['nullable', 'array'];
                    $rules[$key.'.*'] = ['string', 'in:'.implode(',', $this->optionValues($field))];

                    continue 2;
                case 'checkbox':
                    $rule[] = 'boolean';
                    break;
                default:
                    $rule[] = 'string';
                    $rule[] = 'max:1000';
            }

            $rules[$key] = $rule;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, array{value: string, label: string}>
     */
    private function options(array $field): array
    {
        $options = is_array($field['options'] ?? null) ? $field['options'] : [];

        return array_values(array_map(static fn (array $o): array => [
            'value' => (string) ($o['value'] ?? ''),
            'label' => (string) ($o['label'] ?? ($o['value'] ?? '')),
        ], array_filter($options, 'is_array')));
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private function optionValues(array $field): array
    {
        return array_map(static fn (array $o): string => $o['value'], $this->options($field));
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function formKitValidation(array $field, string $type): string
    {
        $rules = [];
        if (! empty($field['required'])) {
            $rules[] = 'required';
        }
        if ($type === 'email') {
            $rules[] = 'email';
        }

        return implode('|', $rules);
    }
}
