<?php

declare(strict_types=1);

namespace App\Admin\Update\Services;

use App\Admin\Update\Actions\CheckForUpdateAction;
use App\Admin\Update\Data\ReleaseData;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * The newest release the daily check found ({@see CheckForUpdateAction}),
 * kept in the cache so the admin panel never waits on GitHub. It counts as
 * an update only while it is newer than the release that runs, so the
 * notice goes away by itself once the install is upgraded.
 */
final readonly class AvailableUpdate
{
    private const string CACHE_KEY = 'chatvia:updates:latest-release';

    public function __construct(
        private InstalledVersion $installed,
        private Cache $cache,
    ) {}

    /** Null while up to date, before the first check, with checks off, or when the install runs no release. */
    public function get(): ?ReleaseData
    {
        if (config('chatvia.updates.check') !== true || ! $this->installed->isRelease()) {
            return null;
        }

        $latest = $this->cache->get(self::CACHE_KEY);

        if (! is_array($latest)) {
            return null;
        }

        $release = ReleaseData::from($latest);

        return version_compare($release->version, $this->installed->version(), '>') ? $release : null;
    }

    /** Whether the release starts a new major version, which automatic updates do not install. */
    public function isNewMajor(ReleaseData $release): bool
    {
        return Str::before($release->version, '.') !== Str::before($this->installed->version(), '.');
    }

    public function remember(ReleaseData $release): void
    {
        $this->cache->forever(self::CACHE_KEY, $release->toArray());
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}
