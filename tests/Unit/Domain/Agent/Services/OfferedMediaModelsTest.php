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
 * two models are listed. A pick is kept only when it is offered.
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

it('offers every listed model with the admin\'s default first, and keeps only an offered pick', function (): void {
    $offered = omm()->for($this->agent, GeneratedMediaKind::Image);

    expect(collect($offered)->pluck('id')->all())->toBe(['fal:fal-ai/flux-2', 'openai:gpt-image-2', 'fal:fal-ai/qwen-image'])
        ->and($offered[0]->is_default)->toBeTrue()
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, 'openai:gpt-image-2')?->service->value)->toBe('openai')
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, 'fal:fal-ai/flux-2-pro'))->toBeNull()
        ->and(omm()->resolve($this->agent, GeneratedMediaKind::Image, null))->toBeNull();
});

it('offers nothing while a gate is shut', function (Closure $shut): void {
    $shut($this->agent);

    expect(omm()->for($this->agent->fresh(), GeneratedMediaKind::Image))->toBe([]);
})->with([
    'agent off' => [fn (Agent $agent) => $agent->setSetting('image', false, OfferedMediaModels::SETTINGS_GROUP)],
    'capability off' => [fn (Agent $agent) => $agent->mcpServers()->detach()],
    'one model listed' => [fn () => config(['chatvia.ai.image_models' => ['openai:gpt-image-2']])],
]);
