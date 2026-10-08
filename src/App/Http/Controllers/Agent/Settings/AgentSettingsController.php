<?php

namespace App\Http\Controllers\Agent\Settings;

use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentSettingsController
{
    public function update(
        Request $request,
        Agent $agent,
        string $group,
    ): void {
        $request->validate($this->rulesFor($group));

        $settings = $this->keysOf($group);

        $values = array_filter(
            $request->only($settings),
            fn ($value): bool => $value !== '' && $value !== null,
        );

        foreach ($values as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: $group);
        }
    }

    public function destroy(
        Request $request,
        Agent $agent,
        string $group,
    ): void {
        // 404 for a group this endpoint does not manage.
        $this->rulesFor($group);

        // Only a setting that belongs to this group may be deleted — never an
        // arbitrary key from the input.
        $key = $request->validate([
            'key' => ['required', 'string', Rule::in($this->keysOf($group))],
        ])['key'];

        $agent->settingsDelete($group, $key);
    }

    /**
     * The validation rules of a group this endpoint may write. Only groups
     * that have both defaults and rules are open here; any other group (for
     * example one saved by its own endpoint) is a 404.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $group): array
    {
        $rules = config('model-settings.validation.'.$group);

        abort_unless(is_array($rules) && $this->keysOf($group) !== [], 404);

        return $rules;
    }

    /**
     * @return list<string>
     */
    private function keysOf(string $group): array
    {
        $defaults = config('model-settings.defaults.'.Agent::class.'.'.$group);

        return is_array($defaults) ? array_keys($defaults) : [];
    }
}
