<?php

namespace App\Http\Requests\Api\V1\Source;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a text added as a knowledge source through the public API. Access
 * to the agent is checked by the route middleware.
 */
class StoreSourceTextRequest extends FormRequest
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
            'content' => ['required', 'string', 'min:500'],
        ];
    }

    public function textContent(): string
    {
        return (string) $this->validated('content');
    }
}
