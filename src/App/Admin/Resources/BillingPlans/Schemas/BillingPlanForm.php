<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingPlans\Schemas;

use App\Admin\Billing\BillingAmount;
use App\Admin\Billing\BillingFormNotices;
use Domain\Billing\Actions\Catalog\SaveBillingPlanAction;
use Domain\Billing\Data\Catalog\BillingPlanData;
use Domain\Billing\Data\Catalog\BillingPlanLimitsData;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Enums\Capability;
use Domain\Billing\Enums\PlanFeature;
use Domain\Billing\Models\BillingPlan;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * The create / edit form of a plan, and the mapping between a plan and the
 * form state. Prices are typed as decimal amounts in the billing currency and
 * saved in cents. The key can only be typed when the plan is created.
 *
 * Feature toggles cover every {@see Capability} plus the flags of
 * {@see BillingPackageCapabilitiesData} that have no capability middleware.
 */
final class BillingPlanForm
{
    /** The icons the plan cards can show. */
    public const array ICONS = [
        'zap' => 'Lightning (zap)',
        'star' => 'Star',
        'crown' => 'Crown',
        'building' => 'Building',
        'gift' => 'Gift',
    ];

    /** Feature flags that are not a {@see Capability} case. */
    private const array OTHER_FEATURES = [
        'removeBranding' => 'Remove Chatvia branding',
        'security' => 'Security & compliance (access control, retention, security log)',
        'siemWebhook' => 'SIEM webhook',
    ];

    /** Fixed limits, by `BillingPlanLimitsData` property. */
    private const array FIXED_LIMITS = [
        'agents' => 'AI agents',
        'pages' => 'Pages (documents)',
        'minutes' => 'Audio / video minutes',
        'links' => 'Website links',
        'customDomains' => 'Custom domains',
        'workspaceUsers' => 'Members (team members + chatters)',
        'teams' => 'Teams',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                BillingFormNotices::notSentToStripe(),
                BillingFormNotices::currencyChange(fn (?Model $record): ?string => $record instanceof BillingPlan ? $record->currency : null),
                self::basics(),
                self::prices(),
                self::limits(),
                self::features(),
                self::extraFeatures(),
                self::stripe(),
            ]);
    }

    /**
     * The form state of a saved plan.
     *
     * @return array<string, mixed>
     */
    public static function fill(BillingPlan $plan): array
    {
        $limits = BillingPlanLimitsData::fromQuotaLimits($plan->quota_limits);

        return [
            'key' => $plan->key,
            'name' => $plan->name,
            'description' => $plan->description,
            'icon' => $plan->icon,
            'sort_order' => $plan->sort_order,
            'is_active' => $plan->is_active,
            'is_default' => $plan->is_default,
            'is_popular' => $plan->is_popular,
            'contact_sales' => $plan->contact_sales,
            'monthly_price' => BillingAmount::fromCents($plan->monthly_price_cents),
            'yearly_price' => BillingAmount::fromCents($plan->yearly_price_cents),
            'limits' => $limits->toArray(),
            'capabilities' => $plan->capabilities->toArray(),
            'highlights' => $plan->highlights ?? [],
            'extra_features' => $plan->extra_features,
        ];
    }

    /**
     * The plan the admin typed, for {@see SaveBillingPlanAction}.
     *
     * @param  array<string, mixed>  $state
     */
    public static function toData(array $state, string $key): BillingPlanData
    {
        $limits = (array) ($state['limits'] ?? []);
        $capabilities = (array) ($state['capabilities'] ?? []);
        $flags = array_keys([...self::capabilityLabels(), ...self::OTHER_FEATURES]);

        return new BillingPlanData(
            key: $key,
            name: trim((string) $state['name']),
            description: filled($state['description'] ?? null) ? trim((string) $state['description']) : null,
            icon: filled($state['icon'] ?? null) ? (string) $state['icon'] : null,
            sortOrder: (int) ($state['sort_order'] ?? 0),
            isActive: (bool) ($state['is_active'] ?? true),
            isDefault: (bool) ($state['is_default'] ?? false),
            isPopular: (bool) ($state['is_popular'] ?? false),
            contactSales: (bool) ($state['contact_sales'] ?? false),
            monthlyPriceCents: BillingAmount::toCents($state['monthly_price'] ?? null) ?? 0,
            yearlyPriceCents: BillingAmount::toCents($state['yearly_price'] ?? null),
            currency: BillingAmount::currency(),
            limits: new BillingPlanLimitsData(...array_map(intval(...), $limits)),
            capabilities: BillingPackageCapabilitiesData::fromArray([
                ...array_map(fn (string $flag): bool => (bool) ($capabilities[$flag] ?? false), array_combine($flags, $flags)),
                'retentionDays' => (int) ($capabilities['retentionDays'] ?? BillingPackageCapabilitiesData::DEFAULT_RETENTION_DAYS),
            ]),
            extraFeatures: array_values(array_filter(
                array_map(fn (mixed $line): string => trim((string) $line), (array) ($state['extra_features'] ?? [])),
                filled(...),
            )),
            highlights: array_values(array_filter(array_map(
                fn (mixed $value): ?PlanFeature => in_array($feature = PlanFeature::tryFrom((string) $value), PlanFeature::highlights(), true) ? $feature : null,
                (array) ($state['highlights'] ?? []),
            ))),
        );
    }

    private static function basics(): Section
    {
        return Section::make('Basics')
            ->icon(Heroicon::OutlinedTag)
            ->columns(2)
            ->schema([
                TextInput::make('key')
                    ->required()
                    ->maxLength(SaveBillingPlanAction::KEY_MAX_LENGTH)
                    ->regex(SaveBillingPlanAction::KEY_PATTERN)
                    ->unique(BillingPlan::class, 'key', ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('Lower-case letters, digits and underscores. Stored in Stripe and on every workspace, so it cannot change later.'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Select::make('icon')
                    ->options(self::ICONS)
                    ->placeholder('No icon'),
                TextInput::make('sort_order')
                    ->label('Position')
                    ->integer()
                    ->required()
                    ->default(0)
                    ->helperText('Plans are shown from the lowest number to the highest.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    // The default plan must stay active; it is still sent with the form.
                    ->disabled(fn (?BillingPlan $record): bool => (bool) $record?->is_default)
                    ->dehydrated()
                    ->helperText('Off: archived. Not offered any more; current subscribers keep it. The default plan is always active.'),
                Toggle::make('is_default')
                    ->label('Default plan')
                    // There is always one default: it moves by turning it on for another plan.
                    ->disabled(fn (?BillingPlan $record): bool => (bool) $record?->is_default)
                    ->dehydrated()
                    ->helperText('New accounts start on it. Turning it on takes the flag from the current default plan.'),
                Toggle::make('is_popular')
                    ->label('Most popular')
                    ->helperText('Shows the "Most popular" badge on the plan card.'),
                Toggle::make('contact_sales')
                    ->label('Contact sales')
                    ->helperText('Not sold online: the plan card links to your website and the plan has no Stripe prices.'),
            ]);
    }

    private static function prices(): Section
    {
        $currency = BillingAmount::currencyCode();

        return Section::make('Prices')
            ->icon(Heroicon::OutlinedBanknotes)
            ->description('Changing a price creates a new price in Stripe. New customers pay the new price; existing subscribers keep the price they pay now.')
            ->columns(3)
            ->schema([
                TextInput::make('monthly_price')
                    ->label('Monthly price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(BillingAmount::MAX)
                    ->step('0.01')
                    ->rule('decimal:0,2')
                    ->prefix($currency)
                    ->default('0.00')
                    ->helperText('0 means the plan is not sold monthly.'),
                TextInput::make('yearly_price')
                    ->label('Yearly price')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(BillingAmount::MAX)
                    ->step('0.01')
                    ->rule('decimal:0,2')
                    ->prefix($currency)
                    ->helperText('The price for a whole year. Empty or 0 means the plan is not sold yearly.'),
                TextEntry::make('billing_currency')
                    ->label('Currency')
                    ->state($currency)
                    ->helperText('Set in Settings → Billing.'),
            ]);
    }

    private static function limits(): Section
    {
        return Section::make('Limits')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->columns(3)
            ->schema([
                self::limitInput('limits.monthlyMessages', 'Messages per month')
                    ->helperText('Message credits of a monthly subscription, every month.'),
                self::limitInput('limits.yearlyMessages', 'Messages per year')
                    ->helperText('Message credits of a yearly subscription, all at once.'),
                ...array_map(
                    fn (string $name, string $label): TextInput => self::limitInput("limits.{$name}", $label),
                    array_keys(self::FIXED_LIMITS),
                    self::FIXED_LIMITS,
                ),
            ]);
    }

    private static function features(): Section
    {
        $toggles = [];

        foreach ([...self::capabilityLabels(), ...self::OTHER_FEATURES] as $flag => $label) {
            $toggles[] = Toggle::make("capabilities.{$flag}")->label($label);
        }

        return Section::make('Features')
            ->icon(Heroicon::OutlinedSparkles)
            ->columns(3)
            ->schema([
                ...$toggles,
                TextInput::make('capabilities.retentionDays')
                    ->label('Days to keep conversations')
                    ->integer()
                    ->required()
                    ->minValue(1)
                    ->maxValue(3650)
                    ->default(BillingPackageCapabilitiesData::DEFAULT_RETENTION_DAYS),
            ]);
    }

    private static function extraFeatures(): Section
    {
        return Section::make('Extra feature lines')
            ->icon(Heroicon::OutlinedListBullet)
            ->description('What the plan offers outside the app, shown after the lines made from the limits and features above. Ticked highlights are translated into every language; your own lines are shown exactly as typed.')
            ->schema([
                CheckboxList::make('highlights')
                    ->label('Highlights')
                    ->options(self::highlightLabels())
                    ->columns(2),
                Repeater::make('extra_features')
                    ->label('Your own lines')
                    ->simple(TextInput::make('line')->required()->maxLength(120))
                    ->addActionLabel('Add a line')
                    ->defaultItems(0)
                    ->reorderable(),
            ]);
    }

    /**
     * The English label of each highlight, keyed by its stored value.
     *
     * @return array<string, string>
     */
    private static function highlightLabels(): array
    {
        return [
            PlanFeature::CustomIntegrations->value => 'Custom integrations',
            PlanFeature::EuDataResidency->value => 'EU data residency',
            PlanFeature::SsoSaml->value => 'SSO / SAML',
            PlanFeature::UptimeSla->value => 'Uptime SLA',
            PlanFeature::DedicatedSupport->value => 'Dedicated support',
            PlanFeature::CustomContract->value => 'Custom contract & DPA',
        ];
    }

    /** The Stripe ids, read-only: only the sync writes them. */
    private static function stripe(): Section
    {
        return Section::make('Stripe')
            ->icon(Heroicon::OutlinedLink)
            ->description('Written by the sync with Stripe. Older prices stay linked to the plan, so subscriptions on them keep working.')
            ->visibleOn('edit')
            ->collapsed()
            ->columns(2)
            ->schema([
                self::stripeId('stripe_product_id', 'Product'),
                self::stripeId('stripe_monthly_price_id', 'Monthly price'),
                self::stripeId('stripe_yearly_price_id', 'Yearly price'),
                TextEntry::make('legacy_stripe_price_ids')
                    ->label('Older prices')
                    ->state(fn (?BillingPlan $record): array => array_keys($record->legacy_stripe_price_ids ?? []))
                    ->fontFamily(FontFamily::Mono)
                    ->listWithLineBreaks()
                    ->placeholder('None'),
            ]);
    }

    private static function stripeId(string $column, string $label): TextEntry
    {
        return TextEntry::make($column)
            ->label($label)
            ->state(fn (?BillingPlan $record): ?string => $record?->{$column})
            ->fontFamily(FontFamily::Mono)
            ->copyable()
            ->placeholder('None');
    }

    private static function limitInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->integer()
            ->required()
            ->minValue(0)
            ->default(0);
    }

    /**
     * @return array<string, string> capability value => label
     */
    private static function capabilityLabels(): array
    {
        $labels = [];

        foreach (Capability::cases() as $capability) {
            $labels[$capability->value] = $capability->label();
        }

        return $labels;
    }
}
