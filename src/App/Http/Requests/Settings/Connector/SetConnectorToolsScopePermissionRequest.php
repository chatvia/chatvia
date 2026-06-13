<?php

namespace App\Http\Requests\Settings\Connector;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetConnectorToolsScopePermissionRequest extends FormRequest
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
            'scope' => ['required', Rule::enum(McpToolScope::class)],
            'permission' => ['required', Rule::enum(McpToolPermission::class)],
        ];
    }

    public function scope(): McpToolScope
    {
        return McpToolScope::from($this->string('scope')->toString());
    }

    public function permission(): McpToolPermission
    {
        return McpToolPermission::from($this->string('permission')->toString());
    }
}
