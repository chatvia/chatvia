<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Crud;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the agent's answer temperature (0 to 2), the only model setting
 * owners can change from Setup. Agent access and the `agent.setup` permission
 * are checked by the route middleware.
 */
class UpdateAgentTemperatureRequest extends FormRequest
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
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
        ];
    }
}
