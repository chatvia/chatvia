<?php

use App\Admin\Clusters\Settings\Pages\ManageWidgetSettings;
use App\Admin\Settings\WidgetSettings;
use App\Identity\Workspace\Models\User;
use Domain\Widget\Actions\BuildWidgetSettingsAction;
use Domain\Widget\Enums\ChatDeletionMode;

/**
 * Settings → Widget: the Chat Widget on every agent of the install. Whether
 * its header says "AI assistant" and what a visitor's "remove this chat"
 * does reach every widget, and a script address that is neither a path on
 * this server nor a full web address is refused.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('saves the AI disclosure and the chat removal mode, and every widget uses them', function (): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->fillForm(['ai_disclosure' => false, 'chat_deletion_mode' => 'hide'])
        ->call('save')
        ->assertHasNoFormErrors();

    $widget = app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()));

    expect(savedSetting(WidgetSettings::class, 'ai_disclosure'))->toBeFalse()
        ->and(savedSetting(WidgetSettings::class, 'chat_deletion_mode'))->toBe('hide')
        ->and($widget->ai_disclosure)->toBeFalse()
        ->and($widget->chat_deletion_mode)->toBe(ChatDeletionMode::Hide);
});

it('refuses a widget script address that is neither a path on this server nor a web address', function (string $address): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->fillForm(['script_url' => $address])
        ->call('save')
        ->assertHasFormErrors(['script_url']);

    expect(savedSetting(WidgetSettings::class, 'script_url'))->toBeNull();
})->with([
    'a relative path' => 'shakomako/embed.js',
    'an address without a scheme' => '//cdn.example.com/embed.js',
    'a script' => 'javascript:alert(1)',
]);
