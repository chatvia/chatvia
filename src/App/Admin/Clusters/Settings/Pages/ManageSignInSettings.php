<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\SignInSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The OAuth apps behind the "Sign in with …" buttons on the login and
 * register pages.
 */
class ManageSignInSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = SignInSettings::class;

    #[\Override]
    protected static ?string $title = 'Sign-in';

    #[\Override]
    protected static ?string $slug = 'sign-in';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightEndOnRectangle;

    #[\Override]
    protected static ?int $navigationSort = 1;

    public static function description(): string
    {
        return 'Google, Microsoft, GitHub and Slack sign-in buttons.';
    }

    public static function overviewGroup(): string
    {
        return 'Access and security';
    }

    protected function cards(): array
    {
        return [
            $this->provider('google', 'Google', 'Google Cloud OAuth client for signing in with a Google account.',
                asset('images/logos/brands/google.svg'), 'https://console.cloud.google.com/apis/credentials'),
            $this->provider('microsoft', 'Microsoft', 'Microsoft Entra app registration for work and personal accounts.',
                asset('images/llms/microsoft.svg'), 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade'),
            $this->provider('github', 'GitHub', 'GitHub OAuth app for signing in with GitHub.',
                asset('images/logos/connectors/github.svg'), 'https://github.com/settings/developers', redirectLabel: 'Callback URL'),
            $this->provider('slack', 'Slack', 'Slack app for "Sign in with Slack".',
                asset('images/logos/connectors/slack.svg'), 'https://api.slack.com/apps'),
        ];
    }

    private function provider(string $service, string $label, string $description, string $icon, string $consoleUrl, string $redirectLabel = 'Redirect URL'): SettingsCard
    {
        return SettingsCard::make($service, $label, $description, $icon, fn (): array => [
            $this->text("{$service}_client_id", 'Client ID', required: true),
            $this->secret("{$service}_client_secret", 'Client secret'),
        ])
            ->advancedFields(fn (): array => [
                $this->url("{$service}_redirect", 'Another redirect URL',
                    help: 'Leave empty to use the URL shown. It must match a redirect URL registered in the OAuth app.'),
            ])
            ->required("{$service}_client_id", "{$service}_client_secret")
            ->console($consoleUrl)
            ->helpFile('provider')
            ->callbacks([
                $redirectLabel => "auth.{$service}.callback",
                // A chatter accepting a chat invitation with Google or Slack
                // signs in through the same app, back to its own URL.
                ...(in_array($service, ['google', 'slack'], true)
                    ? ['Chat invitation '.Str::lcfirst($redirectLabel) => ['agents.invitation.social.callback', ['provider' => $service]]]
                    : []),
            ]);
    }
}
