<?php

namespace App\Http\Requests\Api\V1\Function;

use Domain\Integration\Function\Data\ApiFunctionPayloadData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApiFunctionRequest extends FormRequest
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
            'internal_name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1024'],
            'parameters_schema' => ['sometimes', 'array'],
            'webhook_url' => ['sometimes', 'url', 'max:2048'],
            'webhook_method' => ['sometimes', 'in:POST,GET,PATCH,DELETE'],
            'webhook_headers' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'in:none,bearer,header,hmac'],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            'auth_value' => ['nullable', 'required_if:auth_type,bearer,header,hmac', 'string', 'max:8192'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): ApiFunctionPayloadData
    {
        return ApiFunctionPayloadData::from($this->validated());
    }
}
