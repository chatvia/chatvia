<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Update\Data\ReleaseData;
use App\Admin\Update\Services\AvailableUpdate;
use App\Admin\Update\Services\InstalledVersion;
use Filament\Widgets\Widget;

/**
 * A one-line notice on top of the admin dashboard when a newer ChatVia.ai
 * release is out, with how this install upgrades and a link to the release
 * notes. It reads what the daily check found, so the dashboard never waits
 * on GitHub; while the install is up to date it is not drawn at all.
 *
 * A new major version is not installed by the complete installation's
 * automatic updates, so the notice asks for the release notes first.
 */
class UpdateBanner extends Widget
{
    #[\Override]
    protected static ?int $sort = 0;

    #[\Override]
    protected static bool $isLazy = false;

    #[\Override]
    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected string $view = 'filament.update-banner';

    #[\Override]
    public static function canView(): bool
    {
        return app(AvailableUpdate::class)->get() instanceof ReleaseData;
    }

    /**
     * @return array{release: ?ReleaseData, installed: string, hint: ?string, newMajor: bool}
     */
    #[\Override]
    protected function getViewData(): array
    {
        $updates = app(AvailableUpdate::class);
        $installed = app(InstalledVersion::class);
        $release = $updates->get();

        return [
            'release' => $release,
            'installed' => $installed->version(),
            'hint' => $installed->method()?->upgradeHint(),
            'newMajor' => $release instanceof ReleaseData && $updates->isNewMajor($release),
        ];
    }
}
