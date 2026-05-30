<?php

namespace App\Http\Controllers\Chat\Models;

use Domain\Agents\Models\Agent;

class ChatModelsListController
{
    public function __invoke(Agent $agent): array
    {
        $models = current_agent()?->llmsFormatted();

        return [
            'models' => $models,
            'default' => $models[0] ?? null,
        ];
    }
}
