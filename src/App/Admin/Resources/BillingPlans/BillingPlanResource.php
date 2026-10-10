<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingPlans;

use App\Admin\Resources\BillingPlans\Pages\CreateBillingPlan;
use App\Admin\Resources\BillingPlans\Pages\EditBillingPlan;
use App\Admin\Resources\BillingPlans\Pages\ListBillingPlans;
use App\Admin\Resources\BillingPlans\Schemas\BillingPlanForm;
use App\Admin\Resources\BillingPlans\Tables\BillingPlansTable;
use BackedEnum;
use Domain\Billing\Actions\Catalog\ArchiveBillingPlanAction;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Queries\BillingPlanUsageQuery;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The plans workspaces subscribe to (`billing_plans`): prices, limits,
 * features and the extra feature lines of the plan cards. Saving a plan sends
 * it to Stripe when paid plans are on and a Stripe secret key is saved.
 *
 * A plan is never deleted once a workspace is or was on it: it is archived
 * instead, so old subscriptions and renewals still find it. Only a plan that
 * no workspace ever used can be deleted.
 */
class BillingPlanResource extends Resource
{
    #[\Override]
    protected static ?string $model = BillingPlan::class;

    #[\Override]
    protected static ?string $slug = 'billing/plans';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    #[\Override]
    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    #[\Override]
    protected static ?string $navigationLabel = 'Plans';

    #[\Override]
    protected static ?int $navigationSort = 10;

    #[\Override]
    protected static ?string $modelLabel = 'plan';

    #[\Override]
    protected static ?string $pluralModelLabel = 'plans';

    #[\Override]
    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return BillingPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillingPlansTable::configure($table);
    }

    /**
     * Only a plan no workspace ever used can be deleted, and never the
     * default plan. Every other plan is archived instead.
     */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return match (true) {
            ! $record instanceof BillingPlan, $record->is_default => Response::deny('The default plan cannot be deleted.'),
            app(BillingPlanUsageQuery::class)->wasEverUsed($record) => Response::deny('A plan a workspace is or was on cannot be deleted. Archive it instead.'),
            default => Response::allow(),
        };
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    /**
     * Archives a plan (the list row and the edit page). The default plan
     * cannot be archived; the action explains why instead of hiding.
     */
    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->label('Archive')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('warning')
            ->visible(fn (BillingPlan $record): bool => $record->is_active)
            ->requiresConfirmation()
            ->modalHeading(fn (BillingPlan $record): string => "Archive {$record->name}?")
            ->modalDescription('The plan is no longer offered. Workspaces on it keep it, and their renewals keep working. Nothing changes in Stripe. You can make it active again later.')
            ->modalSubmitActionLabel('Archive')
            ->action(function (BillingPlan $record, Action $action): void {
                try {
                    app(ArchiveBillingPlanAction::class)->execute($record);
                } catch (InvalidBillingCatalogException $e) {
                    Notification::make()->danger()->title('Not archived')->body($e->getMessage())->send();
                    $action->cancel();
                }

                Notification::make()->success()->title("{$record->name} archived")->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingPlans::route('/'),
            'create' => CreateBillingPlan::route('/create'),
            'edit' => EditBillingPlan::route('/{record}/edit'),
        ];
    }
}
