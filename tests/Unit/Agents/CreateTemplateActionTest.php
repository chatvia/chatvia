<?php

use App\Auth\Models\User;
use Domain\Agents\Actions\CreateTemplateAction;
use Domain\Agents\Data\CreateTemplateData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('creates a template owned by the user with all config', function (): void {
    $user = User::factory()->create();

    $template = app(CreateTemplateAction::class)->execute(
        new CreateTemplateData(...['user' => $user, ...templateDataArgs()])
    );

    expect($template->user_id)->toBe($user->id)
        ->and($template->name)->toBe('Support')
        ->and($template->type->value)->toBe('knowledge_base')
        ->and($template->color_theme)->toBe('indigo')
        ->and($template->color_shade)->toBe(500)
        ->and($template->starters)->toBe(['One?', 'Two?'])
        ->and($template->models)->toBe(['gpt-4o'])
        ->and($template->widget_language)->toBe('en')
        ->and($template->widget_sites)->toBe(['acme.test'])
        ->and($template->widget_show_on_load)->toBeTrue()
        ->and($template->is_public)->toBeFalse();
});

it('persists the is_public flag', function (): void {
    $user = User::factory()->create();

    $template = app(CreateTemplateAction::class)->execute(
        new CreateTemplateData(...['user' => $user, ...templateDataArgs(['isPublic' => true])])
    );

    expect($template->is_public)->toBeTrue();
});

it('stores resized avatar and logo as raw paths', function (): void {
    Storage::fake();
    $user = User::factory()->create();

    $template = app(CreateTemplateAction::class)->execute(
        new CreateTemplateData(...['user' => $user, ...templateDataArgs([
            'avatar' => UploadedFile::fake()->image('avatar.png', 64, 64),
            'logo' => UploadedFile::fake()->image('logo.png', 64, 64),
        ])])
    );

    expect($template->agent_avatar_path)->not->toBeNull()
        ->and($template->logo_image_path)->not->toBeNull();
    Storage::assertExists($template->agent_avatar_path);
    Storage::assertExists($template->logo_image_path);
});

it('reindexes sparse starter and model keys into clean lists', function (): void {
    $user = User::factory()->create();

    $template = app(CreateTemplateAction::class)->execute(
        new CreateTemplateData(...['user' => $user, ...templateDataArgs([
            'starters' => [3 => 'b', 7 => 'a'],
            'models' => [9 => 'gpt-4o'],
        ])])
    );

    expect($template->starters)->toBe(['b', 'a'])
        ->and($template->models)->toBe(['gpt-4o']);
});
