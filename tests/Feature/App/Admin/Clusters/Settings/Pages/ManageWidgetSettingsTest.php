<?php

use App\Admin\Clusters\Settings\Pages\ManageWidgetSettings;
use App\Admin\Settings\WidgetSettings;
use App\Identity\Workspace\Models\User;
use Domain\Widget\Actions\BuildWidgetSettingsAction;
use Domain\Widget\Enums\ChatDeletionMode;

/**
 * Settings → Widget: the Chat Widget on every agent of the install. Where its
 * script loads from, its reply limits, whether its header says "AI
 * assistant" (on until an admin turns it off), and what a visitor's "remove
 * this chat" does (ending the chat until an admin picks another way).
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

it('takes a path on this server or a full address for the widget script', function (string $address): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->fillForm(['script_url' => $address])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(WidgetSettings::class, 'script_url'))->toBe($address)
        ->and(url(config('chatvia.widget.script_url_embed')))->toBe(url($address));
})->with([
    'a path on this server' => '/shakomako/next/embed.js',
    'a full address' => 'https://cdn.example.com/embed.js',
]);

it('refuses a widget script address that is neither', function (string $address): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->fillForm(['script_url' => $address])
        ->call('save')
        ->assertHasFormErrors(['script_url']);

    expect(savedSetting(WidgetSettings::class, 'script_url'))->toBeNull();
})->with([
    'a relative path' => 'shakomako/embed.js',
    'an address without a scheme' => '//cdn.example.com/embed.js',
    'a script' => 'javascript:alert(1)',
    'another scheme' => 'ftp://cdn.example.com/embed.js',
]);

it('saves the reply limits and the extra sites', function (): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->fillForm(['allowed_origins' => 'example.com, shop.example.com', 'stream_per_minute' => 30, 'stream_per_hour' => 600])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageWidgetSettings::getUrl())
        ->assertSee('30 replies per agent per minute');

    expect(config('chatvia.widget.extra_allowed_origins'))->toBe('example.com, shop.example.com')
        ->and(config('chatvia.widget.stream_per_agent_per_minute'))->toBe(30)
        ->and(config('chatvia.widget.stream_per_agent_per_hour'))->toBe(600);
});

it('lets the admin choose what removing a chat does, ending it by default', function (): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->assertSee('Removing chats')
        ->fillForm(['chat_deletion_mode' => 'hide'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(WidgetSettings::class, 'chat_deletion_mode'))->toBe('hide')
        ->and(app(BuildWidgetSettingsAction::class)->execute(createAgent(User::factory()->create()))->chat_deletion_mode)->toBe(ChatDeletionMode::Hide);
});

it('refuses a removal mode it does not know', function (): void {
    adminPage(ManageWidgetSettings::class, $this->admin)
        ->fillForm(['chat_deletion_mode' => 'archive'])
        ->call('save')
        ->assertHasFormErrors(['chat_deletion_mode']);
});
