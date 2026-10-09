<?php

namespace App\Http\Requests\Agent\Connector;

use Domain\Integration\Mcp\Data\Agent\SetConnectorAlwaysAvailableData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Turn "always on" on or off for a connector attached to an agent.
 */
class SetConnectorAlwaysAvailableRequest extends FormRequest
{
    /**
     * The `can.access.agent` + `agent.permission:…` route middleware gate access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'always_available' => ['required', 'boolean'],
        ];
    }

    public function toData(): SetConnectorAlwaysAvailableData
    {
        return new SetConnectorAlwaysAvailableData(
            always_available: $this->boolean('always_available'),
        );
    }
}
