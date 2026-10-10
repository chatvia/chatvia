<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingAddons;

use App\Admin\Billing\BillingAmount;
use App\Admin\Billing\BillingFormNotices;
use App\Admin\Billing\StripeSyncState;
use App\Admin\Resources\BillingAddons\Pages\EditBillingAddon;
use App\Admin\Resources\BillingAddons\Pages\ListBillingAddons;
use BackedEnum;
use Domain\Billing\Data\Catalog\BillingAddonData;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Models\BillingAddon;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The add-ons workspaces can buy (`billing_addons`): their texts, prices and
 * whether they are sold. The kinds of add-on are fixed in code, so add-ons are
 * only edited here, never created or deleted. Saving one sends it to Stripe
 * when paid plans are on and a Stripe secret key is saved: message-credit
 * packs as one-time prices, the others as monthly subscription prices.
 */
class BillingAddonResource extends Resource
{
    #[\Override]
    protected static ?string $model = BillingAddon::class;

    #[\Override]
    protected static ?string $slug = 'billing/addons';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    #[\Override]
    protected static ?string $navigationLabel = 'Add-ons';

    #[\Override]
    protected static ?int $navigationSort = 20;

    #[\Override]
    protected static ?string $modelLabel = 'add-on';

    #[\Override]
    protected static ?string $pluralModelLabel = 'add-ons';

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    protected static bool $isGloballySearchable = false;

    /** The icons an add-on can carry. */
    private const array ICONS = [
        'credit-card' => 'Credit card',
        'message-circle' => 'Message',
        'bot' => 'Bot',
        'globe' => 'Globe',
        'sparkles' => 'Sparkles',
        'users' => 'Users',
    ];

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('Add-ons are fixed in code.');
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Add-ons are fixed in code. Turn "Sold" off instead.');
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Add-ons are fixed in code.');
    }

    public static function form(Schema $schema): Schema
    {
        $currency = BillingAmount::currencyCode();

        return $schema
            ->columns(1)
            ->components([
                BillingFormNotices::notSentToStripe(),
                BillingFormNotices::currencyChange(fn (?Model $record): ?string => $record instanceof BillingAddon ? $record->currency : null),
                Section::make('Add-on')
                    ->icon(Heroicon::OutlinedPuzzlePiece)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('key')
                            ->fontFamily(FontFamily::Mono),
                        TextEntry::make('kind')
                            ->label('Sold as')
                            ->state(fn (BillingAddon $record): string => self::kind($record)),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('icon')
                            ->options(self::ICONS)
                            ->placeholder('No icon'),
                        Textarea::make('description')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Sold')
                            ->helperText('Off: workspaces cannot buy it any more. Current subscriptions keep running.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Price')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->description('Changing the price creates a new price in Stripe. New purchases pay the new price; existing subscriptions keep the price they pay now.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price')
                            ->label('Price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(BillingAmount::MAX)
                            ->step('0.01')
                            ->rule('decimal:0,2')
                            ->prefix($currency)
                            ->helperText("In {$currency}, set in Settings → Billing."),
                        TextInput::make('billing_unit')
                            ->label('Billing unit')
                            ->maxLength(255)
                            ->placeholder('per agent/month')
                            ->helperText('Shown next to the price, for example "per agent/month".'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->description(fn (BillingAddon $record): string => self::kind($record)),
                TextColumn::make('key')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('price_cents')
                    ->label('Price')
                    ->money(fn (BillingAddon $record): string => strtoupper($record->currency), divideBy: 100),
                TextColumn::make('billing_unit')
                    ->label('Billing unit')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Sold')
                    ->boolean(),
                TextColumn::make('stripe')
                    ->label('Stripe')
                    ->badge()
                    ->tooltip('Synced: it has a Stripe product and price. Saving it checks the amount against Stripe.')
                    ->state(fn (BillingAddon $record): StripeSyncState => StripeSyncState::forAddon($record)),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
            ]);
    }

    /**
     * The form state of a saved add-on.
     *
     * @return array<string, mixed>
     */
    public static function fill(BillingAddon $addon): array
    {
        return [
            'name' => $addon->name,
            'description' => $addon->description,
            'icon' => $addon->icon,
            'is_active' => $addon->is_active,
            'price' => BillingAmount::fromCents($addon->price_cents),
            'billing_unit' => $addon->billing_unit,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function toData(array $state): BillingAddonData
    {
        return new BillingAddonData(
            name: trim((string) $state['name']),
            description: filled($state['description'] ?? null) ? trim((string) $state['description']) : null,
            isActive: (bool) ($state['is_active'] ?? false),
            priceCents: BillingAmount::toCents($state['price'] ?? null) ?? 0,
            currency: BillingAmount::currency(),
            billingUnit: filled($state['billing_unit'] ?? null) ? trim((string) $state['billing_unit']) : null,
            icon: filled($state['icon'] ?? null) ? (string) $state['icon'] : null,
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingAddons::route('/'),
            'edit' => EditBillingAddon::route('/{record}/edit'),
        ];
    }

    private static function kind(BillingAddon $addon): string
    {
        return MessageCreditAddon::tryFrom($addon->key) instanceof MessageCreditAddon
            ? 'One-time credit pack'
            : 'Monthly subscription';
    }
}
