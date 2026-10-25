<?php

declare(strict_types=1);

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates one hosted-chat message before its answer streams. A message
 * longer than the admin's limit is refused before any model runs.
 */
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
            'message' => ['required', 'string', 'max:'.config('chatvia.guardrails.max_message_chars')],
            'updated_message_info' => ['nullable', 'array'],
            'request_id' => ['nullable', 'string', 'uuid'],
            // The model the chatter picked; one the agent does not offer falls back to its own.
            'model_id' => ['nullable', 'integer'],
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
            'message.required' => __('Please enter a message'),
            'message.max' => __('Your message is too long. Please keep it under :max characters.'),
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
            'message' => __('chat message'),
            'updated_message_info' => __('message update information'),
        ];
    }
}
