<?php

namespace Domain\Agent\Concerns;

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

/**
 * Grouped key/value settings for a model (an agent, a user), stored as
 * {@see ModelSetting} rows. Reads lay the stored values over the defaults in
 * `config('model-settings.defaults.<model class>.<group>')`, so only values
 * that differ from a default need a row; a group without defaults is invalid.
 *
 * Resolved groups are cached per model instance and dropped on every write to
 * that group. An uploaded image is resized to 128px high, stored as a PNG on
 * the public `assets` disk under the owner's prefix, and read back as its URL.
 *
 * The using model must provide `storageRoot()` and an `external_id`.
 */
trait HasSettings
{
    /** @var array<string, Collection<string, mixed>> Per-instance, per-group cache for settings(). */
    private array $resolvedSettingsCache = [];

    /**
     * The group's settings: stored values over the config defaults.
     *
     * @return Collection<string, mixed>
     *
     * @throws InvalidArgumentException when the group has no defaults
     */
    public function settings(string $group): Collection
    {
        if (isset($this->resolvedSettingsCache[$group])) {
            return $this->resolvedSettingsCache[$group];
        }

        $modelDefaultSettings = $this->settingDefaults($group);

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

        return $this->resolvedSettingsCache[$group] = collect($modelDefaultSettings)
            ->merge($this->storedValues($modelSettings));
    }

    /**
     * Warm the per-instance settings cache for multiple groups in one query.
     * Widget/embed paths read 2–4 groups per request — separate `settings()`
     * calls would issue one SELECT each. Groups without defaults are skipped.
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
            $defaults = $this->settingDefaults($group);

            if (is_null($defaults)) {
                continue;
            }

            $items = $rows->get($group, collect());

            $this->resolvedSettingsCache[$group] = $items->isEmpty()
                ? collect($defaults)
                : collect($defaults)->merge($this->storedValues($items));
        }
    }

    /**
     * Store one setting. An uploaded image replaces the group's previous file
     * only once the new one is safely stored.
     */
    public function setSetting(string $key, mixed $value, string $group): void
    {
        $dataType = $this->settingType($value);

        if ($value instanceof UploadedFile) {
            $value = $this->storeSettingImage($key, $value, $group);
        }

        if ($dataType === 'array') {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }

        // One INSERT … ON CONFLICT on the (model, group, key) unique key: two
        // saves at once cannot both insert, and there is no read first.
        $this->modelSettings()->upsert([
            'group' => $group,
            'key' => $key,
            'value' => $value,
            'data_type' => $dataType,
        ], ['model_type', 'model_id', 'group', 'key'], ['value', 'data_type']);

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

    /**
     * Remove stored settings so the group falls back to their defaults.
     *
     * @param  array<int, string>|string  $keys
     */
    public function settingsDelete(string $group, array|string $keys): void
    {
        $this->modelSettings()
            ->where('group', $group)
            ->whereIn('key', is_string($keys) ? [$keys] : $keys)
            ->delete();

        unset($this->resolvedSettingsCache[$group]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function settingDefaults(string $group): ?array
    {
        return config('model-settings.defaults.'.$this::class.'.'.$group);
    }

    /**
     * Stored rows as `key => value`, each value cast back to its saved type.
     *
     * @param  Collection<int, ModelSetting>  $rows
     * @return Collection<string, mixed>
     */
    private function storedValues(Collection $rows): Collection
    {
        return $rows->mapWithKeys(fn (ModelSetting $item): array => [$item->key => $this->castSettingValue($item)]);
    }

    private function castSettingValue(ModelSetting $item): mixed
    {
        return match ($item->data_type) {
            'string' => (string) $item->value,
            'integer' => (int) $item->value,
            'file' => Storage::disk('assets')->url($item->value),
            'boolean' => (bool) $item->value,
            'array' => json_decode($item->value, true, 512, JSON_THROW_ON_ERROR),
            default => $item->value,
        };
    }

    /**
     * Mirror ProfilePhotoStorage: process and upload first, and only let the
     * new path reach the database once the upload succeeds — a failed upload
     * throws and leaves the existing image untouched. Brand assets (widget
     * logo/avatar) are public + CDN-served, so they live on the `assets` disk
     * and read back via its pull-zone URL.
     *
     * @return string the stored path
     */
    private function storeSettingImage(string $key, UploadedFile $image, string $group): string
    {
        $disk = Storage::disk('assets');
        $previousPath = $this->modelSettings()->where('key', $key)->where('group', $group)->value('value');

        $data = Image::decodeBinary($image->getContent())->scale(height: 128)->encode(new PngEncoder)->toString();
        $file = new File(create_temp_file($image->getFilename(), $data));

        $uploadedPath = $disk->putFile($this->storageRoot().'/settings/'.$group.'/'.$this->external_id, $file);

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

        return $uploadedPath;
    }

    private function settingType(mixed $setting): string
    {
        if ($setting instanceof UploadedFile) {
            return 'file';
        }

        return gettype($setting);
    }
}
