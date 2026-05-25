<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChatStreamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'model' => ['required', 'string', 'exists:llms,external_name'],
            'message' => ['required', 'string'],
            'updated_message_info' => ['nullable', 'array'],
            'request_id' => ['nullable', 'string', 'uuid'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'model.required' => 'Please select a model',
            'model.exists' => 'The selected model is not available',
            'message.required' => 'Please enter a message',
            'agent.required_if' => 'Agent is required when using playground mode',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'model' => 'AI model',
            'message' => 'chat message',
            'updated_message_info' => 'message update information',
        ];
    }
}
