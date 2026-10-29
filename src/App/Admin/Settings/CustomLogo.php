<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The install's own icon (Settings → Platform), shown in place of the
 * ChatVia.ai mark in the admin panel and on the sign-in pages, and the
 * favicons made from it ({@see Actions\MakeFaviconsAction}). It needs a
 * license key: without one SettingsConfig empties `chatvia.logo`, so the
 * ChatVia.ai mark and favicons show and the saved files wait for a key.
 */
final readonly class CustomLogo
{
    /** Where the uploads go on the `assets` disk. */
    public const string DIRECTORY = 'platform';

    /** The smallest side of an upload: the largest favicon is made from it. */
    public const int MIN_SIZE = 512;

    /** The PNG favicons made from the icon, by file name, with their size in pixels. */
    public const array FAVICONS = [
        'favicon-96x96.png' => 96,
        'apple-touch-icon.png' => 180,
        'web-app-manifest-192x192.png' => 192,
        'web-app-manifest-512x512.png' => 512,
    ];

    /** The sizes inside the made favicon.ico. */
    public const array ICO_SIZES = [32, 48];

    public function __construct(private Config $config) {}

    /** The icon's public address, or null while the mark shows. */
    public function url(): ?string
    {
        $path = $this->path();

        return $path !== null ? Storage::disk('assets')->url($path) : null;
    }

    /**
     * The address of a favicon (a file of public/images/favicons): the one
     * made from the icon, or the ChatVia.ai one while there is no icon. Null
     * for a file the icon has no copy of (favicon.svg), so the ChatVia.ai
     * one does not win over the icon's.
     */
    public function favicon(string $file): ?string
    {
        $path = $this->path();

        if ($path === null) {
            return asset("images/favicons/{$file}");
        }

        if ($file !== 'favicon.ico' && ! array_key_exists($file, self::FAVICONS)) {
            return null;
        }

        return Storage::disk('assets')->url(self::faviconDirectory($path)."/{$file}");
    }

    /** Whether the favicons and the mark are the install's own. */
    public function isSet(): bool
    {
        return $this->path() !== null;
    }

    /** The folder that holds the favicons of an icon: one per upload, so browsers never keep an old one. */
    public static function faviconDirectory(string $logoPath): string
    {
        return self::DIRECTORY.'/favicons/'.Str::of($logoPath)->basename()->beforeLast('.');
    }

    private function path(): ?string
    {
        $path = $this->config->get('chatvia.logo');

        return filled($path) ? $path : null;
    }
}
