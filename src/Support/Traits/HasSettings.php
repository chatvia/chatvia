<?php

namespace Support\Traits;

use Domain\Agent\Models\ModelSetting;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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
                'file' => Storage::disk('assets')->url($item->value),
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
                    'file' => Storage::disk('assets')->url($item->value),
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
            // Mirror ProfilePhotoStorage: process and upload first, and only let
            // the new path reach the database once the upload succeeds — a failed
            // upload throws and leaves the existing image untouched. Brand assets
            // (widget logo/avatar) are public + CDN-served, so they live on the
            // `assets` disk and read back via its pull-zone URL.
            $disk = Storage::disk('assets');
            $previousPath = $this->modelSettings()->where('key', $key)->where('group', $group)->value('value');

            $data = Image::decodeBinary($value->getContent())->scale(height: 128)->encode(new PngEncoder)->toString();
            $file = new File(create_temp_file($value->getFilename(), $data));

            $uploadedPath = $disk->putFile('users/'.$this->getUserExternalId().'/settings/'.$group.'/'.$this->external_id, $file);

            // putFile returns false on failure when a disk doesn't throw — never
            // overwrite the saved value with a non-path. (The assets disk has
            // throw => true, so this is defence in depth.)
            if (! is_string($uploadedPath)) {
                throw new RuntimeException("Failed to upload the '{$key}' image to the assets disk.");
            }

            // Delete the replaced file only after the new one is safely stored,
            // best-effort: a stale CDN object must not fail an otherwise-good save.
            if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $uploadedPath) {
                try {
                    $disk->delete($previousPath);
                } catch (Throwable $e) {
                    Log::warning('Setting image: failed to delete the replaced file on the assets disk.', [
                        'path' => $previousPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $value = $uploadedPath;
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
