<?php

namespace App\Http\Controllers\Chat;

use App\Models\Agent;

class ChatModelsListController
{
    public function __invoke(Agent $agent)
    {
        $models = current_agent()?->llmsFormatted();

        return [
            'models' => $models,
            'default' => $models[0] ?? null,
        ];
    }
}
