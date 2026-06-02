<?php

namespace App\Http\Requests\Agent\Connector;

use Illuminate\Foundation\Http\FormRequest;

class AgentConnectorConnectRequest extends FormRequest
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
            // Token is optional — none-auth connectors don't send one.
            // Validated server-side per connector strategy.
            'token' => ['nullable', 'string', 'min:4', 'max:4096'],
        ];
    }
}
