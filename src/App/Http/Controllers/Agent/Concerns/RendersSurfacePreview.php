<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Concerns;

use App\Http\Controllers\Agent\Bar\BarPreviewController;
use App\Http\Controllers\Agent\Widget\WidgetPreviewController;
use App\Identity\Workspace\Services\SurfacePreviewTokens;
use Illuminate\Http\Request;

/**
 * Shared by the Playground previews ({@see WidgetPreviewController},
 * {@see BarPreviewController}), which load the real embed script in an iframe.
 * The `data-auth` token itself is minted by
 * {@see SurfacePreviewTokens}.
 */
trait RendersSurfacePreview
{
    /** The preview theme from `?theme=`, or null for the agent's own. */
    private function previewTheme(Request $request): ?string
    {
        $theme = $request->query('theme');

        return collect(['auto', 'light', 'dark'])->containsStrict($theme) ? $theme : null;
    }
}
