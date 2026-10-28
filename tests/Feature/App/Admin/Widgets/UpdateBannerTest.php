<?php

use App\Admin\Pages\Dashboard;
use App\Admin\Update\Data\ReleaseData;
use App\Admin\Update\Services\AvailableUpdate;
use App\Admin\Widgets\UpdateBanner;

/**
 * The update notice on the admin dashboard: shown while the daily check has
 * found a newer release, with how this install upgrades.
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

it('tells admins a newer release is out and how to upgrade', function (): void {
    ubFound('1.5.0');

    adminPage(Dashboard::class)->assertSeeLivewire(UpdateBanner::class);
    adminPage(UpdateBanner::class)
        ->assertSee('ChatVia.ai 1.5.0 is out.')
        ->assertSee('This install runs 1.4.2.')
        ->assertSee('run the installer again')
        ->assertDontSee('new major version');
});

it('asks for the release notes first before a new major version', function (): void {
    ubFound('2.0.0');

    adminPage(UpdateBanner::class)->assertSee('It is a new major version');
});

it('shows nothing once the install runs that release', function (): void {
    ubFound('1.4.2');

    adminPage(Dashboard::class)->assertDontSeeLivewire(UpdateBanner::class);
});
