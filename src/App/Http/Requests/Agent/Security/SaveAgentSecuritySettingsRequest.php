<?php

namespace App\Http\Requests\Agent\Security;

use Domain\Agent\Data\Settings\AgentSecuritySettingsData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the per-agent chat rate-limit form on the Security page. The route
 * already enforces `agent.permission:agent.setup`, so authorize() defers to it.
 */
class SaveAgentSecuritySettingsRequest extends FormRequest
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
            'chat_rate_limit_per_minute' => ['required', 'integer', 'min:1', 'max:1000'],
            'notify_owner_on_rate_limit' => ['required', 'boolean'],
        ];
    }

    public function toData(): AgentSecuritySettingsData
    {
        return new AgentSecuritySettingsData(
            chat_rate_limit_per_minute: $this->integer('chat_rate_limit_per_minute'),
            notify_owner_on_rate_limit: $this->boolean('notify_owner_on_rate_limit'),
        );
    }
}
