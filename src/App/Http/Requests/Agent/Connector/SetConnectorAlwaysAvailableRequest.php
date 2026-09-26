<?php

namespace App\Http\Requests\Agent\Connector;

use Domain\Integration\Mcp\Data\Agent\SetConnectorAlwaysAvailableData;
use Illuminate\Foundation\Http\FormRequest;

class SetConnectorAlwaysAvailableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agent')) ?? false;
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
