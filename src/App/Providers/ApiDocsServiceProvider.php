<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

/**
 * OpenAPI documentation (Scramble): only the public `/api/v1/*` routes are
 * documented, with the bearer token as the single security scheme, under the
 * brand name.
 */
class ApiDocsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::routes(fn (Route $route): bool => str_starts_with($route->uri, 'api/v1/'));

        // Scramble copies its config while its own provider boots, before the
        // brand name saved in the admin panel is applied, so set it again.
        $config = (array) config('scramble');
        data_set($config, 'ui.title', config('chatvia.brand.name').' API');

        Scramble::configure()->useConfig($config)->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(
                SecurityScheme::http('bearer', 'Bearer'),
            );
        });
    }
}
