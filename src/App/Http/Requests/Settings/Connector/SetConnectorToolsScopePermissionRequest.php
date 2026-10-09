<?php

namespace App\Http\Requests\Settings\Connector;

use Domain\Integration\Mcp\Data\McpScopePermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Set the same permission on every tool in one scope (read or write) of a
 * connector.
 */
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

    public function toData(): McpScopePermissionData
    {
        return new McpScopePermissionData(
            scope: McpToolScope::from($this->string('scope')->toString()),
            permission: McpToolPermission::from($this->string('permission')->toString()),
        );
    }
}
