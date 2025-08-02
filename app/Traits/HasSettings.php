<?php

namespace App\Traits;

use App\Models\ModelSetting;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;

trait HasSettings
{
    public function settings(string $group): Collection
    {
        $modelDefaultSettings = config('model-settings.defaults.'.$this::class.'.'.$group);

        if (is_null($modelDefaultSettings)) {
            throw new InvalidArgumentException('Invalid group for settings');
        }

        $modelSettings = $this
            ->modelSettings()
            ->select(['data_type', 'key', 'value'])
            ->where('group', $group)
            ->get()
            ->collect();

        if ($modelSettings->isEmpty()) {
            return collect($modelDefaultSettings);
        }

        return $modelSettings->mapWithKeys(function ($item) {
            $value = match ($item->data_type) {
                'string' => (string) $item->value,
                'integer' => (int) $item->value,
                'file' => Storage::url($item->value),
                'boolean' => (bool) $item->value,
                default => $item->value,
            };

            return [$item->key => $value];
        });
    }

    public function setSetting(string $key, mixed $value, string $group): void
    {
        $dataType = $this->settingType($value);

        if ($value instanceof UploadedFile) {
            // Resize image
            $data = Image::read($value->getContent())->scale(height: 128)->toPng()->toString();
            $file = new File(create_temp_file($value->getFilename(), $data));
            $value = Storage::putFile('settings/'.$this->id.'/'.$group, $file, ['visibility' => 'public']);
        }

        $this->modelSettings()->updateOrCreate([
            'key' => $key,
            'group' => $group,
        ], [
            'value' => $value,
            'data_type' => $dataType,
        ]);
    }

    public function modelSettings(): MorphMany
    {
        return $this->morphMany(ModelSetting::class, 'model');
    }

    private function settingType($setting): string
    {
        if ($setting instanceof UploadedFile) {
            return 'file';
        }

        return gettype($setting);
    }

    public function settingsDelete(string $group, array|string $keys): void
    {
        $this->modelSettings()
            ->where('group', $group)
            ->whereIn('key', is_string($keys) ? [$keys] : $keys)
            ->delete();
    }
}
