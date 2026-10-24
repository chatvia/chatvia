<?php

declare(strict_types=1);

use App\Admin\Settings\ModelLogo;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\ModelsDevCatalog;
use Illuminate\Support\Facades\Http;

/**
 * A model's logo: the app serves it from models.dev to signed-in admins (so
 * their browsers never call models.dev), and names it before the model's
 * name wherever the admin panel shows a model.
 */
beforeEach(function (): void {
    config(['chatvia.ai.models_dev.enabled' => true]);
    Http::fake([
        ModelsDevCatalog::URL => Http::response(['providers' => [
            'openrouter' => ['models' => ['qwen/qwen3-235b-a22b' => ['canonical_model_id' => 'alibaba/qwen3-235b-a22b']]],
        ], 'models' => []]),
        'models.dev/logos/labs/alibaba.svg' => Http::response('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'models.dev/logos/*' => Http::response('Not found', 404),
    ]);
    app(ModelsDevCatalog::class)->refresh();
});

it('serves a lab\'s logo to an admin, with scripts blocked', function (): void {
    $this->actingAs(adminUser(), 'admin')
        ->get('/admin/model-logos/labs/alibaba.svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'")
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('gives no logo models.dev does not have, nor to a guest', function (): void {
    $this->actingAs(adminUser(), 'admin')->get('/admin/model-logos/labs/nobody.svg')->assertNotFound();

    auth('admin')->logout();

    $this->get('/admin/model-logos/labs/alibaba.svg')->assertRedirect();
});

it('puts the lab\'s logo before the model\'s name', function (): void {
    expect(ModelLogo::label('Qwen3 <235B>', LlmProviderKind::OpenRouter, 'qwen/qwen3-235b-a22b'))
        ->toBe('<span class="cv-model-label"><span class="cv-model-logo" style="-webkit-mask-image: url(/admin/model-logos/labs/alibaba.svg); mask-image: url(/admin/model-logos/labs/alibaba.svg)" aria-hidden="true"></span><span>Qwen3 &lt;235B&gt;</span></span>')
        ->and(ModelLogo::label('llama3.1:8b', LlmProviderKind::Custom, 'llama3.1:8b'))->toBe('llama3.1:8b');
});

it('puts a provider\'s own logo before its name', function (): void {
    expect(ModelLogo::forProvider('OpenRouter', LlmProviderKind::OpenRouter))
        ->toBe('<span class="cv-model-label"><span class="cv-model-logo" style="-webkit-mask-image: url(/admin/model-logos/openrouter.svg); mask-image: url(/admin/model-logos/openrouter.svg)" aria-hidden="true"></span><span>OpenRouter</span></span>')
        ->and(ModelLogo::forProvider('My <Ollama>', LlmProviderKind::Custom))->toBe('My &lt;Ollama&gt;');
});
