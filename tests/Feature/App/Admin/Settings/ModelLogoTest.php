<?php

declare(strict_types=1);

use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Http;

/**
 * A model's logo is fetched from models.dev and served from this app's own
 * origin, so its SVG is sent with a policy that blocks any script in it.
 */
beforeEach(function (): void {
    forgetModelsDevCatalog();
    Http::fake([
        ModelsDevCatalog::URL => Http::response(['providers' => [
            'openrouter' => ['models' => ['qwen/qwen3-235b-a22b' => ['canonical_model_id' => 'alibaba/qwen3-235b-a22b']]],
        ], 'models' => []]),
        'models.dev/logos/labs/alibaba.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'models.dev/logos/*' => Http::response('Not found', 404),
    ]);
    app(ModelsDevCatalog::class)->refresh();
});

it('serves a lab\'s logo with scripts blocked, to an admin and on the public route', function (): void {
    $this->actingAs(adminUser(), 'admin')
        ->get('/admin/model-logos/labs/alibaba.svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'")
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get('/model-logos/labs/alibaba.svg')
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'");
});
