<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Agent\Services\OfferedMediaModels;
use Illuminate\Testing\TestResponse;

/**
 * PUT agents/{agent}/capabilities/{slug}/model-choice lets chatters pick the
 * image or video model in the hosted chat, while the admin allows it.
 */
beforeEach(function (): void {
    withLicense();
    config([
        'chatvia.ai.image_generator' => 'fal',
        'services.fal.image_model' => 'fal-ai/flux-2',
        'chatvia.ai.image_model_choice' => true,
        'chatvia.ai.image_models' => ['fal-ai/qwen-image'],
    ]);
    $this->owner = User::factory()->create();
    $this->agent = makeTeamAgent($this->owner, 'Support Bot');
});

function ucmcSet(User $user, string $agentId, string $slug, bool $enabled): TestResponse
{
    return test()->actingAs($user)->putJson(route('agents.capabilities.model-choice', ['agent' => $agentId, 'slug' => $slug]), ['enabled' => $enabled]);
}

it('turns the image model choice on and off', function (): void {
    ucmcSet($this->owner, $this->agent->external_id, 'image-generation', true)->assertOk()->assertJsonPath('ok', true);

    expect($this->agent->fresh()->settings(OfferedMediaModels::SETTINGS_GROUP)->get('image'))->toBeTrue();

    ucmcSet($this->owner, $this->agent->external_id, 'image-generation', false)->assertOk();

    expect($this->agent->fresh()->settings(OfferedMediaModels::SETTINGS_GROUP)->get('image'))->toBeFalse();
});

it('refuses to turn it on while the admin does not allow a choice', function (): void {
    config(['chatvia.ai.video_model_choice' => false]);

    ucmcSet($this->owner, $this->agent->external_id, 'video-generation', true)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['enabled']);
});

it('lets it be turned off while the admin does not allow a choice', function (): void {
    $this->agent->setSetting('video', true, OfferedMediaModels::SETTINGS_GROUP);

    ucmcSet($this->owner, $this->agent->external_id, 'video-generation', false)->assertOk();

    expect($this->agent->fresh()->settings(OfferedMediaModels::SETTINGS_GROUP)->get('video'))->toBeFalse();
});

it('404s for a capability with no model choice', function (): void {
    ucmcSet($this->owner, $this->agent->external_id, 'charts', true)->assertNotFound();
});

it('404s for another workspace\'s agent', function (): void {
    ucmcSet(User::factory()->create(), $this->agent->external_id, 'image-generation', true)->assertNotFound();
});
