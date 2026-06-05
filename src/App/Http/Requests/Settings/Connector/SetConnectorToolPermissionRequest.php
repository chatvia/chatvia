<?php

namespace App\Http\Requests\Settings\Connector;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetConnectorToolPermissionRequest extends FormRequest
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
            'permission' => ['required', Rule::enum(McpToolPermission::class)],
        ];
    }

    public function permission(): McpToolPermission
    {
        return McpToolPermission::from($this->string('permission')->toString());
    }
}
