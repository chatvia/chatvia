<?php

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Data\Settings\BusinessProfileData;
use Domain\Agent\Models\Agent;

class UpdateAgentBusinessProfileAction
{
    public function execute(Agent $agent, BusinessProfileData $data): void
    {
        // The model_settings.value column is NOT NULL, so a cleared field has to
        // be removed rather than stored as null. settings() merges defaults on
        // read, so a missing row reads back as null anyway.
        $values = [
            'email' => $data->email,
            'phone' => $data->phone,
            'website' => $data->website,
            'address' => $data->address,
            'opening_hours' => $data->opening_hours,
        ];

        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                $agent->settingsDelete('business', $key);

                continue;
            }

            $agent->setSetting($key, $value, 'business');
        }
    }
}
