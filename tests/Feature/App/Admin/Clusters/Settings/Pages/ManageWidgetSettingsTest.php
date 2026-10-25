<?php

use App\Admin\Clusters\Settings\Pages\ManageWidgetSettings;
use App\Admin\Settings\WidgetSettings;
use App\Identity\Workspace\Models\User;
use Domain\Widget\Actions\BuildWidgetSettingsAction;

/**
 * Settings → Widget: whether the Chat Widget's header says "AI assistant" on
 * every agent of the install. On until an admin turns it off.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('shows the AI disclosure on by default', function (): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->assertSee('AI disclosure')
        ->assertSee('Shows "AI assistant"')
        ->assertSchemaStateSet(['ai_disclosure' => true]);

    expect(app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()))->ai_disclosure)->toBeTrue();
});

it('turns the AI disclosure off for every widget', function (): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->fillForm(['ai_disclosure' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageWidgetSettings::getUrl())
        ->assertSee('Hides "AI assistant"');

    expect(savedSetting(WidgetSettings::class, 'ai_disclosure'))->toBeFalse()
        ->and(config('chatvia.widget.ai_disclosure'))->toBeFalse()
        ->and(app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()))->ai_disclosure)->toBeFalse();
});
