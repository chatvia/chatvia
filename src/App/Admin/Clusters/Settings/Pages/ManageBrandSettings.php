<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\BrandSettings;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * The product name and links the install shows instead of Chatvia's, and the
 * "Powered by" credit.
 */
class ManageBrandSettings extends AdminSettingsPage
{
    #[\Override]
    protected static string $settings = BrandSettings::class;

    #[\Override]
    protected static ?string $title = 'Brand';

    #[\Override]
    protected static ?string $slug = 'brand';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    #[\Override]
    protected static ?int $navigationSort = 9;

    public static function description(): string
    {
        return 'Your product name, links and "Powered by" credit.';
    }

    public static function overviewGroup(): string
    {
        return 'Brand and platform';
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('name', 'Name and links', 'Shown in the app and in emails.', Heroicon::OutlinedSwatch, fn (): array => [
                $this->text('name', 'Product name'),
                $this->url('website_url', 'Website', help: 'The logo on the sign-in pages leads here. Leave empty to lead to the sign-in page.'),
                $this->url('docs_url', 'Documentation', help: 'The Documentation link in the dashboard header. Leave empty to hide it.'),
                $this->url('api_docs_url', 'API documentation', help: 'Leave empty to use the API reference this app serves at /docs/api.'),
            ])->summary(fn (): string => 'Name: '.$this->shown($this->valueInUse('name'))),
            SettingsCard::make('powered_by', 'Powered by', 'The credit under hosted chats and widgets. Each agent can also hide it.', Heroicon::OutlinedTag, fn (): array => [
                $this->toggle('powered_by_enabled', 'Show the credit'),
                $this->text('powered_by_label', 'Label'),
                $this->url('powered_by_url', 'Link'),
            ])->summary(fn (): string => $this->valueInUse('powered_by_enabled')
                ? 'Shown: "Powered by '.$this->shown($this->valueInUse('powered_by_label')).'"'
                : 'Hidden'),
        ];
    }
}
