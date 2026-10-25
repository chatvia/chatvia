<?php

declare(strict_types=1);

namespace App\Http\Requests\Widget;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * One widget message before its answer streams. A message longer than the
 * admin's limit is refused before any model runs. The session, conversation
 * and viewer are checked by the controller. The widget may not ask for JSON,
 * so a refusal is always a JSON 422, never a redirect.
 */
class WidgetStreamRequest extends FormRequest
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
        return [
            'content' => ['nullable', 'string', 'max:'.config('chatvia.guardrails.max_message_chars')],
            // The model the chatter picked; one the agent does not offer falls back to its own.
            'model_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.max' => __('Your message is too long. Please keep it under :max characters.'),
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY));
    }
}
