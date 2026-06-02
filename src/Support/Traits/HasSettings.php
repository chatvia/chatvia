<?php

namespace Support\Traits;

use Domain\Agent\Models\ModelSetting;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;

trait HasSettings
{
    /** Per-instance, per-group cache for settings(). */
    private array $resolvedSettingsCache = [];

    public function settings(string $group): Collection
    {
        if (isset($this->resolvedSettingsCache[$group])) {
            return $this->resolvedSettingsCache[$group];
        }

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
            return $this->resolvedSettingsCache[$group] = collect($modelDefaultSettings);
        }

        $savedSettings = $modelSettings->mapWithKeys(function ($item) {
            $value = match ($item->data_type) {
                'string' => (string) $item->value,
                'integer' => (int) $item->value,
                'file' => Storage::url($item->value),
                'boolean' => (bool) $item->value,
                'array' => json_decode($item->value, true, 512, JSON_THROW_ON_ERROR),
                default => $item->value,
            };

            return [$item->key => $value];
        });

        return $this->resolvedSettingsCache[$group] = collect($modelDefaultSettings)->merge($savedSettings);
    }

    /**
     * Warm the per-instance settings cache for multiple groups in one query.
     * Widget/embed paths read 2–4 groups per request — separate `settings()`
     * calls would issue one SELECT each.
     *
     * @param  array<int, string>  $groups
     */
    public function loadSettingsGroups(array $groups): void
    {
        if ($groups === []) {
            return;
        }

        $rows = $this->modelSettings()
            ->select(['data_type', 'key', 'value', 'group'])
            ->whereIn('group', $groups)
            ->get()
            ->groupBy('group');

        foreach ($groups as $group) {
            $defaults = config('model-settings.defaults.'.$this::class.'.'.$group);

            if (is_null($defaults)) {
                continue;
            }

            $items = $rows->get($group, collect());

            if ($items->isEmpty()) {
                $this->resolvedSettingsCache[$group] = collect($defaults);

                continue;
            }

            $saved = $items->mapWithKeys(function ($item) {
                $value = match ($item->data_type) {
                    'string' => (string) $item->value,
                    'integer' => (int) $item->value,
                    'file' => Storage::url($item->value),
                    'boolean' => (bool) $item->value,
                    'array' => json_decode($item->value, true, 512, JSON_THROW_ON_ERROR),
                    default => $item->value,
                };

                return [$item->key => $value];
            });

            $this->resolvedSettingsCache[$group] = collect($defaults)->merge($saved);
        }
    }

    public function setSetting(string $key, mixed $value, string $group): void
    {
        $dataType = $this->settingType($value);

        if ($value instanceof UploadedFile) {
            $data = Image::read($value->getContent())->scale(height: 128)->toPng()->toString();
            $file = new File(create_temp_file($value->getFilename(), $data));
            $value = Storage::putFile('users/'.$this->getUserId().'/settings/'.$group.'/'.$this->id, $file, ['visibility' => 'public']);
        }

        if ($dataType === 'array') {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }

        $this->modelSettings()->updateOrCreate([
            'key' => $key,
            'group' => $group,
        ], [
            'value' => $value,
            'data_type' => $dataType,
        ]);

        // Invalidate the per-instance cache for this group.
        unset($this->resolvedSettingsCache[$group]);
    }

    /**
     * @return MorphMany<ModelSetting, $this>
     */
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

        unset($this->resolvedSettingsCache[$group]);
    }
}
