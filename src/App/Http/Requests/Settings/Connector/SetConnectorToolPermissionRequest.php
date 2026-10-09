<?php

namespace App\Http\Requests\Settings\Connector;

use Domain\Integration\Mcp\Data\McpToolPermissionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Set one connector tool's permission (allow / approval / blocked).
 */
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

    /**
     * The tool (the `{tool}` route id) and the permission to give it.
     */
    public function toData(): McpToolPermissionData
    {
        return new McpToolPermissionData(
            id: (int) $this->route('tool'),
            permission: McpToolPermission::from($this->string('permission')->toString()),
        );
    }
}
