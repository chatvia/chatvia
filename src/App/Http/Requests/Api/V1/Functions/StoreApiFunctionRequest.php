<?php

namespace App\Http\Requests\Api\V1\Functions;

use Domain\Agents\Data\ApiFunctionPayloadData;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiFunctionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'description' => ['required', 'string', 'max:1024'],
            'parameters_schema' => ['required', 'array'],
            'webhook_url' => ['required', 'url', 'max:2048'],
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
