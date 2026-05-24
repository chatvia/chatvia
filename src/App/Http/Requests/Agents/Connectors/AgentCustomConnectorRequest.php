<?php

namespace App\Http\Requests\Agents\Connectors;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentCustomConnectorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64'],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'auth_strategy' => ['required', Rule::in(['none', 'token', 'oauth'])],
            'token' => ['nullable', 'string', 'min:4', 'max:4096', 'required_if:auth_strategy,token'],
        ];
    }
}
