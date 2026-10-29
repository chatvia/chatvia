<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The image and video models a chatter may pick: every model the admin
 * listed, from any service, with the admin's default first; only while the
 * agent turned the choice on and has the capability on; none when fewer than
 * two models are listed.
 */
beforeEach(function (): void {
    config([
        'chatvia.ai.image_models' => ['openai:gpt-image-2', 'fal:fal-ai/qwen-image', 'fal:fal-ai/flux-2'],
        'chatvia.ai.default_image_model' => 'fal:fal-ai/flux-2',
    ]);
    $this->agent = Agent::factory()->create();
    $this->agent->setSetting('image', true, OfferedMediaModels::SETTINGS_GROUP);
    ommCapability($this->agent, 'image-generation');
});

function ommCapability(Agent $agent, string $slug): void
{
    $server = AgentMcpServer::factory()->builtin($slug)->create(['workspace_id' => $agent->workspace_id]);
    $agent->mcpServers()->attach($server->id);
}

function omm(): OfferedMediaModels
{
    return app(OfferedMediaModels::class);
}

it('offers every listed model, of any service, with the admin\'s default first', function (): void {
    $offered = omm()->for($this->agent, GeneratedMediaKind::Image);

    expect(collect($offered)->map->toArray()->all())->toBe([
        ['id' => 'fal:fal-ai/flux-2', 'name' => 'FLUX.2 dev', 'is_default' => true],
        ['id' => 'openai:gpt-image-2', 'name' => 'GPT Image 2', 'is_default' => false],
        ['id' => 'fal:fal-ai/qwen-image', 'name' => 'Qwen-Image', 'is_default' => false],
    ]);
});

it('offers nothing while a gate is shut', function (Closure $shut): void {
    $shut($this->agent);

    expect(omm()->for($this->agent->fresh(), GeneratedMediaKind::Image))->toBe([]);
})->with([
    'agent off' => [fn (Agent $agent) => $agent->setSetting('image', false, OfferedMediaModels::SETTINGS_GROUP)],
    'capability off' => [fn (Agent $agent) => $agent->mcpServers()->detach()],
    'one model listed' => [fn () => config(['chatvia.ai.image_models' => ['openai:gpt-image-2']])],
]);

it('keeps an offered pick and drops any other', function (): void {
    expect(omm()->resolve($this->agent, GeneratedMediaKind::Image, 'openai:gpt-image-2')?->service->value)->toBe('openai')
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, 'fal:fal-ai/flux-2-pro'))->toBeNull()
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, 'gpt-image-2'))->toBeNull()
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, null))->toBeNull();
});

it('offers no choice for audio files', function (): void {
    expect(omm()->isAllowed(GeneratedMediaKind::Audio))->toBeFalse()
        ->and(omm()->isEnabled($this->agent, GeneratedMediaKind::Audio))->toBeFalse();
});

it('keeps videos apart from pictures', function (): void {
    config(['chatvia.ai.video_models' => ['kling:kling-3.0-turbo', 'runway:veo3.1_fast', 'fal:fal-ai/veo3.1/fast']]);

    expect(omm()->for($this->agent, GeneratedMediaKind::Video))->toBe([])
        ->and(omm()->isAllowed(GeneratedMediaKind::Video))->toBeTrue();

    $this->agent->setSetting('video', true, OfferedMediaModels::SETTINGS_GROUP);
    ommCapability($this->agent, 'video-generation');

    expect(collect(omm()->for($this->agent->fresh(), GeneratedMediaKind::Video))->pluck('name', 'id')->all())->toBe([
        'kling:kling-3.0-turbo' => 'Kling 3.0 Turbo',
        'runway:veo3.1_fast' => 'Google Veo 3.1 Fast (Runway)',
        'fal:fal-ai/veo3.1/fast' => 'Google Veo 3.1 Fast (fal.ai)',
    ]);
});
