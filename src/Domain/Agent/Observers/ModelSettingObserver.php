<?php

namespace Domain\Agent\Observers;

use Domain\Agent\Models\ModelSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ModelSettingObserver
{
    /**
     * Handle the ModelSetting "created" event.
     */
    public function created(ModelSetting $modelSetting): void
    {
        if (config('model-settings.observer.auto_cache_clearing', true)) {
            $this->clearCache($modelSetting);
        }
    }

    /**
     * Handle the ModelSetting "updated" event.
     */
    public function updated(ModelSetting $modelSetting): void
    {
        if (config('model-settings.observer.auto_cache_clearing', true)) {
            $this->clearCache($modelSetting);
        }
    }

    /**
     * Handle the ModelSetting "deleted" event.
     */
    public function deleted(ModelSetting $modelSetting): void
    {
        if (config('model-settings.observer.auto_cache_clearing', true)) {
            $this->clearCache($modelSetting);
        }
    }

    /**
     * Handle the ModelSetting "force deleted" event.
     */
    public function forceDeleted(ModelSetting $modelSetting): void
    {
        if (config('model-settings.observer.auto_cache_clearing', true)) {
            $this->clearCache($modelSetting);
        }
    }

    /**
     * Clear cache for the affected model setting.
     */
    protected function clearCache(ModelSetting $modelSetting): void
    {
        // Check if caching is enabled
        if (! config('model-settings.cache.enabled', true)) {
            return;
        }

        $cachePrefix = config('model-settings.cache.prefix', 'model_settings');

        // Clear specific setting cache
        $specificCacheKey = sprintf(
            '%s.%s.%s.%s',
            $cachePrefix,
            $modelSetting->model_type,
            $modelSetting->model_id,
            $modelSetting->key
        );

        // Clear all settings cache for this model
        $allCacheKey = sprintf(
            '%s.%s.%s.all',
            $cachePrefix,
            $modelSetting->model_type,
            $modelSetting->model_id
        );

        Cache::forget($specificCacheKey);
        Cache::forget($allCacheKey);

        // For additional safety, clear any potential cache tags if using Redis
        if (config('cache.default') === 'redis') {
            $this->clearRedisCache($modelSetting, $cachePrefix);
        }
    }

    /**
     * Clear Redis cache using pattern matching (if available).
     */
    protected function clearRedisCache(ModelSetting $modelSetting, string $cachePrefix): void
    {
        try {
            $redis = Redis::connection(config('cache.stores.redis.connection'));
            $pattern = sprintf(
                '%s:%s.%s.%s.*',
                config('cache.prefix'),
                $cachePrefix,
                $modelSetting->model_type,
                $modelSetting->model_id
            );

            $keys = $redis->keys($pattern);
            if (! empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Exception $e) {
            // Silently fail if Redis pattern deletion doesn't work
            // The specific cache keys were already cleared above
            \Log::debug('Failed to clear Redis cache pattern: '.$e->getMessage());
        }
    }

    /**
     * Clear all cache for a specific model instance.
     */
    public static function clearCacheForModel(string $modelType, int $modelId): void
    {
        if (! config('model-settings.cache.enabled', true)) {
            return;
        }

        $cachePrefix = config('model-settings.cache.prefix', 'model_settings');

        // Clear all settings cache for this model
        $allCacheKey = sprintf(
            '%s.%s.%s.all',
            $cachePrefix,
            $modelType,
            $modelId
        );

        Cache::forget($allCacheKey);

        // For Redis, try to clear all related cache keys
        if (config('cache.default') === 'redis') {
            try {
                $redis = Redis::connection(config('cache.stores.redis.connection'));
                $pattern = sprintf(
                    '%s:%s.%s.%s.*',
                    config('cache.prefix'),
                    $cachePrefix,
                    $modelType,
                    $modelId
                );

                $keys = $redis->keys($pattern);
                if (! empty($keys)) {
                    $redis->del($keys);
                }
            } catch (\Exception $e) {
                \Log::debug('Failed to clear Redis cache pattern: '.$e->getMessage());
            }
        }
    }
}
