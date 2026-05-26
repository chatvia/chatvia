<?php

namespace App\Http\Requests\Settings\Connectors;

use Illuminate\Foundation\Http\FormRequest;

class AddCustomConnectorRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:64'],
            'url' => ['required', 'url', 'max:2048'],
            'auth_strategy' => ['required', 'in:oauth,token,none'],
            'token' => ['sometimes', 'nullable', 'string', 'max:8192'],
        ];
    }
}
