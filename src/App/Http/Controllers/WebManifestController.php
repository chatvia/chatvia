<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Admin\Settings\CustomLogo;
use Illuminate\Http\JsonResponse;

/**
 * `/site.webmanifest` (route `web-manifest`): what a phone uses when the app
 * is added to its home screen, with the app's name (Settings → Platform) and
 * the install's own favicons, or the ChatVia.ai ones.
 */
class WebManifestController
{
    public function __invoke(CustomLogo $logo): JsonResponse
    {
        // The ChatVia.ai icons leave room around the mark, so a phone may
        // cut them to its own shape; an uploaded icon may fill its square.
        $purpose = $logo->isSet() ? 'any' : 'maskable';

        return response()->json([
            'name' => config('app.name'),
            'short_name' => config('app.name'),
            'icons' => collect([192, 512])->map(fn (int $size): array => [
                'src' => $logo->favicon("web-app-manifest-{$size}x{$size}.png"),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => $purpose,
            ])->all(),
            'theme_color' => '#ffffff',
            'background_color' => '#ffffff',
            'display' => 'standalone',
        ], headers: ['Content-Type' => 'application/manifest+json']);
    }
}
