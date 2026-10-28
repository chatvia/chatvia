<?php

declare(strict_types=1);

namespace App\Admin\Update\Services;

use App\Admin\Update\Data\ReleaseData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The newest ChatVia.ai release, read from the vX.Y.Z tags of its repository
 * the way the installer reads them: release candidates and branches are left
 * out. It asks Git's own HTTP endpoint, which needs no API key and has no
 * rate limit, and sends nothing about this install.
 */
final class ReleaseFeed
{
    private const string TAG_PATTERN = '#refs/tags/v(\d+\.\d+\.\d+)\n#';

    /** Null when the repository could not be read or has no release yet. */
    public function latest(): ?ReleaseData
    {
        $repository = Str::rtrim(config('chatvia.updates.repository') ?? '', '/');

        try {
            $response = Http::timeout(15)->get("{$repository}.git/info/refs", ['service' => 'git-upload-pack']);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $version = Str::matchAll(self::TAG_PATTERN, $response->body())
            ->sort(version_compare(...))
            ->last();

        return $version === null ? null : new ReleaseData(
            version: $version,
            url: "{$repository}/releases/tag/v{$version}",
        );
    }
}
