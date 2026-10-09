<?php

namespace App\Http\Requests\Agent\Settings;

use Domain\Agent\Enums\AgentSettingsGroup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saves some keys of one agent settings group from the Setup or Deploy page.
 * Only the group's own keys are kept, and blank values are skipped (a blank
 * field means "leave as is"). Authorization is the route's
 * `agent.permission:agent.setup` middleware.
 */
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
        return $this->group()->rules();
    }

    /**
     * The submitted values to store, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return array_filter(
            $this->only($this->group()->keys()),
            static fn (mixed $value): bool => $value !== '' && $value !== null,
        );
    }

    /** The route's `{group}`, already bound to the enum (an unknown group is a 404). */
    public function group(): AgentSettingsGroup
    {
        $group = $this->route('group');

        return $group instanceof AgentSettingsGroup ? $group : AgentSettingsGroup::from((string) $group);
    }
}
