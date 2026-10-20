<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\SettingsConfig;
use Domain\Llm\Models\Llm;
use Illuminate\Contracts\Container\Container;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

/**
 * Reads the admin settings and the AI model list from the database again
 * (the reload button of Status → Caches → Settings). Both are cached for
 * every request and worker; a process that read the database while a save
 * was still open can leave a copy from before the save, so a key added in
 * the panel looks missing. Workers pick the fresh copy up before their next
 * job.
 */
final readonly class ReloadSavedSettingsAction
{
    public function __construct(
        private SettingsCacheFactory $caches,
        private SettingsConfig $config,
        private Container $container,
    ) {}

    public function execute(): void
    {
        foreach ($this->caches->all() as $cache) {
            $cache->clear();
        }

        Llm::flushCatalogCache();

        // This request already holds the settings it read from the old cache.
        foreach ($this->config->classes() as $class) {
            $this->container->forgetInstance($class);
        }

        // Reads every class from the database.
        $this->config->apply();

        // The package caches settings it loads from the database, but its
        // per-request config of each class still says "loaded from cache"
        // from the read before the clear, so it skips that. Cache them here.
        foreach ($this->config->classes() as $class) {
            $this->caches->build($class::repository())->put($this->container->make($class));
        }
    }
}
