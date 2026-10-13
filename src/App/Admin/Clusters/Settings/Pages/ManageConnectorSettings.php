<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\ConnectorSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The OAuth apps that let workspaces connect services whose MCP servers do not
 * support dynamic client registration.
 */
class ManageConnectorSettings extends AdminSettingsPage
{
    /** Where each service's OAuth apps are created, by connector slug. */
    private const array CONSOLES = [
        'github' => 'https://github.com/settings/developers',
        'gmail' => 'https://console.cloud.google.com/apis/credentials',
        'google-calendar' => 'https://console.cloud.google.com/apis/credentials',
        'google-drive' => 'https://console.cloud.google.com/apis/credentials',
        'slack' => 'https://api.slack.com/apps',
        'hubspot' => null,
        'box' => 'https://app.box.com/developers/console',
        'asana' => 'https://app.asana.com/0/my-apps',
    ];

    #[\Override]
    protected static string $settings = ConnectorSettings::class;

    #[\Override]
    protected static ?string $title = 'Connectors';

    #[\Override]
    protected static ?string $slug = 'connectors';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    #[\Override]
    protected static ?int $navigationSort = 7;

    public static function description(): string
    {
        return 'OAuth apps for GitHub, Gmail, Slack and more.';
    }

    public static function overviewGroup(): string
    {
        return 'Connections';
    }

    protected function cards(): array
    {
        return array_map($this->connector(...), array_keys(self::CONSOLES));
    }

    private function connector(string $slug): SettingsCard
    {
        $label = (string) config("connectors.{$slug}.name");
        $property = Str::snake(str_replace('-', '_', $slug));

        return SettingsCard::make(
            $property,
            $label,
            "Lets workspaces connect {$label} to their agents.",
            asset(ltrim((string) config("connectors.{$slug}.logo_url"), '/')),
            fn (): array => [
                $this->text("{$property}_client_id", 'Client ID', required: true),
                $this->secret("{$property}_client_secret", 'Client secret'),
            ],
        )
            ->required("{$property}_client_id", "{$property}_client_secret")
            ->console(self::CONSOLES[$slug])
            ->helpFile('connector')
            ->callbacks(['Redirect URL' => 'connectors.oauth.callback']);
    }
}
