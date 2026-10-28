<?php

declare(strict_types=1);

namespace App\Admin\Pages;

use App\Admin\AdminPanelProvider;
use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\BrandSettings;
use App\Admin\Settings\CreditLicense;
use App\Admin\Settings\LicenseActivation;
use App\Admin\Settings\LicenseStatus;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Support\License\LicenseFeature;
use Support\License\LicenseKey;
use UnitEnum;

/**
 * License: the license key, the "Powered by ChatVia.ai" credit, and a link
 * to hire the developer. It has its own place in the sidebar, below
 * Settings, but is built like a Settings page (cards, one Save button).
 * Hiding the credit needs a license key activated for this install's domain
 * ({@see CreditLicense}): the page activates a new key at chatvia.ai before
 * it saves it ({@see LicenseActivation}), and does not turn the credit off
 * without a valid one.
 */
class License extends AdminSettingsPage
{
    #[\Override]
    protected static ?string $cluster = null;

    #[\Override]
    protected static string $settings = BrandSettings::class;

    #[\Override]
    protected static ?string $title = 'License';

    #[\Override]
    protected static ?string $slug = 'license';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = AdminPanelProvider::SYSTEM_NAVIGATION_GROUP;

    #[\Override]
    protected static ?int $navigationSort = 101;

    public static function description(): string
    {
        return 'Your license key, the "Powered by ChatVia.ai" credit, and help from the developer.';
    }

    #[\Override]
    protected static function docsPath(): string
    {
        return 'license';
    }

    /**
     * A new key is activated for this domain before it is saved (a key as
     * bought) or must already be activated for it, and the credit can only
     * be turned off with a valid key (the new one or the saved one).
     */
    #[\Override]
    public function save(): void
    {
        $state = $this->form->getState();
        $typed = filled($state['license_key'] ?? null) ? $this->activatedKey((string) $state['license_key']) : null;

        if (($state['powered_by_enabled'] ?? true) === false && ! $this->license()->allowsHidingCredit($typed ?? $this->savedKey())) {
            throw ValidationException::withMessages(['data.powered_by_enabled' => 'Hiding the credit needs a license. Add your license key below and save again.']);
        }

        if ($typed !== null) {
            $this->data['license_key'] = $typed;
        }

        parent::save();
    }

    protected function cards(): array
    {
        return [
            SettingsCard::make('powered_by', 'Powered by', 'The credit under the hosted chat and widget of every agent.', Heroicon::OutlinedTag, fn (): array => [
                $this->toggle('powered_by_enabled', 'Show the credit')
                    ->disabled(fn (): bool => ! $this->licensed())
                    ->formatStateUsing(fn (?bool $state): bool => $this->licensed() ? $state !== false : true)
                    ->helperText(fn (): ?string => $this->licensed() ? null : 'Add a license key below to hide the credit.'),
                $this->secret('license_key', 'License key', 'Paste the whole key from your email. Saving it activates the license for this site at chatvia.ai.', required: false),
                $this->licenseCallout(),
            ])->summary(fn (): string => $this->summary()),
            SettingsCard::make('hire_me', 'Hire me', 'Custom features, setup and support from the developer of ChatVia.ai.', Heroicon::OutlinedBriefcase, fn (): array => [
                $this->hireMeCallout(),
            ])->summary(fn (): string => 'mayahi.net'),
        ];
    }

    /** The developer's services, for installs that want help with ChatVia.ai. */
    private function hireMeCallout(): Callout
    {
        return Callout::make('Need help with ChatVia.ai?')
            ->description('I build ChatVia.ai. I can add custom features, set it up on your servers, connect it to your own systems, and help your team run it.')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->actions([
                Action::make('hireMe')
                    ->label('See my services')
                    ->url('https://mayahi.net/services')
                    ->openUrlInNewTab(),
            ]);
    }

    #[\Override]
    protected function forgetDescription(string $property, string $label): string
    {
        return $property === 'license_key'
            ? 'The "Powered by ChatVia.ai" credit shows again under every hosted chat and widget. The key is revoked at chatvia.ai, so you can use it on another install.'
            : parent::forgetDescription($property, $label);
    }

    /**
     * A removed key is freed at chatvia.ai, so it can move to another domain,
     * and the switch goes back on: the credit shows again.
     */
    #[\Override]
    protected function forgotten(string $property, mixed $previous): void
    {
        if ($property !== 'license_key') {
            return;
        }

        if (filled($previous) && $this->license()->read($previous) instanceof LicenseKey) {
            app(LicenseActivation::class)->release($previous);
        }

        $settings = app(BrandSettings::class);
        $settings->powered_by_enabled = null;
        $settings->save();

        $this->fillCards();
    }

    /** Where the license stands, with links to buy one (while there is none) and to manage it. */
    private function licenseCallout(): Callout
    {
        return Callout::make(fn (): string => match ($this->savedStatus()) {
            LicenseStatus::Valid => "{$this->licenseType()} license, licensed to ".$this->license()->read($this->savedKey())?->licensee,
            LicenseStatus::Missing => 'Hiding the credit needs a license',
            LicenseStatus::Expired => 'The license has ended',
            default => 'The license is not active here',
        })
            ->description(fn (): string => match ($this->savedStatus()) {
                LicenseStatus::Valid => $this->licenseType() === 'Enterprise'
                    ? 'You can hide the credit, and the Enterprise features are on. It renews every year at chatvia.ai.'
                    : 'You can hide the credit.',
                LicenseStatus::Missing => 'A Pro license costs $199 a year. An Enterprise license costs $1,490 a year: it also hides the credit and turns on the Enterprise security features.',
                LicenseStatus::Invalid => 'The saved key is not valid. The credit stays on until you add a valid key.',
                LicenseStatus::Expired => "It ended on {$this->license()->read($this->savedKey())?->expires}, so the free limits and the credit are back. Renew it with Manage license: it works again after the next daily check, or right away when you save the key again.",
                default => 'Paste your license key below and save to activate it. The credit stays on until then.',
            })
            ->icon(fn (): Heroicon => match ($this->savedStatus()) {
                LicenseStatus::Valid => Heroicon::OutlinedCheckBadge,
                LicenseStatus::Missing => Heroicon::OutlinedInformationCircle,
                default => Heroicon::OutlinedExclamationTriangle,
            })
            ->status(fn (): string => match ($this->savedStatus()) {
                LicenseStatus::Valid => 'success',
                LicenseStatus::Missing => 'info',
                default => 'warning',
            })
            ->actions([
                Action::make('buyLicense')
                    ->label('Buy a license')
                    ->url(fn (): ?string => config('chatvia.license.buy_url'))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => filled(config('chatvia.license.buy_url')) && $this->savedStatus() !== LicenseStatus::Valid),
                Action::make('manageLicense')
                    ->label('Manage license')
                    ->url(fn (): ?string => config('chatvia.license.manage_url'))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => filled(config('chatvia.license.manage_url'))),
            ]);
    }

    /** "Enterprise" or "Pro", by what the saved key unlocks. */
    private function licenseType(): string
    {
        return $this->license()->allows($this->savedKey(), LicenseFeature::Enterprise) ? 'Enterprise' : 'Pro';
    }

    /**
     * The typed key activated for this domain at chatvia.ai, ready to save.
     * chatvia.ai decides whether the key is free for this domain, so a key
     * activated before (here or on a domain it was moved from) goes through
     * it too. Stops the save when the key is not real or not activated.
     */
    private function activatedKey(string $key): string
    {
        if ($this->license()->status($key) === LicenseStatus::Invalid) {
            throw ValidationException::withMessages(['data.license_key' => 'This is not a valid license key. Check that you copied all of it.']);
        }

        $activation = app(LicenseActivation::class)->activate($key);

        return $activation->key ?? throw ValidationException::withMessages(['data.license_key' => (string) $activation->message]);
    }

    private function summary(): string
    {
        $shown = $this->creditShows() ? 'Shown' : 'Hidden';

        return $this->savedStatus() === LicenseStatus::Valid ? "{$shown} · licensed" : $shown;
    }

    /** Whether the credit shows now, after the license check. */
    private function creditShows(): bool
    {
        return config('chatvia.powered_by') !== false;
    }

    /** Whether the saved key allows hiding the credit; the switch is greyed out until it does. */
    private function licensed(): bool
    {
        return $this->savedStatus() === LicenseStatus::Valid;
    }

    private function savedStatus(): LicenseStatus
    {
        return $this->license()->status($this->savedKey());
    }

    private function savedKey(): ?string
    {
        $key = $this->valueInUse('license_key');

        return filled($key) ? $key : null;
    }

    private function license(): CreditLicense
    {
        return app(CreditLicense::class);
    }
}
