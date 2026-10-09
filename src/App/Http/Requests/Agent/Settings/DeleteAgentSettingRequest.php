<?php

namespace App\Http\Requests\Agent\Settings;

use Domain\Agent\Enums\AgentSettingsGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Resets one setting of an agent settings group to its default by deleting
 * the stored value. Only a key that belongs to the group may be named — never
 * an arbitrary key. Authorization is the route's `agent.permission:agent.setup`
 * middleware.
 */
class DeleteAgentSettingRequest extends FormRequest
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
            'key' => ['required', 'string', Rule::in($this->group()->keys())],
        ];
    }

    public function key(): string
    {
        return $this->string('key')->toString();
    }

    /** The route's `{group}`, already bound to the enum (an unknown group is a 404). */
    public function group(): AgentSettingsGroup
    {
        $group = $this->route('group');

        return $group instanceof AgentSettingsGroup ? $group : AgentSettingsGroup::from((string) $group);
    }
}
