<?php

use App\Admin\Pages\Dashboard;
use App\Admin\Update\Data\ReleaseData;
use App\Admin\Update\Services\AvailableUpdate;
use App\Admin\Widgets\UpdateBanner;

/**
 * The update notice on the admin dashboard: shown while the daily check has
 * found a newer release than the one this install runs, and gone once the
 * install runs it.
 */
beforeEach(function (): void {
    config([
        'chatvia.updates.check' => true,
        'chatvia.updates.install' => 'complete',
        'chatvia.updates.version_file' => create_temp_file('VERSION', "1.4.2\n"),
    ]);
});

function ubFound(string $version): void
{
    app(AvailableUpdate::class)->remember(new ReleaseData(version: $version, url: "https://github.com/chatvia/chatvia/releases/tag/v{$version}"));
}

it('shows the notice while a newer release is out, and not once the install runs it', function (): void {
    ubFound('1.5.0');

    adminPage(Dashboard::class)->assertSeeLivewire(UpdateBanner::class);

    ubFound('1.4.2');

    adminPage(Dashboard::class)->assertDontSeeLivewire(UpdateBanner::class);
});
