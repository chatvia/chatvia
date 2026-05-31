<?php

namespace App\Http\Requests\Api\V1\Agents;

use Domain\Agents\Data\UpdateAgentSettingsData;
use Domain\Agents\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateAgentSettingsRequest extends FormRequest
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
        $group = (string) $this->route('group');
        $rules = config('model-settings.validation.'.$group);

        if (! is_array($rules)) {
            throw ValidationException::withMessages([
                'group' => "Settings group '$group' is not exposed in the API.",
            ]);
        }

        $this->ensureKnownGroup($group);

        return $rules;
    }

    public function toData(Agent $agent): UpdateAgentSettingsData
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->validated();

        return new UpdateAgentSettingsData(
            agent: $agent,
            group: (string) $this->route('group'),
            payload: $payload,
        );
    }

    private function ensureKnownGroup(string $group): void
    {
        $groups = array_keys(config('model-settings.defaults.'.Agent::class, []));

        if (! in_array($group, $groups, true)) {
            throw ValidationException::withMessages([
                'group' => "Unknown settings group '$group'. Allowed: ".implode(', ', $groups),
            ]);
        }
    }
}
