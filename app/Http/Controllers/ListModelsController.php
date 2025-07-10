<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ListModelsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return collect(config('llms'))->map(function ($model) use (&$i) {
            return [
                'id' => $model['external_id'],
                'name' => $model['name'],
                'context' => $model['context'],
                'icon' => $model['icon'],
                'description' => $model['description'],
                'features' => $model['features'] ?? [],
            ];
        });
    }
}
