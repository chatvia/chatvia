<?php

namespace App\Http\Controllers\Chat;

class ChatModelsListController
{
    public function __invoke()
    {
        $models = current_agent()?->llmsFormatted();

        return [
            'models' => $models,
            'default' => $models[0],
        ];
    }
}
