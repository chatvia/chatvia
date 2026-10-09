<?php

namespace Domain\Agent\Enums;

use Domain\Agent\Models\Agent;

/**
 * The agent settings groups the Setup and Deploy pages save through the
 * generic `settings/{group}` endpoint. Groups with stricter rules — the chat
 * rate limit (`security`), the guardrails, the model — have their own
 * endpoints and permissions, so they are not listed here. Each group's keys
 * and defaults come from `model-settings.defaults`, its rules from
 * `model-settings.validation`.
 */
enum AgentSettingsGroup: string
{
    case Appearance = 'appearance';
    case Surfaces = 'surfaces';
    case Widget = 'widget';
    case Embed = 'embed';
    case Bar = 'bar';
    case Analytics = 'analytics';

    /**
     * The validation rules of the group's keys.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return (array) config('model-settings.validation.'.$this->value, []);
    }

    /**
     * The group's setting keys.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys((array) config('model-settings.defaults.'.Agent::class.'.'.$this->value, []));
    }
}
