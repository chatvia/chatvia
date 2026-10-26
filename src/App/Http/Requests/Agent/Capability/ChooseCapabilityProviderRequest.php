<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Capability;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Picking the connector a capability works through for an agent. Whether it
 * is one the capability can use, and connected, is the action's check.
 */
class ChooseCapabilityProviderRequest extends FormRequest
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
            'provider' => ['required', 'string', 'max:64'],
        ];
    }

    public function provider(): string
    {
        return (string) $this->validated('provider');
    }
}
