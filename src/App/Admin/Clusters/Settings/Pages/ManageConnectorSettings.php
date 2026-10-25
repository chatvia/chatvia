<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\ConnectorSettings;
use App\Admin\Settings\SettingStatus;
use BackedEnum;
use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The OAuth apps that let workspaces connect services whose MCP servers do not
 * support dynamic client registration (one card per
 * {@see PreRegisteredOAuthConnector} in the catalog), then the services behind
 * the built-in Perplexity, Weather and Currency connectors.
 */
class ManageConnectorSettings extends AdminSettingsPage
{
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
        return 'OAuth apps for GitHub, Gmail, Slack and more, and the services behind the built-in tools.';
    }

    public static function overviewGroup(): string
    {
        return 'Connections';
    }

    /**
     * How many OAuth apps are set up. The cards of the built-in tools' services
     * hold only addresses and models with working defaults, so they never count.
     *
     * @return array{0: string, 1: string}
     */
    #[\Override]
    public function overviewStatus(): array
    {
        if (($unfinished = $this->unfinishedStatus()) !== null) {
            return $unfinished;
        }

        $ready = count(array_filter(
            $this->visibleCards(),
            fn (SettingsCard $card): bool => $card->required !== [] && $this->statusOf($card) === SettingStatus::Configured,
        ));

        return $ready === 0 ? ['Optional: none set up yet', 'gray'] : ["{$ready} set up", 'success'];
    }

    protected function cards(): array
    {
        return [
            ...app(ConnectorCatalog::class)->preRegisteredOAuth()
                ->map($this->connector(...))
                ->values()
                ->all(),
            SettingsCard::make('perplexity', 'Perplexity', 'The service behind the Perplexity tool (web search). Each workspace adds its own API key.', Heroicon::OutlinedGlobeAlt, fn (): array => [
                $this->url('perplexity_base_url', 'Address'),
                $this->text('perplexity_agent_model', 'Search model',
                    help: 'The Perplexity model for web searches, reading a page, finance data, finding people and running code.'),
                $this->text('perplexity_ask_model', 'Ask model',
                    help: 'The Perplexity model that writes a full answer, with its reasons, from web sources.'),
                $this->text('perplexity_research_model', 'Research model',
                    help: 'The Perplexity model for deep research across many sources. It is slower.'),
            ]),
            SettingsCard::make('openweather', 'OpenWeather', 'The service behind the Weather tool. Each workspace adds its own API key.', Heroicon::OutlinedCloud, fn (): array => [
                $this->url('openweather_base_url', 'Address'),
            ]),
            SettingsCard::make('exchange_rate', 'ExchangeRate-API', 'The service behind the Currency tool. Each workspace adds its own API key.', Heroicon::OutlinedBanknotes, fn (): array => [
                $this->url('exchange_rate_base_url', 'Address'),
            ]),
        ];
    }

    /**
     * A connector's card. Its fields are the `{slug}_client_id` and
     * `{slug}_client_secret` properties of {@see ConnectorSettings}.
     */
    private function connector(PreRegisteredOAuthConnector $connector): SettingsCard
    {
        $property = Str::snake(Str::replace('-', '_', $connector->slug));

        return SettingsCard::make(
            $property,
            $connector->name,
            "Lets workspaces connect {$connector->name} to their agents.",
            asset(Str::ltrim($connector->logoUrl, '/')),
            fn (): array => [
                $this->text("{$property}_client_id", 'Client ID', required: true),
                $this->secret("{$property}_client_secret", 'Client secret'),
            ],
        )
            ->required("{$property}_client_id", "{$property}_client_secret")
            ->console($connector->appConsoleUrl)
            ->callbacks(['Redirect URL' => 'connectors.oauth.callback']);
    }
}
