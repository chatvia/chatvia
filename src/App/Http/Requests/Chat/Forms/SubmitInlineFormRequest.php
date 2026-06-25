<?php

namespace App\Http\Requests\Chat\Forms;

use Domain\Form\Models\FormSubmission;
use Domain\Form\Services\FormSchemaBuilder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the chat + widget submit controllers. Builds its field rules
 * dynamically from the bound submission's form definition, so client and server
 * validate against the same schema. Binding/auth is enforced in the controller.
 */
class SubmitInlineFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'fields' => ['required', 'array'],
            'request_id' => ['nullable', 'string', 'max:100'],
            'assistant_message_id' => ['nullable', 'string', 'max:64'],
        ];

        $submission = $this->route('formInstance');
        if ($submission instanceof FormSubmission && $submission->definition !== null) {
            foreach (app(FormSchemaBuilder::class)->validationRules($submission->definition->schema) as $key => $rule) {
                $rules['fields.'.$key] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldValues(): array
    {
        $fields = $this->validated()['fields'] ?? [];

        return is_array($fields) ? $fields : [];
    }

    public function requestId(): ?string
    {
        return $this->input('request_id');
    }

    public function assistantMessageId(): ?string
    {
        return $this->input('assistant_message_id');
    }
}
