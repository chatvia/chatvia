<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Http\Response;

/**
 * Serves a model's logo from models.dev, kept by the app ({@see ModelsDevCatalog::logoSvg()}).
 * The SVG can never run a script: it is sent with a policy that blocks them.
 * Any site may load it: the widget draws it as a CSS mask, which browsers
 * fetch with CORS, and a logo holds nothing private.
 */
final class ModelLogoController
{
    public function __invoke(string $logo, ModelsDevCatalog $catalog): Response
    {
        $svg = $catalog->logoSvg($logo);

        abort_if($svg === null, 404);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=604800',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
