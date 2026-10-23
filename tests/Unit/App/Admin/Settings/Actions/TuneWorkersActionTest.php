<?php

declare(strict_types=1);

use App\Admin\Settings\Actions\TuneWorkersAction;
use App\Admin\Settings\Data\WorkerCountsData;
use App\Admin\Settings\PlatformSettings;

/**
 * TuneWorkersAction: recommended worker numbers saved as the
 * Settings → Platform values, which config/horizon.php reads at start.
 */
it('saves the worker numbers to the platform settings', function (): void {
    $workers = new WorkerCountsData(chat: 12, ingestion: 8, analytics: 4, media: 2, voice: 6);

    app(TuneWorkersAction::class)->execute($workers);

    expect(savedSetting(PlatformSettings::class, 'chat_workers'))->toBe(12)
        ->and(savedSetting(PlatformSettings::class, 'ingestion_workers'))->toBe(8)
        ->and(savedSetting(PlatformSettings::class, 'analytics_workers'))->toBe(4)
        ->and(savedSetting(PlatformSettings::class, 'media_workers'))->toBe(2)
        ->and(savedSetting(PlatformSettings::class, 'voice_workers'))->toBe(6);
});

it('replaces worker numbers an admin saved before', function (): void {
    saveSettings(PlatformSettings::class, ['chat_workers' => 2, 'voice_workers' => 50]);

    app(TuneWorkersAction::class)->execute(new WorkerCountsData(chat: 6, ingestion: 4, analytics: 2, media: 1, voice: 3));

    expect(savedSetting(PlatformSettings::class, 'chat_workers'))->toBe(6)
        ->and(savedSetting(PlatformSettings::class, 'voice_workers'))->toBe(3);
});
