<?php

namespace App\Support;

class Models
{
    public function getAllModels()
    {
        return collect(config('llms'));
    }

    public function getModelByExternalId(string $modelExternalId, ?string $key = null)
    {
        $models = collect(config('llms'))
            ->where('external_id', $modelExternalId)
            ->first();

        if ($key) {
            return $models[$key] ?? null;
        }

        return $models;
    }

    public function getAllModelExternalIds()
    {
        return $this->getAllModels()->pluck('external_id');
    }
}
