<?php

use App\Auth\Models\User;
use Domain\Agents\Actions\UpdateTemplateAction;
use Domain\Agents\Data\UpdateTemplateData;
use Domain\Agents\Models\AgentTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('updates a template in place', function (): void {
    $user = User::factory()->create();
    $template = AgentTemplate::factory()->create(['user_id' => $user->id, 'name' => 'Old']);

    $updated = app(UpdateTemplateAction::class)->execute(
        $user,
        $template->external_id,
        new UpdateTemplateData(...templateDataArgs(['name' => 'New', 'colorTheme' => 'emerald']))
    );

    expect($updated->name)->toBe('New')
        ->and($updated->color_theme)->toBe('emerald')
        ->and($template->fresh()->color_theme)->toBe('emerald');
});

it('updates the is_public flag', function (): void {
    $user = User::factory()->create();
    $template = AgentTemplate::factory()->create(['user_id' => $user->id, 'is_public' => false]);

    $updated = app(UpdateTemplateAction::class)->execute(
        $user,
        $template->external_id,
        new UpdateTemplateData(...templateDataArgs(['isPublic' => true]))
    );

    expect($updated->is_public)->toBeTrue()
        ->and($template->fresh()->is_public)->toBeTrue();
});

it('replaces the avatar when a new file is supplied', function (): void {
    Storage::fake();
    $user = User::factory()->create();
    $template = AgentTemplate::factory()->create(['user_id' => $user->id]);

    app(UpdateTemplateAction::class)->execute(
        $user,
        $template->external_id,
        new UpdateTemplateData(...templateDataArgs([
            'avatar' => UploadedFile::fake()->image('new.png', 64, 64),
        ]))
    );

    $path = $template->fresh()->agent_avatar_path;
    expect($path)->not->toBeNull();
    Storage::assertExists($path);
});

it('leaves the avatar untouched when none is supplied', function (): void {
    $user = User::factory()->create();
    $template = AgentTemplate::factory()->create([
        'user_id' => $user->id,
        'agent_avatar_path' => 'users/x/templates/keep.png',
    ]);

    app(UpdateTemplateAction::class)->execute(
        $user,
        $template->external_id,
        new UpdateTemplateData(...templateDataArgs())
    );

    expect($template->fresh()->agent_avatar_path)->toBe('users/x/templates/keep.png');
});
