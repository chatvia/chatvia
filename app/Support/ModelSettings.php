<?php

namespace App\Support;

use App\Models\ModelSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ModelSettings
{
    protected Model $model;

    protected string $cachePrefix = 'model_settings';

    protected int $cacheTtl = 3600; // 1 hour

    protected bool $observerCacheClearing = true;

    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->cachePrefix = config('model-settings.cache.prefix', 'model_settings');
        $this->cacheTtl = config('model-settings.cache.ttl', 3600);
    }

    /**
     * Get a setting value with optional default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->getCacheKey($key);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($key, $default) {
            $setting = $this->model->modelSettings()->where('key', $key)->first();

            if ($setting) {
                return $setting->getValue();
            }

            // Check for configured defaults
            $configDefault = $this->getConfigDefault($key);
            if ($configDefault !== null) {
                return $configDefault;
            }

            // Return provided default
            return $default;
        });
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value): self
    {
        $this->model->modelSettings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // Observer will handle cache clearing, but we can do it manually if observer is disabled
        if (! $this->observerCacheClearing) {
            $this->forgetCache($key);
        }

        return $this;
    }

    /**
     * Set multiple settings at once.
     */
    public function setMultiple(array $settings): self
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Get all settings for this model.
     */
    public function all(): Collection
    {
        $cacheKey = $this->getAllCacheKey();

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            $dbSettings = $this->model->modelSettings()->pluck('value', 'key');
            $configDefaults = $this->getConfigDefaults();

            // Merge config defaults with database settings, giving priority to database settings
            return collect($configDefaults)->merge($dbSettings);
        });
    }

    /**
     * Check if a setting exists (either in database or config defaults).
     */
    public function has(string $key): bool
    {
        // Check if setting exists in database
        $dbSetting = $this->model->modelSettings()->where('key', $key)->exists();
        if ($dbSetting) {
            return true;
        }

        // Check if setting exists in config defaults
        return $this->getConfigDefault($key) !== null;
    }

    /**
     * Remove a setting.
     */
    public function forget(string $key): self
    {
        $this->model->modelSettings()->where('key', $key)->delete();

        // Observer will handle cache clearing, but we can do it manually if observer is disabled
        if (! $this->observerCacheClearing) {
            $this->forgetCache($key);
        }

        return $this;
    }

    /**
     * Remove all settings for this model.
     */
    public function flush(): self
    {
        $this->model->modelSettings()->delete();

        // Observer will handle cache clearing, but we can do it manually if observer is disabled
        if (! $this->observerCacheClearing) {
            $this->forgetAllCache();
        }

        return $this;
    }

    /**
     * Get settings as an associative array.
     */
    public function toArray(): array
    {
        return $this->all()->toArray();
    }

    /**
     * Get cache key for a specific setting.
     */
    protected function getCacheKey(string $key): string
    {
        return sprintf(
            '%s.%s.%s.%s',
            $this->cachePrefix,
            get_class($this->model),
            $this->model->getKey(),
            $key
        );
    }

    /**
     * Get cache key for all settings.
     */
    protected function getAllCacheKey(): string
    {
        return sprintf(
            '%s.%s.%s.all',
            $this->cachePrefix,
            get_class($this->model),
            $this->model->getKey()
        );
    }

    /**
     * Clear cache for a specific setting.
     */
    protected function forgetCache(string $key): void
    {
        Cache::forget($this->getCacheKey($key));
        Cache::forget($this->getAllCacheKey());
    }

    /**
     * Clear all cache for this model.
     */
    protected function forgetAllCache(): void
    {
        // Clear all cache for this model instance
        $pattern = sprintf(
            '%s.%s.%s.*',
            $this->cachePrefix,
            get_class($this->model),
            $this->model->getKey()
        );

        // Note: This is a simple implementation. For production with Redis,
        // you might want to use a more sophisticated cache tagging approach.
        Cache::forget($this->getAllCacheKey());
    }

    /**
     * Set cache TTL for this instance.
     */
    public function cacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;

        return $this;
    }

    /**
     * Get the underlying model settings relationship.
     */
    public function relationship()
    {
        return $this->model->modelSettings();
    }

    /**
     * Get settings with eager loading for better performance.
     */
    public function allEager(): Collection
    {
        $cacheKey = $this->getAllCacheKey();

        return Cache::remember($cacheKey, $this->cacheTtl, function () {
            // Load the model with its settings if not already loaded
            if (! $this->model->relationLoaded('modelSettings')) {
                $this->model->load('modelSettings');
            }

            $dbSettings = $this->model->modelSettings->pluck('value', 'key');
            $configDefaults = $this->getConfigDefaults();

            // Merge config defaults with database settings, giving priority to database settings
            return collect($configDefaults)->merge($dbSettings);
        });
    }

    /**
     * Bulk set settings (more efficient for multiple settings).
     * Temporarily disables observer cache clearing to avoid redundant cache clears.
     */
    public function bulkSet(array $settings): self
    {
        // Disable observer cache clearing for bulk operations
        $originalObserverSetting = $this->observerCacheClearing;
        $this->observerCacheClearing = false;

        try {
            $existingSettings = $this->model->modelSettings()->pluck('id', 'key')->toArray();
            $updateData = [];
            $insertData = [];

            foreach ($settings as $key => $value) {
                if (isset($existingSettings[$key])) {
                    $updateData[] = [
                        'id' => $existingSettings[$key],
                        'value' => $value,
                        'updated_at' => now(),
                    ];
                } else {
                    $insertData[] = [
                        'model_type' => get_class($this->model),
                        'model_id' => $this->model->getKey(),
                        'key' => $key,
                        'value' => json_encode($value),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Batch updates
            if (! empty($updateData)) {
                foreach ($updateData as $data) {
                    ModelSetting::where('id', $data['id'])->update([
                        'value' => $data['value'],
                        'updated_at' => $data['updated_at'],
                    ]);
                }
            }

            // Batch inserts
            if (! empty($insertData)) {
                ModelSetting::insert($insertData);
            }

            // Clear cache once for all changes
            $this->forgetAllCache();

        } finally {
            // Restore original observer setting
            $this->observerCacheClearing = $originalObserverSetting;
        }

        return $this;
    }

    /**
     * Disable observer cache clearing (useful for bulk operations).
     */
    public function withoutObserverCacheClearing(): self
    {
        $this->observerCacheClearing = false;

        return $this;
    }

    /**
     * Enable observer cache clearing.
     */
    public function withObserverCacheClearing(): self
    {
        $this->observerCacheClearing = true;

        return $this;
    }

    /**
     * Manually clear cache for this model's settings.
     */
    public function clearCache(): self
    {
        $this->forgetAllCache();

        return $this;
    }

    /**
     * Get default value for a specific key from config.
     */
    protected function getConfigDefault(string $key): mixed
    {
        $modelClass = get_class($this->model);
        $defaults = config('model-settings.defaults', []);

        return $defaults[$modelClass][$key] ?? null;
    }

    /**
     * Get all default settings for this model from config.
     */
    protected function getConfigDefaults(): array
    {
        $modelClass = get_class($this->model);
        $defaults = config('model-settings.defaults', []);

        return $defaults[$modelClass] ?? [];
    }

    /**
     * Get only database settings (excluding config defaults).
     */
    public function getDatabaseSettings(): Collection
    {
        return $this->model->modelSettings()->pluck('value', 'key');
    }

    /**
     * Get only config default settings for this model.
     */
    public function getDefaultSettings(): array
    {
        return $this->getConfigDefaults();
    }

    /**
     * Check if a setting is using the default value from config.
     */
    public function isUsingDefault(string $key): bool
    {
        $dbSetting = $this->model->modelSettings()->where('key', $key)->exists();

        return ! $dbSetting && $this->getConfigDefault($key) !== null;
    }

    /**
     * Reset a setting to its config default (removes database setting).
     */
    public function resetToDefault(string $key): self
    {
        $this->forget($key);

        return $this;
    }

    /**
     * Reset all settings to config defaults (removes all database settings).
     */
    public function resetAllToDefaults(): self
    {
        $this->flush();

        return $this;
    }
}
