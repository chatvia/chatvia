<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingPlans\Tables;

use App\Admin\Billing\StripeSyncState;
use App\Admin\Resources\BillingPlans\BillingPlanResource;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Queries\BillingPlanUsageQuery;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The list of plans: prices in the plan's currency, flags, how many
 * workspaces are subscribed now, and whether the plan is linked to Stripe.
 */
final class BillingPlansTable
{
    public static function configure(Table $table): Table
    {
        // Counted once per page load, for every plan at the same time.
        $subscribers = null;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('key')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable(),
                self::price('monthly_price_cents', 'Monthly'),
                self::price('yearly_price_cents', 'Yearly'),
                IconColumn::make('is_popular')->label('Popular')->boolean(),
                IconColumn::make('is_default')->label('Default')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                IconColumn::make('contact_sales')->label('Contact sales')->boolean(),
                TextColumn::make('subscribers')
                    ->badge()
                    ->color('gray')
                    ->tooltip('Active subscriptions and trials on one of the plan\'s Stripe prices.')
                    ->state(function (BillingPlan $record) use (&$subscribers): int {
                        $subscribers ??= app(BillingPlanUsageQuery::class)->subscriberCounts();

                        return $subscribers[$record->key] ?? 0;
                    }),
                TextColumn::make('stripe')
                    ->label('Stripe')
                    ->badge()
                    ->tooltip('Synced: it has a Stripe product and a price for every way it is sold. Saving it checks the amounts against Stripe.')
                    ->state(fn (BillingPlan $record): StripeSyncState => StripeSyncState::forPlan($record)),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All plans')
                    ->trueLabel('Active only')
                    ->falseLabel('Archived only'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order')->orderBy('id'))
            ->recordActions([
                EditAction::make(),
                BillingPlanResource::archiveAction(),
            ]);
    }

    private static function price(string $column, string $label): TextColumn
    {
        return TextColumn::make($column)
            ->label($label)
            ->state(fn (BillingPlan $record): ?int => $record->contact_sales || ! $record->{$column} ? null : $record->{$column})
            ->money(fn (BillingPlan $record): string => strtoupper($record->currency), divideBy: 100)
            ->placeholder('—');
    }
}
