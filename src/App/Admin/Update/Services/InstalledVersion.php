<?php

declare(strict_types=1);

namespace App\Admin\Update\Services;

use App\Admin\Update\Enums\InstallMethod;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The ChatVia.ai version this install runs, from the VERSION file that the
 * Docker build and the complete installation's deploy write: a release like
 * 1.4.2, or "main@1a2b3c4" for a branch. A git checkout has no file and
 * runs "dev". Only a release is compared with the newest one.
 */
final class InstalledVersion
{
    private const string RELEASE_PATTERN = '/^\d+\.\d+\.\d+$/';

    public function version(): string
    {
        return once(function (): string {
            $file = config('chatvia.updates.version_file') ?? '';
            $version = filled($file) && File::isFile($file) ? Str::trim(File::get($file)) : '';

            return filled($version) ? $version : 'dev';
        });
    }

    public function isRelease(): bool
    {
        return Str::isMatch(self::RELEASE_PATTERN, $this->version());
    }

    public function method(): ?InstallMethod
    {
        return InstallMethod::tryFrom(config('chatvia.updates.install') ?? '');
    }
}
