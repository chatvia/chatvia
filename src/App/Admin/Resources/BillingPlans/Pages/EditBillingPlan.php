<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingPlans\Pages;

use App\Admin\Billing\StripeSyncNotification;
use App\Admin\Resources\BillingPlans\BillingPlanResource;
use App\Admin\Resources\BillingPlans\Schemas\BillingPlanForm;
use Domain\Billing\Actions\Catalog\SaveBillingPlanAction;
use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingPlan;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Edits a plan through {@see SaveBillingPlanAction}, which also sends it to
 * Stripe: a changed amount becomes a new Stripe price for new customers,
 * while existing subscribers keep theirs. The key cannot change.
 *
 * @property-read BillingPlan $record
 */
class EditBillingPlan extends EditRecord
{
    #[\Override]
    protected static string $resource = BillingPlanResource::class;

    private ?StripeCatalogSyncData $stripeResult = null;

    protected function getHeaderActions(): array
    {
        return [
            BillingPlanResource::archiveAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BillingPlanForm::fill($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BillingPlan $record */
        try {
            $saved = app(SaveBillingPlanAction::class)->execute(BillingPlanForm::toData($data, $record->key), $record);
        } catch (InvalidBillingCatalogException $e) {
            $this->refuse($e);
        }

        $this->stripeResult = $saved->stripe;

        return $saved->plan;
    }

    protected function getSavedNotification(): ?Notification
    {
        return $this->stripeResult instanceof StripeCatalogSyncData
            ? StripeSyncNotification::make($this->stripeResult, 'Plan saved')
            : parent::getSavedNotification();
    }

    /** Shows why the action refused the change and keeps the form open. */
    private function refuse(InvalidBillingCatalogException $e): never
    {
        Notification::make()->danger()->title('Plan not saved')->body($e->getMessage())->send();

        throw new Halt;
    }
}
