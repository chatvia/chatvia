<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\OfferedMediaModels;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The image and video models a chatter may pick: only while the admin lets
 * agents offer a choice, the agent turned it on and has the capability on;
 * the admin's model first; the ticked ones of the service in use; none when
 * fewer than two are left.
 */
beforeEach(function (): void {
    config([
        'chatvia.ai.image_generator' => 'fal',
        'services.fal.image_model' => 'fal-ai/flux-2',
        'chatvia.ai.image_model_choice' => true,
        'chatvia.ai.image_models' => ['fal-ai/qwen-image', 'gpt-image-2', 'fal-ai/flux-2'],
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

it('offers the admin\'s model first, then the ticked ones of the service in use', function (): void {
    $offered = omm()->for($this->agent, GeneratedMediaKind::Image);

    expect(collect($offered)->map->toArray()->all())->toBe([
        ['id' => 'fal-ai/flux-2', 'name' => 'FLUX.2 dev', 'is_default' => true],
        ['id' => 'fal-ai/qwen-image', 'name' => 'Qwen-Image', 'is_default' => false],
    ]);
});

it('offers nothing while a gate is shut', function (Closure $shut): void {
    $shut($this->agent);

    expect(omm()->for($this->agent->fresh(), GeneratedMediaKind::Image))->toBe([]);
})->with([
    'admin off' => [fn () => config(['chatvia.ai.image_model_choice' => false])],
    'agent off' => [fn (Agent $agent) => $agent->setSetting('image', false, OfferedMediaModels::SETTINGS_GROUP)],
    'capability off' => [fn (Agent $agent) => $agent->mcpServers()->detach()],
    'one model left' => [fn () => config(['chatvia.ai.image_models' => ['gpt-image-2']])],
    'service off' => [fn () => config(['chatvia.ai.image_generator' => 'none'])],
]);

it('keeps an offered pick and drops any other', function (): void {
    expect(omm()->resolve($this->agent, GeneratedMediaKind::Image, 'fal-ai/qwen-image'))->toBe('fal-ai/qwen-image')
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, 'fal-ai/flux-2-pro'))->toBeNull()
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, null))->toBeNull();
});

it('offers no choice for audio files', function (): void {
    expect(omm()->isAllowed(GeneratedMediaKind::Audio))->toBeFalse()
        ->and(omm()->isEnabled($this->agent, GeneratedMediaKind::Audio))->toBeFalse();
});

it('keeps videos apart from pictures', function (): void {
    config([
        'chatvia.ai.video_generator' => 'kling',
        'services.kling.video_model' => 'kling-3.0-turbo',
        'chatvia.ai.video_model_choice' => true,
        'chatvia.ai.video_models' => ['kling-4.0'],
    ]);

    expect(omm()->for($this->agent, GeneratedMediaKind::Video))->toBe([])
        ->and(omm()->isAllowed(GeneratedMediaKind::Video))->toBeTrue();

    $this->agent->setSetting('video', true, OfferedMediaModels::SETTINGS_GROUP);
    ommCapability($this->agent, 'video-generation');

    expect(collect(omm()->for($this->agent->fresh(), GeneratedMediaKind::Video))->pluck('id')->all())->toBe(['kling-3.0-turbo', 'kling-4.0']);
});
