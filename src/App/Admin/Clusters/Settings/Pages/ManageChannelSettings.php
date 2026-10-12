<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\ChannelSettings;
use BackedEnum;
use Filament\Forms\Components\Field;
use Filament\Support\Icons\Heroicon;

/**
 * A Meta app for each of Messenger, Instagram and WhatsApp. Customers connect
 * their Pages, accounts and numbers through these apps.
 */
class ManageChannelSettings extends AdminSettingsPage
{
    private const string META_CONSOLE = 'https://developers.facebook.com/apps/';

    #[\Override]
    protected static string $settings = ChannelSettings::class;

    #[\Override]
    protected static ?string $title = 'Channels';

    #[\Override]
    protected static ?string $slug = 'channels';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    #[\Override]
    protected static ?int $navigationSort = 2;

    public static function description(): string
    {
        return 'Meta apps for Messenger, Instagram and WhatsApp.';
    }

    public static function overviewGroup(): string
    {
        return 'Connections';
    }

    protected function cards(): array
    {
        return [
            $this->metaApp('messenger', 'Messenger', 'Meta app that connects Facebook Pages to agents.', 'channels.messenger.callback', 'messenger.webhook'),
            $this->metaApp('instagram', 'Instagram', 'Meta app that connects Instagram professional accounts to agents.', 'channels.instagram.callback', 'instagram.webhook'),
            SettingsCard::make('whatsapp', 'WhatsApp', 'Meta app for WhatsApp Embedded Signup and the Cloud API.', asset('images/logos/channels/whatsapp.svg'), fn (): array => [
                ...$this->appFields('whatsapp'),
                $this->text('whatsapp_config_id', 'Login configuration ID',
                    help: 'The Facebook Login for Business configuration that Embedded Signup opens.', required: true),
                $this->secret('whatsapp_register_pin', 'Number registration PIN',
                    help: 'Six digits used to register connected numbers for the Cloud API. Do not use 000000.', required: false)
                    ->rules(['nullable', 'digits:6', 'not_in:000000']),
            ])
                ->required('whatsapp_app_id', 'whatsapp_app_secret', 'whatsapp_webhook_verify_token', 'whatsapp_config_id')
                ->console(self::META_CONSOLE)
                ->callbacks(['Webhook callback URL' => 'whatsapp.webhook']),
        ];
    }

    private function metaApp(string $channel, string $label, string $description, string $redirectRoute, string $webhookRoute): SettingsCard
    {
        return SettingsCard::make($channel, $label, $description, asset("images/logos/channels/{$channel}.svg"), fn (): array => [
            ...$this->appFields($channel),
            $this->text("{$channel}_config_id", 'Login configuration ID',
                help: 'Facebook Login for Business configuration. Without it the classic permission request is used.'),
            $this->url("{$channel}_redirect", 'Redirect URL',
                help: 'Leave empty to use the URL shown. It must be a valid OAuth redirect URI in the Meta app.'),
        ])
            ->required("{$channel}_app_id", "{$channel}_app_secret", "{$channel}_webhook_verify_token")
            ->console(self::META_CONSOLE)
            ->callbacks(['OAuth redirect' => $redirectRoute, 'Webhook callback URL' => $webhookRoute]);
    }

    /** @return list<Field> */
    private function appFields(string $channel): array
    {
        return [
            $this->text("{$channel}_app_id", 'App ID', required: true),
            $this->secret("{$channel}_app_secret", 'App secret'),
            $this->secret("{$channel}_webhook_verify_token", 'Webhook verify token',
                help: 'Any random string. Paste the same value in the webhook settings of the Meta app.'),
        ];
    }
}
