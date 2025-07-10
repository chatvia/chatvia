<?php

namespace App\Traits;

use App\Models\ModelSetting;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasSettings
{
    public function settings(string $group)
    {
        $modelDefaultSettings = config('model-settings.defaults.'.$this::class.'.'.$group);

        $modelSettings = $this->modelSettings()->where('group', $group)->get()
            ->pluck('value', 'key')
            ->toArray();

        return collect($modelDefaultSettings)->mapWithKeys(function ($value, $key) use ($modelSettings) {
            return [$key => array_key_exists($key, $modelSettings) ? $modelSettings[$key] : $value];
        });
    }

    public function setSetting(string $key, mixed $value, string $group): void
    {
        $this->modelSettings()->updateOrCreate([
            'key' => $key,
            'group' => $group,
        ], [
            'value' => $value,
        ]);
    }

    public function modelSettings(): MorphMany
    {
        return $this->morphMany(ModelSetting::class, 'model');
    }
}
