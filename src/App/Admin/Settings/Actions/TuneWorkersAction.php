<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\Data\WorkerCountsData;
use App\Admin\Settings\HorizonWorkers;
use App\Admin\Settings\PlatformSettings;

/**
 * Saves queue-worker numbers as the Settings → Platform values, where an
 * admin changes them later. Horizon reads them when it starts, so a running
 * one keeps its numbers until it restarts ({@see HorizonWorkers}).
 */
final readonly class TuneWorkersAction
{
    public function __construct(private PlatformSettings $settings) {}

    public function execute(WorkerCountsData $workers): PlatformSettings
    {
        $this->settings->chat_workers = $workers->chat;
        $this->settings->ingestion_workers = $workers->ingestion;
        $this->settings->analytics_workers = $workers->analytics;
        $this->settings->media_workers = $workers->media;
        $this->settings->voice_workers = $workers->voice;
        $this->settings->save();

        return $this->settings;
    }
}
