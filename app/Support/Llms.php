<?php

namespace App\Support;

use App\Data\Chat\LlmModelData;
use App\Models\Llm;

class Llms
{
    public function all()
    {
        return Llm::all();
    }

    public function getByExternalName(string $modelExternalId): LlmModelData
    {
        logger()?->error($modelExternalId);
        return LlmModelData::fromLlmModel(Llm::query()->where('external_name', $modelExternalId)->firstOrFail());
    }
}
